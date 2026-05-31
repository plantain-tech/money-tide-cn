<?php

declare(strict_types=1);

/**
 * Sprint 2 · Day 10·1 — Pipeline analytics & observability.
 *
 * Turns the raw pipeline_runs log into trends, a conversion funnel, KPIs and
 * derived insights, so the owner can SEE whether the autonomous engine is
 * healthy and tune thresholds / throttle with evidence instead of guessing.
 *
 * All aggregation is done in PHP over a bounded window (a handful of runs/day),
 * so it stays portable across MariaDB versions (no JSON_TABLE dependency).
 */

/** Allowed look-back windows for the dashboard. */
function pipeline_analytics_windows(): array
{
    return [7 => '7 天', 14 => '14 天', 30 => '30 天'];
}

function pipeline_analytics_clamp_days(int $days): int
{
    return array_key_exists($days, pipeline_analytics_windows()) ? $days : 14;
}

/**
 * Fetch parsed runs within the window (excludes paused skips for throughput
 * maths but keeps them available via the 'all' set).
 */
function pipeline_analytics_runs(int $days): array
{
    ensure_pipeline_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $days = pipeline_analytics_clamp_days($days);
    try {
        // $days is an int from clamp(), safe to inline; INTERVAL binding is finicky.
        $rows = $pdo->query('SELECT trigger_type, status, stages, summary, duration_sec, started_at
            FROM pipeline_runs
            WHERE started_at >= (NOW() - INTERVAL ' . (int) $days . ' DAY)
            ORDER BY started_at ASC')->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['stages'] = json_decode((string) ($row['stages'] ?? '{}'), true) ?: [];
    }
    unset($row);
    return $rows;
}

/** Pull the six canonical stage counts out of one run's stages JSON. */
function pipeline_run_stage_counts(array $stages): array
{
    return [
        'ingest' => (int) ($stages['ingest']['new_items'] ?? 0),
        'cluster' => (int) ($stages['cluster']['clusters'] ?? 0),
        'draft' => (int) ($stages['synthesize']['drafts'] ?? 0),
        'auto' => (int) ($stages['assess']['auto'] ?? 0),
        'review' => (int) ($stages['assess']['review'] ?? 0),
        'publish' => (int) ($stages['publish']['articles'] ?? 0),
        'issue' => (int) ($stages['assemble']['issues'] ?? 0),
    ];
}

/**
 * Headline KPIs + the conversion funnel + per-day series for charts.
 */
function pipeline_analytics_summary(int $days = 14): array
{
    $days = pipeline_analytics_clamp_days($days);
    $runs = pipeline_analytics_runs($days);

    $totals = ['ingest' => 0, 'cluster' => 0, 'draft' => 0, 'auto' => 0, 'review' => 0, 'publish' => 0, 'issue' => 0];
    $runCount = 0;
    $okCount = 0;
    $pausedCount = 0;
    $failCount = 0;
    $durationSum = 0;
    $durationRuns = 0;
    $zeroDraftRuns = 0; // ran (not paused) but produced 0 drafts -> likely throttling

    // Per-day buckets for the trend chart.
    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $key = date('Y-m-d', strtotime("-{$i} day"));
        $series[$key] = ['date' => $key, 'draft' => 0, 'publish' => 0, 'issue' => 0, 'runs' => 0];
    }

    foreach ($runs as $run) {
        $status = (string) ($run['status'] ?? 'ok');
        if ($status === 'paused') {
            $pausedCount++;
            continue;
        }
        $runCount++;
        if ($status === 'ok') {
            $okCount++;
        } else {
            $failCount++;
        }
        $c = pipeline_run_stage_counts((array) $run['stages']);
        foreach ($totals as $k => $_) {
            $totals[$k] += $c[$k];
        }
        $dur = (int) ($run['duration_sec'] ?? 0);
        if ($dur > 0) {
            $durationSum += $dur;
            $durationRuns++;
        }
        // Throttle heuristic: had material (selected clusters) but 0 drafts.
        if ($c['draft'] === 0 && $c['cluster'] > 0) {
            $zeroDraftRuns++;
        }
        $dayKey = date('Y-m-d', strtotime((string) $run['started_at']));
        if (isset($series[$dayKey])) {
            $series[$dayKey]['draft'] += $c['draft'];
            $series[$dayKey]['publish'] += $c['publish'];
            $series[$dayKey]['issue'] += $c['issue'];
            $series[$dayKey]['runs']++;
        }
    }

    $assessed = $totals['auto'] + $totals['review'];
    $autoRate = $assessed > 0 ? (int) round($totals['auto'] / $assessed * 100) : 0;
    $draftYield = $totals['cluster'] > 0 ? (int) round($totals['draft'] / $totals['cluster'] * 100) : 0;
    $publishYield = $totals['draft'] > 0 ? (int) round($totals['publish'] / $totals['draft'] * 100) : 0;
    $successRate = $runCount > 0 ? (int) round($okCount / $runCount * 100) : 0;

    // Conversion funnel (each stage as a share of the widest stage).
    $funnelMax = max(1, $totals['ingest'], $totals['cluster'], $totals['draft'], $totals['publish'], $totals['issue']);
    $funnel = [
        ['key' => 'ingest', 'icon' => '🛰', 'label' => '摄取素材', 'value' => $totals['ingest']],
        ['key' => 'cluster', 'icon' => '🧩', 'label' => '聚类选题', 'value' => $totals['cluster']],
        ['key' => 'draft', 'icon' => '✍️', 'label' => 'AI 草稿', 'value' => $totals['draft']],
        ['key' => 'publish', 'icon' => '🚀', 'label' => '发布文章', 'value' => $totals['publish']],
        ['key' => 'issue', 'icon' => '📰', 'label' => '组装早报', 'value' => $totals['issue']],
    ];
    foreach ($funnel as &$f) {
        $f['pct'] = (int) round($f['value'] / $funnelMax * 100);
    }
    unset($f);

    return [
        'days' => $days,
        'run_count' => $runCount,
        'ok_count' => $okCount,
        'fail_count' => $failCount,
        'paused_count' => $pausedCount,
        'success_rate' => $successRate,
        'avg_duration' => $durationRuns > 0 ? (int) round($durationSum / $durationRuns) : 0,
        'totals' => $totals,
        'auto_rate' => $autoRate,
        'draft_yield' => $draftYield,
        'publish_yield' => $publishYield,
        'zero_draft_runs' => $zeroDraftRuns,
        'funnel' => $funnel,
        'series' => array_values($series),
        'has_data' => $runCount > 0,
    ];
}

