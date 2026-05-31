<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·2 — Cluster & Select.
 *
 * Takes raw ingested `news_items` per category, asks the AI to dedupe + cluster
 * related stories and rank them by newsworthiness for Chinese finance readers,
 * then stores ranked `story_clusters`. The top picks per category become the
 * shortlist that feeds AI article synthesis on Day 9·3.
 */

function ensure_clusters_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS story_clusters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_slug VARCHAR(40) NOT NULL,
            headline VARCHAR(500) NOT NULL,
            angle VARCHAR(500) NULL,
            why_it_matters TEXT NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            item_ids TEXT NULL,
            item_count INT NOT NULL DEFAULT 0,
            primary_url VARCHAR(700) NULL,
            status ENUM('candidate','selected','skipped','used') NOT NULL DEFAULT 'candidate',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_clusters_cat (category_slug, status, score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

function cluster_status_labels(): array
{
    return ['candidate' => '候选', 'selected' => '已选用', 'skipped' => '已跳过', 'used' => '已生成'];
}

/**
 * Run AI clustering for a single category. Returns a result summary.
 *
 * @param int $topN how many top clusters to auto-mark as 'selected'
 */
function cluster_news_for_category(string $categorySlug, int $topN = 3): array
{
    ensure_news_schema();
    ensure_clusters_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'code' => 'db', 'message' => '数据库未连接。', 'clusters' => 0];
    }

    $provider = ai_provider_status();
    if (!$provider['ready']) {
        return ['ok' => false, 'code' => 'provider', 'message' => $provider['message'], 'clusters' => 0];
    }
    if (!ai_usage_allowed()) {
        return ['ok' => false, 'code' => 'quota', 'message' => '今日 AI 额度已用完。', 'clusters' => 0];
    }

    // Pull recent unclustered items for this category.
    $items = news_items(['category_slug' => $categorySlug, 'status' => 'new', 'limit' => 40]);
    if (count($items) < 1) {
        return ['ok' => false, 'code' => 'empty', 'message' => '该栏目暂无待处理的新闻条目，先去抓取。', 'clusters' => 0];
    }

    // Compact item list for the prompt.
    $lines = [];
    $idIndex = [];
    foreach ($items as $it) {
        $id = (int) $it['id'];
        $idIndex[$id] = $it;
        $summary = mb_substr((string) ($it['summary'] ?? ''), 0, 200, 'UTF-8');
        $lines[] = "[{$id}] " . (string) $it['title'] . ($summary !== '' ? ' — ' . $summary : '');
    }
    $catName = $categorySlug;
    foreach ((function_exists('get_categories') ? get_categories() : []) as $c) {
        if ($c['slug'] === $categorySlug) {
            $catName = (string) $c['name'];
            break;
        }
    }

    $prompt = "你是钱潮 Money Tide「{$catName}」栏目的资深选题编辑。下面是今天抓取到的英文新闻条目（含编号、标题、摘要）。\n\n"
        . implode("\n", $lines)
        . "\n\n任务：\n"
        . "1) 把讲同一件事的条目聚成一个 cluster（去重）。\n"
        . "2) 对每个 cluster 评估它对中文财经读者的价值，给 0-100 的新闻价值分（score）。\n"
        . "3) 为每个 cluster 写一个中文 headline（中性描述这件事）、一个适合钱潮的中文报道 angle、以及一句 why_it_matters（为什么对中文读者重要）。\n"
        . "4) item_ids 必须是上面出现过的编号。\n"
        . "按 score 从高到低排序，最多返回 6 个 cluster。"
        . (function_exists('ai_proper_noun_rule') ? ai_proper_noun_rule() : '')
        . "\n\n严格只返回 JSON：{\"clusters\":[{\"headline\":\"…\",\"angle\":\"…\",\"why_it_matters\":\"…\",\"score\":0-100,\"item_ids\":[编号,…]}]}";

    $response = call_simple_json_api($prompt, ['clusters']);
    log_ai_usage($provider['provider'], $provider['model'], 'cluster-' . $categorySlug, strlen($prompt), $response['ok'] ? 'ok' : 'error', $response['ok'] ? '' : ($response['message'] ?? ''));

    if (!$response['ok']) {
        return ['ok' => false, 'code' => 'ai_error', 'message' => $response['message'] ?? 'AI 调用失败。', 'clusters' => 0];
    }

    $clusters = $response['payload']['clusters'] ?? [];
    if (!is_array($clusters) || !$clusters) {
        return ['ok' => false, 'code' => 'parse', 'message' => 'AI 未返回有效 cluster。', 'clusters' => 0];
    }

    // Clear prior candidate clusters for this category (keep selected/used so manual picks persist).
    try {
        $pdo->prepare("DELETE FROM story_clusters WHERE category_slug = :cat AND status = 'candidate'")
            ->execute(['cat' => $categorySlug]);
    } catch (Throwable $exception) {
    }

    // Normalize + rank.
    $normalized = [];
    foreach ($clusters as $cl) {
        if (!is_array($cl)) {
            continue;
        }
        $headline = trim((string) ($cl['headline'] ?? ''));
        if ($headline === '') {
            continue;
        }
        $ids = [];
        foreach ((array) ($cl['item_ids'] ?? []) as $rawId) {
            $rid = (int) $rawId;
            if (isset($idIndex[$rid])) {
                $ids[] = $rid;
            }
        }
        $primaryUrl = '';
        if ($ids) {
            $primaryUrl = (string) ($idIndex[$ids[0]]['url'] ?? '');
        }
        $score = max(0, min(100, (int) round((float) ($cl['score'] ?? 0))));
        $normalized[] = [
            'headline' => mb_substr($headline, 0, 480, 'UTF-8'),
            'angle' => mb_substr(trim((string) ($cl['angle'] ?? '')), 0, 480, 'UTF-8'),
            'why' => trim((string) ($cl['why_it_matters'] ?? '')),
            'score' => $score,
            'ids' => $ids,
            'primary_url' => $primaryUrl,
        ];
    }
    usort($normalized, static fn ($a, $b): int => $b['score'] <=> $a['score']);

    $inserted = 0;
    $rank = 0;
    $usedItemIds = [];
    try {
        $insert = $pdo->prepare('INSERT INTO story_clusters
            (category_slug, headline, angle, why_it_matters, score, item_ids, item_count, primary_url, status)
            VALUES (:cat, :headline, :angle, :why, :score, :ids, :count, :url, :status)');
        foreach ($normalized as $n) {
            $status = $rank < $topN ? 'selected' : 'candidate';
            $insert->execute([
                'cat' => $categorySlug,
                'headline' => $n['headline'],
                'angle' => $n['angle'] !== '' ? $n['angle'] : null,
                'why' => $n['why'] !== '' ? $n['why'] : null,
                'score' => $n['score'],
                'ids' => json_encode($n['ids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'count' => count($n['ids']),
                'url' => $n['primary_url'] !== '' ? mb_substr($n['primary_url'], 0, 700, 'UTF-8') : null,
                'status' => $status,
            ]);
            $inserted++;
            $rank++;
            foreach ($n['ids'] as $i) {
                $usedItemIds[$i] = true;
            }
        }
        // Mark the clustered news items so we don't re-cluster them next run.
        if ($usedItemIds) {
            $idList = implode(',', array_map('intval', array_keys($usedItemIds)));
            $pdo->exec("UPDATE news_items SET status = 'clustered' WHERE id IN ({$idList}) AND status = 'new'");
        }
    } catch (Throwable $exception) {
        return ['ok' => false, 'code' => 'db', 'message' => '保存 cluster 失败：' . $exception->getMessage(), 'clusters' => $inserted];
    }

    return ['ok' => true, 'code' => 'ok', 'message' => '生成 ' . $inserted . ' 个 cluster（自动选用前 ' . min($topN, $inserted) . ' 个）。', 'clusters' => $inserted];
}

/**
 * Cluster all categories (pipeline + admin "cluster all").
 *
 * Resilience for free-tier AI providers that rate-limit rapid successive calls:
 *  - paces calls with a short gap between categories
 *  - retries transient provider/parse errors with backoff
 *  - skips empty categories silently (not counted as failures)
 *  - stops cleanly when the daily AI quota is exhausted
 *
 * @param int  $topN      auto-selected clusters per category
 * @param int  $pauseSec  gap between categories (rate-limit friendly)
 * @param int  $maxTries  total attempts per category on transient errors
 */
function cluster_all_categories(int $topN = 3, int $pauseSec = 2, int $maxTries = 3): array
{
    ensure_clusters_schema();
    ensure_news_schema();
    $summary = ['categories' => 0, 'ok' => 0, 'failed' => 0, 'skipped' => 0, 'clusters' => 0, 'details' => []];
    $cats = function_exists('get_categories') ? get_categories() : [];
    $total = count($cats);

    foreach ($cats as $index => $c) {
        $slug = (string) $c['slug'];
        $name = (string) $c['name'];

        // Skip categories with no fresh material — silent, not a failure.
        if (count(news_items(['category_slug' => $slug, 'status' => 'new', 'limit' => 1])) === 0) {
            $summary['skipped']++;
            $summary['details'][] = ['category' => $slug, 'name' => $name, 'ok' => false, 'skipped' => true, 'message' => '无待处理素材，已跳过'];
            continue;
        }

        // Stop cleanly when the local daily quota is gone.
        if (!ai_usage_allowed()) {
            $summary['details'][] = ['category' => $slug, 'name' => $name, 'ok' => false, 'message' => 'AI 额度用完，已停止后续栏目'];
            $summary['failed']++;
            $summary['categories']++;
            break;
        }

        // Attempt with retry/backoff on transient errors.
        $attempt = 1;
        $r = ['ok' => false, 'code' => 'unknown', 'message' => '未执行', 'clusters' => 0];
        while ($attempt <= $maxTries) {
            $r = cluster_news_for_category($slug, $topN);
            if ($r['ok']) {
                break;
            }
            $code = (string) ($r['code'] ?? '');
            // Don't retry terminal conditions.
            if (in_array($code, ['quota', 'empty', 'provider', 'db'], true)) {
                break;
            }
            // Transient (ai_error / parse): back off and retry.
            if ($attempt < $maxTries) {
                sleep($attempt * 3);
            }
            $attempt++;
        }

        $summary['categories']++;
        $summary['clusters'] += (int) $r['clusters'];
        if ($r['ok']) {
            $summary['ok']++;
        } else {
            $summary['failed']++;
        }
        $retryNote = ($attempt > 1 && $r['ok']) ? '（重试 ' . ($attempt - 1) . ' 次后成功）' : '';
        $summary['details'][] = ['category' => $slug, 'name' => $name, 'ok' => $r['ok'], 'message' => $r['message'] . $retryNote];

        // If quota ran out mid-retry, stop the rest.
        if (!$r['ok'] && (string) ($r['code'] ?? '') === 'quota') {
            break;
        }

        // Pace before the next category to respect provider rate limits.
        if ($pauseSec > 0 && $index < $total - 1) {
            sleep($pauseSec);
        }
    }

    if (function_exists('record_event')) {
        record_event('news_cluster_run', ['source' => $summary['categories'] . ' cats', 'slug' => 'clusters:' . $summary['clusters']]);
    }
    return $summary;
}

function story_clusters(array $filters = []): array
{
    ensure_clusters_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT * FROM story_clusters WHERE 1 = 1';
    $params = [];
    if (!empty($filters['category_slug'])) {
        $sql .= ' AND category_slug = :cat';
        $params['cat'] = $filters['category_slug'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND status = :status';
        $params['status'] = $filters['status'];
    }
    $sql .= " ORDER BY FIELD(status,'selected','candidate','used','skipped'), score DESC, id DESC LIMIT 200";
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
    foreach ($rows as &$row) {
        $row['item_ids'] = json_decode((string) ($row['item_ids'] ?? '[]'), true) ?: [];
    }
    return $rows;
}

/**
 * Hydrate a cluster's member news items (titles + urls) for display.
 */
function cluster_member_items(array $itemIds): array
{
    $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
    if (!$itemIds) {
        return [];
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    try {
        $in = implode(',', $itemIds);
        return $pdo->query("SELECT id, title, url, source_id, published_at FROM news_items WHERE id IN ({$in})")->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function set_cluster_status(int $id, string $status): bool
{
    if (!array_key_exists($status, cluster_status_labels())) {
        return false;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('UPDATE story_clusters SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function delete_cluster(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM story_clusters WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function clustering_summary(): array
{
    ensure_clusters_schema();
    $pdo = db();
    $out = ['total' => 0, 'selected' => 0, 'candidate' => 0, 'used' => 0, 'today' => 0, 'by_category' => []];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['total'] = (int) $pdo->query('SELECT COUNT(*) FROM story_clusters')->fetchColumn();
        $out['selected'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'selected'")->fetchColumn();
        $out['candidate'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'candidate'")->fetchColumn();
        $out['used'] = (int) $pdo->query("SELECT COUNT(*) FROM story_clusters WHERE status = 'used'")->fetchColumn();
        $out['today'] = (int) $pdo->query('SELECT COUNT(*) FROM story_clusters WHERE created_at >= CURDATE()')->fetchColumn();
        foreach ($pdo->query("SELECT category_slug, COUNT(*) AS n, SUM(status='selected') AS sel FROM story_clusters GROUP BY category_slug")->fetchAll() as $row) {
            $out['by_category'][(string) $row['category_slug']] = ['total' => (int) $row['n'], 'selected' => (int) $row['sel']];
        }
    } catch (Throwable $exception) {
    }
    return $out;
}
