<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·3 — AI article synthesis.
 *
 * Turns a SELECTED story_cluster into an ORIGINAL Money Tide article draft.
 * The cluster's member headlines + summaries are fed to the category's
 * editorial bot as raw material; the bot writes original Chinese analysis
 * (never copying source text), and the source URLs become attribution links.
 * The result lands in the existing ai_drafts queue (statuses, quality, review),
 * so Day 9·4 (fact-check gate) and Day 9·5 (publish) reuse it unchanged.
 */

/**
 * Synthesize one cluster into an ai_draft. Idempotent: if already synthesized,
 * returns the existing draft id.
 */
function synthesize_cluster_to_draft(int $clusterId): array
{
    ensure_clusters_schema();
    $cluster = function_exists('story_cluster_get') ? story_cluster_get($clusterId) : null;
    if (!$cluster) {
        return ['ok' => false, 'code' => 'missing', 'message' => '未找到该选题 cluster。'];
    }
    if (!empty($cluster['draft_id'])) {
        return ['ok' => true, 'code' => 'exists', 'id' => (int) $cluster['draft_id'], 'message' => '该 cluster 已生成草稿 #' . (int) $cluster['draft_id'] . '。'];
    }

    $members = cluster_member_items((array) ($cluster['item_ids'] ?? []));
    if (!$members) {
        return ['ok' => false, 'code' => 'empty', 'message' => '该 cluster 没有可用的来源素材。'];
    }

    // Build the synthesis material block + attribution links.
    $material = '';
    $links = [];
    foreach ($members as $i => $m) {
        $title = trim((string) ($m['title'] ?? ''));
        $summary = trim((string) ($m['summary'] ?? ''));
        $material .= ($i + 1) . '. ' . $title . ($summary !== '' ? ' — ' . $summary : '') . "\n";
        $url = trim((string) ($m['url'] ?? ''));
        if ($url !== '') {
            $links[] = $url;
        }
    }

    $topicAngle = "选题：" . (string) $cluster['headline'] . "\n"
        . "报道角度：" . (string) ($cluster['angle'] ?? '') . "\n"
        . "为什么重要：" . (string) ($cluster['why_it_matters'] ?? '') . "\n\n"
        . "以下是这条新闻的多来源英文素材（标题 + 摘要）。请综合这些素材，写一篇钱潮 Money Tide 的【原创】中文解读文章："
        . "整合多个来源的信息，补充背景、来龙去脉和对中文读者的影响分析；"
        . "绝不照搬、翻译或逐句复制任何来源原文；只引用其中的事实与数据，不要编造素材中没有的数字或引述。\n\n"
        . "素材：\n" . $material;

    $result = generate_ai_draft([
        'section_slug' => (string) $cluster['category_slug'],
        'topic_angle' => $topicAngle,
        'source_links' => implode("\n", $links),
        'urgency' => 'normal',
    ]);

    if (empty($result['ok'])) {
        return ['ok' => false, 'code' => 'ai_error', 'message' => implode(' ', (array) ($result['errors'] ?? ['草稿生成失败。']))];
    }

    $draftId = (int) $result['id'];

    // Link cluster -> draft, mark cluster used, mark member items used.
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $pdo->prepare("UPDATE story_clusters SET status = 'used', draft_id = :d WHERE id = :id")
                ->execute(['d' => $draftId, 'id' => $clusterId]);
            $ids = array_values(array_filter(array_map('intval', (array) ($cluster['item_ids'] ?? []))));
            if ($ids) {
                $in = implode(',', $ids);
                $pdo->exec("UPDATE news_items SET status = 'used' WHERE id IN ({$in})");
            }
        } catch (Throwable $exception) {
        }
    }

    if (function_exists('record_event')) {
        record_event('news_synthesis', ['slug' => (string) $cluster['category_slug'], 'source' => 'draft:' . $draftId]);
    }

    return ['ok' => true, 'code' => 'ok', 'id' => $draftId, 'message' => '已生成原创草稿 #' . $draftId . '。'];
}

/**
 * Batch-synthesize selected clusters (pipeline + admin "synthesize selected").
 * Paced + retried for free-tier rate limits; bounded by $limit for web use.
 */
function synthesize_selected_clusters(?string $categorySlug = null, int $limit = 8, int $pauseSec = 2, int $maxTries = 2): array
{
    $filters = ['status' => 'selected'];
    if ($categorySlug !== null && $categorySlug !== '') {
        $filters['category_slug'] = $categorySlug;
    }
    $clusters = story_clusters($filters);

    $summary = ['total' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0, 'drafts' => [], 'details' => []];
    $processed = 0;

    foreach ($clusters as $cl) {
        if ($processed >= $limit) {
            break;
        }
        if (!empty($cl['draft_id'])) {
            $summary['skipped']++;
            continue;
        }
        if (!ai_usage_allowed()) {
            $summary['details'][] = ['headline' => (string) $cl['headline'], 'ok' => false, 'message' => 'AI 额度用完，已停止。'];
            $summary['failed']++;
            break;
        }

        $attempt = 1;
        $r = ['ok' => false, 'code' => 'unknown', 'message' => '未执行'];
        while ($attempt <= $maxTries) {
            $r = synthesize_cluster_to_draft((int) $cl['id']);
            if ($r['ok'] || in_array((string) ($r['code'] ?? ''), ['exists', 'missing', 'empty'], true)) {
                break;
            }
            // Stop the whole batch on quota; retry transient AI errors.
            if (stripos((string) ($r['message'] ?? ''), '额度') !== false) {
                break 2;
            }
            if ($attempt < $maxTries) {
                sleep($attempt * 3);
            }
            $attempt++;
        }

        $summary['total']++;
        $processed++;
        if ($r['ok']) {
            $summary['ok']++;
            if (!empty($r['id'])) {
                $summary['drafts'][] = (int) $r['id'];
            }
        } else {
            $summary['failed']++;
        }
        $summary['details'][] = ['headline' => (string) $cl['headline'], 'ok' => $r['ok'], 'message' => $r['message']];

        if ($pauseSec > 0 && $processed < $limit) {
            sleep($pauseSec);
        }
    }

    if (function_exists('record_event')) {
        record_event('news_synthesis_batch', ['source' => $summary['ok'] . ' drafts']);
    }
    return $summary;
}

function synthesis_summary(): array
{
    ensure_clusters_schema();
    $pdo = db();
    $out = ['synthesized' => 0, 'pending_selected' => 0, 'today' => 0];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['synthesized'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'used' AND draft_id IS NOT NULL")->fetchColumn();
        $out['pending_selected'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'selected' AND draft_id IS NULL")->fetchColumn();
        $out['today'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'used' AND draft_id IS NOT NULL AND created_at >= CURDATE()")->fetchColumn();
    } catch (Throwable $exception) {
    }
    return $out;
}