/**
 * Plain-language insights / alerts derived from the summary, each with a
 * severity so the UI can colour them. This is the "what should I do" layer.
 */
function pipeline_analytics_insights(array $summary): array
{
    $out = [];

    if (!$summary['has_data']) {
        $out[] = ['level' => 'info', 'icon' => '🟦', 'text' => '所选时间段还没有运行记录。到自动驾驶页点「立即运行一次」，或开启 Cron 后再回来看趋势。'];
        return $out;
    }

    if ($summary['zero_draft_runs'] > 0) {
        $out[] = [
            'level' => 'warn',
            'icon' => '⚠️',
            'text' => $summary['zero_draft_runs'] . ' 次运行有选题却产出 0 草稿，通常是免费额度被限流。建议在自动驾驶页把「AI 阶段间隔」调到 10–15 秒。',
        ];
    }
    if ($summary['fail_count'] > 0) {
        $out[] = [
            'level' => 'warn',
            'icon' => '🟥',
            'text' => $summary['fail_count'] . ' 次运行失败（成功率 ' . $summary['success_rate'] . '%）。查看下方运行记录定位失败阶段。',
        ];
    }
    if ($summary['draft_yield'] > 0 && $summary['draft_yield'] < 50) {
        $out[] = [
            'level' => 'warn',
            'icon' => '✍️',
            'text' => '聚类→草稿转化率仅 ' . $summary['draft_yield'] . '%，许多选题没被写稿。检查写稿上限或来源质量。',
        ];
    }
    if ($summary['auto_rate'] >= 90) {
        $out[] = [
            'level' => 'info',
            'icon' => '🎚',
            'text' => '自动通过率高达 ' . $summary['auto_rate'] . '%。若想多一层人工把关，可在签收台把阈值调高几分。',
        ];
    } elseif ($summary['auto_rate'] > 0 && $summary['auto_rate'] < 40) {
        $out[] = [
            'level' => 'info',
            'icon' => '🎚',
            'text' => '自动通过率偏低（' . $summary['auto_rate'] . '%），转人工较多。跑顺后可适当下调阈值提升自动化。',
        ];
    }
    if ($summary['avg_duration'] > 240) {
        $out[] = [
            'level' => 'info',
            'icon' => '⏱',
            'text' => '平均单次运行 ' . $summary['avg_duration'] . ' 秒，偏长。可降低单次批量上限，让网页运行更稳。',
        ];
    }

    if (empty($out)) {
        $out[] = ['level' => 'ok', 'icon' => '✅', 'text' => '一切正常：成功率 ' . $summary['success_rate'] . '%，自动通过率 ' . $summary['auto_rate'] . '%，无限流迹象。引擎运行健康。'];
    }
    return $out;
}

/**
 * Build SVG polyline points for a values array fit to a width x height box.
 * Returns ['points' => 'x,y x,y ...', 'area' => 'M ... Z', 'last' => [x,y]].
 */
function pipeline_chart_geometry(array $values, float $w, float $h, float $pad = 4.0, int $forceMax = 0): array
{
    $n = count($values);
    if ($n === 0) {
        return ['points' => '', 'area' => '', 'last' => [0, $h], 'max' => 0];
    }
    $max = $forceMax > 0 ? $forceMax : max(1, (int) max($values));
    $stepX = $n > 1 ? ($w - $pad * 2) / ($n - 1) : 0;
    $pts = [];
    foreach (array_values($values) as $i => $v) {
        $x = $pad + $stepX * $i;
        $y = $h - $pad - ($v / $max) * ($h - $pad * 2);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $line = implode(' ', $pts);
    $first = $pad . ',' . ($h - $pad);
    $lastX = $pad + $stepX * ($n - 1);
    $area = 'M ' . $first . ' L ' . $line . ' L ' . round($lastX, 1) . ',' . ($h - $pad) . ' Z';
    $lastPoint = explode(',', $pts[$n - 1]);
    return ['points' => $line, 'area' => $area, 'last' => [(float) $lastPoint[0], (float) $lastPoint[1]], 'max' => $max];
}
