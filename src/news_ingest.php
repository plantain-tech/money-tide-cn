<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·1 — News ingestion foundation.
 *
 * Pulls headlines + summaries from legal RSS/Atom feeds into `news_items`,
 * deduped by URL. This raw material is later CLUSTERED and SYNTHESIZED by the
 * AI into ORIGINAL articles with attribution — we never republish source text.
 *
 * Tables:
 *   news_sources  — configured feeds, one per (category, url)
 *   news_items    — ingested headlines/summaries, deduped by url_hash
 */

function ensure_news_schema(): void
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
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_sources (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(180) NOT NULL,
            feed_url VARCHAR(600) NOT NULL,
            category_slug VARCHAR(40) NOT NULL,
            credibility ENUM('trusted','standard','caution') NOT NULL DEFAULT 'standard',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_fetched_at TIMESTAMP NULL,
            last_status VARCHAR(255) NULL,
            last_item_count INT NOT NULL DEFAULT 0,
            total_items INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_feed_url (feed_url),
            INDEX idx_news_sources_cat (category_slug, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS news_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source_id INT UNSIGNED NOT NULL,
            category_slug VARCHAR(40) NOT NULL,
            title VARCHAR(500) NOT NULL,
            url VARCHAR(700) NOT NULL,
            url_hash CHAR(40) NOT NULL,
            summary TEXT NULL,
            author VARCHAR(200) NULL,
            published_at DATETIME NULL,
            fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('new','clustered','used','ignored') NOT NULL DEFAULT 'new',
            UNIQUE KEY uniq_url_hash (url_hash),
            INDEX idx_news_items_cat (category_slug, status, published_at),
            INDEX idx_news_items_source (source_id, fetched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $ensured = true;
    } catch (Throwable $exception) {
    }
}

/**
 * Default seed feeds — all public RSS/Atom (legal to consume). The owner can
 * edit/add/remove these in /admin/news-sources. URLs that 404 simply record an
 * error on fetch; they never break the pipeline.
 */
function default_news_sources(): array
{
    return [
        // markets
        ['name' => 'CNBC · Markets', 'feed_url' => 'https://www.cnbc.com/id/100003114/device/rss/rss.html', 'category_slug' => 'markets', 'credibility' => 'trusted'],
        ['name' => 'MarketWatch · Top Stories', 'feed_url' => 'http://feeds.marketwatch.com/marketwatch/topstories/', 'category_slug' => 'markets', 'credibility' => 'trusted'],
        ['name' => 'Yahoo Finance', 'feed_url' => 'https://finance.yahoo.com/news/rssindex', 'category_slug' => 'markets', 'credibility' => 'standard'],
        // business
        ['name' => 'CNN · Business', 'feed_url' => 'http://rss.cnn.com/rss/money_latest.rss', 'category_slug' => 'business', 'credibility' => 'standard'],
        ['name' => 'Fox Business · Markets', 'feed_url' => 'https://moxie.foxbusiness.com/google-publisher/markets.xml', 'category_slug' => 'business', 'credibility' => 'standard'],
        // tech
        ['name' => 'TechCrunch', 'feed_url' => 'https://techcrunch.com/feed/', 'category_slug' => 'tech', 'credibility' => 'trusted'],
        ['name' => 'The Verge', 'feed_url' => 'https://www.theverge.com/rss/index.xml', 'category_slug' => 'tech', 'credibility' => 'standard'],
        ['name' => 'Yahoo Finance · 科技 (NVDA)', 'feed_url' => 'https://feeds.finance.yahoo.com/rss/2.0/headline?s=NVDA&region=US&lang=en-US', 'category_slug' => 'tech', 'credibility' => 'standard'],
        // crypto
        ['name' => 'CoinDesk', 'feed_url' => 'https://www.coindesk.com/arc/outboundfeeds/rss/', 'category_slug' => 'crypto', 'credibility' => 'trusted'],
        ['name' => 'Cointelegraph', 'feed_url' => 'https://cointelegraph.com/rss', 'category_slug' => 'crypto', 'credibility' => 'standard'],
        // policy
        ['name' => 'Federal Reserve · Press', 'feed_url' => 'https://www.federalreserve.gov/feeds/press_all.xml', 'category_slug' => 'policy', 'credibility' => 'trusted'],
        // world
        ['name' => 'BBC · Business', 'feed_url' => 'https://feeds.bbci.co.uk/news/business/rss.xml', 'category_slug' => 'world', 'credibility' => 'trusted'],
        // wealth
        ['name' => 'Investopedia', 'feed_url' => 'https://www.investopedia.com/feedbuilder/feed/getfeed?feedName=rss_articles', 'category_slug' => 'wealth', 'credibility' => 'standard'],
        ['name' => 'Seeking Alpha', 'feed_url' => 'https://seekingalpha.com/feed.php', 'category_slug' => 'wealth', 'credibility' => 'standard'],
        // global-china
        ['name' => 'SCMP · Business', 'feed_url' => 'https://www.scmp.com/rss/92/feed', 'category_slug' => 'global-china', 'credibility' => 'standard'],
    ];
}

/**
 * Seed default sources only when the table is empty. Idempotent.
 */
function seed_news_sources(): int
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $existing = (int) $pdo->query('SELECT COUNT(*) FROM news_sources')->fetchColumn();
        if ($existing > 0) {
            return 0;
        }
        $inserted = 0;
        $statement = $pdo->prepare('INSERT IGNORE INTO news_sources (name, feed_url, category_slug, credibility)
            VALUES (:name, :url, :cat, :cred)');
        foreach (default_news_sources() as $src) {
            $statement->execute([
                'name' => $src['name'],
                'url' => $src['feed_url'],
                'cat' => $src['category_slug'],
                'cred' => $src['credibility'],
            ]);
            $inserted += $statement->rowCount();
        }
        return $inserted;
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Add any missing default sources (idempotent via unique feed_url). Unlike
 * seed_news_sources() this runs even when the table is non-empty, so the owner
 * can top up with newly-added defaults (e.g. Seeking Alpha). Returns count added.
 * Only invoked on explicit admin action — never auto-runs, so a source the owner
 * deleted won't silently reappear during ingestion.
 */
function topup_default_news_sources(): int
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $inserted = 0;
        $statement = $pdo->prepare('INSERT IGNORE INTO news_sources (name, feed_url, category_slug, credibility)
            VALUES (:name, :url, :cat, :cred)');
        foreach (default_news_sources() as $src) {
            $statement->execute([
                'name' => $src['name'],
                'url' => $src['feed_url'],
                'cat' => $src['category_slug'],
                'cred' => $src['credibility'],
            ]);
            $inserted += $statement->rowCount();
        }
        return $inserted;
    } catch (Throwable $exception) {
        return 0;
    }
}

function news_credibility_options(): array
{
    return ['trusted' => '可信', 'standard' => '常规', 'caution' => '谨慎'];
}

function news_sources(array $filters = []): array
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT * FROM news_sources WHERE 1 = 1';
    $params = [];
    if (!empty($filters['category_slug'])) {
        $sql .= ' AND category_slug = :cat';
        $params['cat'] = $filters['category_slug'];
    }
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $sql .= ' AND is_active = :active';
        $params['active'] = (int) $filters['is_active'];
    }
    $sql .= " ORDER BY category_slug ASC, FIELD(credibility,'trusted','standard','caution'), name ASC LIMIT 300";
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function news_source_by_id(int $id): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }
    try {
        $statement = $pdo->prepare('SELECT * FROM news_sources WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        return $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function save_news_source(array $input, ?int $id = null): array
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }
    $name = trim((string) ($input['name'] ?? ''));
    $url = trim((string) ($input['feed_url'] ?? ''));
    $cat = trim((string) ($input['category_slug'] ?? ''));
    $cred = (string) ($input['credibility'] ?? 'standard');
    $active = !empty($input['is_active']) ? 1 : 0;
    if (!array_key_exists($cred, news_credibility_options())) {
        $cred = 'standard';
    }
    $validCats = array_column(function_exists('get_categories') ? get_categories() : [], 'slug');
    $errors = [];
    if ($name === '') {
        $errors[] = '名称不能为空。';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Feed URL 无效。';
    }
    if ($cat === '' || ($validCats && !in_array($cat, $validCats, true))) {
        $errors[] = '请选择有效栏目。';
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }
    try {
        if ($id === null) {
            $statement = $pdo->prepare('INSERT INTO news_sources (name, feed_url, category_slug, credibility, is_active)
                VALUES (:name, :url, :cat, :cred, :active)');
            $statement->execute(['name' => $name, 'url' => $url, 'cat' => $cat, 'cred' => $cred, 'active' => $active]);
            return ['ok' => true, 'id' => (int) $pdo->lastInsertId()];
        }
        $statement = $pdo->prepare('UPDATE news_sources SET name = :name, feed_url = :url, category_slug = :cat, credibility = :cred, is_active = :active WHERE id = :id');
        $statement->execute(['name' => $name, 'url' => $url, 'cat' => $cat, 'cred' => $cred, 'active' => $active, 'id' => $id]);
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：' . $exception->getMessage()]];
    }
}

function delete_news_source(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('DELETE FROM news_sources WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

function toggle_news_source(int $id): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }
    try {
        return $pdo->prepare('UPDATE news_sources SET is_active = 1 - is_active WHERE id = :id')->execute(['id' => $id]);
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * Fetch + parse one RSS/Atom feed. Returns a normalized item list (no DB write).
 */
function fetch_rss_feed(string $url): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'message' => 'cURL not available', 'items' => []];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_USERAGENT => 'MoneyTideNewsBot/1.0 (+https://moneytidecn.avanturadeals.com)',
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => '',
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($raw === false || $raw === '' || $status >= 400) {
        return ['ok' => false, 'message' => 'HTTP ' . ($status ?: '?') . ($error ? ' · ' . $error : ''), 'items' => []];
    }

    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string((string) $raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        return ['ok' => false, 'message' => 'XML 解析失败', 'items' => []];
    }

    $items = [];
    $namespaces = $xml->getNamespaces(true);

    // RSS 2.0 / RDF
    if (isset($xml->channel) && isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $items[] = news_normalize_rss_item($item, $namespaces);
        }
    } elseif (isset($xml->item)) { // RSS 1.0 (RDF) — items at root
        foreach ($xml->item as $item) {
            $items[] = news_normalize_rss_item($item, $namespaces);
        }
    } elseif (isset($xml->entry)) { // Atom
        foreach ($xml->entry as $entry) {
            $items[] = news_normalize_atom_entry($entry, $namespaces);
        }
    }

    $items = array_values(array_filter($items, static fn ($i): bool => $i !== null && $i['url'] !== '' && $i['title'] !== ''));
    return ['ok' => true, 'message' => 'ok', 'items' => $items];
}

function news_normalize_rss_item(SimpleXMLElement $item, array $namespaces): ?array
{
    $title = trim((string) ($item->title ?? ''));
    $link = trim((string) ($item->link ?? ''));
    if ($link === '' && isset($item->guid)) {
        $g = trim((string) $item->guid);
        if (filter_var($g, FILTER_VALIDATE_URL)) {
            $link = $g;
        }
    }
    $desc = (string) ($item->description ?? '');
    $author = trim((string) ($item->author ?? ''));
    if ($author === '' && isset($namespaces['dc'])) {
        $dc = $item->children($namespaces['dc']);
        $author = trim((string) ($dc->creator ?? ''));
    }
    $pub = trim((string) ($item->pubDate ?? ''));
    if ($pub === '' && isset($namespaces['dc'])) {
        $dc = $item->children($namespaces['dc']);
        $pub = trim((string) ($dc->date ?? ''));
    }
    return [
        'title' => mb_substr($title, 0, 480, 'UTF-8'),
        'url' => $link,
        'summary' => news_clean_summary($desc),
        'author' => mb_substr($author, 0, 180, 'UTF-8'),
        'published_at' => news_parse_date($pub),
    ];
}

function news_normalize_atom_entry(SimpleXMLElement $entry, array $namespaces): ?array
{
    $title = trim((string) ($entry->title ?? ''));
    $link = '';
    if (isset($entry->link)) {
        foreach ($entry->link as $l) {
            $rel = (string) $l['rel'];
            $href = (string) $l['href'];
            if ($href !== '' && ($rel === '' || $rel === 'alternate')) {
                $link = $href;
                break;
            }
            if ($href !== '' && $link === '') {
                $link = $href;
            }
        }
    }
    $summary = (string) ($entry->summary ?? $entry->content ?? '');
    $author = trim((string) ($entry->author->name ?? ''));
    $pub = trim((string) ($entry->published ?? $entry->updated ?? ''));
    return [
        'title' => mb_substr($title, 0, 480, 'UTF-8'),
        'url' => trim($link),
        'summary' => news_clean_summary($summary),
        'author' => mb_substr($author, 0, 180, 'UTF-8'),
        'published_at' => news_parse_date($pub),
    ];
}

function news_clean_summary(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    return mb_substr($text, 0, 600, 'UTF-8');
}

function news_parse_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function news_url_hash(string $url): string
{
    // Normalize: drop fragment + common tracking params for dedup.
    $url = trim($url);
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return sha1($url);
    }
    $scheme = strtolower($parts['scheme'] ?? 'https');
    $host = strtolower($parts['host']);
    $path = rtrim($parts['path'] ?? '', '/');
    $query = '';
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $q);
        foreach (array_keys($q) as $k) {
            if (stripos($k, 'utm_') === 0 || in_array(strtolower($k), ['fbclid', 'gclid', 'ref', 'cmpid'], true)) {
                unset($q[$k]);
            }
        }
        ksort($q);
        $query = http_build_query($q);
    }
    return sha1($scheme . '://' . $host . $path . ($query !== '' ? '?' . $query : ''));
}

/**
 * Fetch one source and insert new items. Updates source stats. Returns summary.
 */
function ingest_news_source(array $source): array
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'new' => 0, 'message' => '数据库未连接。'];
    }
    $result = fetch_rss_feed((string) $source['feed_url']);
    $new = 0;
    $seen = count($result['items']);

    if ($result['ok'] && $seen > 0) {
        try {
            $insert = $pdo->prepare('INSERT IGNORE INTO news_items
                (source_id, category_slug, title, url, url_hash, summary, author, published_at)
                VALUES (:sid, :cat, :title, :url, :hash, :summary, :author, :pub)');
            foreach ($result['items'] as $item) {
                $insert->execute([
                    'sid' => (int) $source['id'],
                    'cat' => (string) $source['category_slug'],
                    'title' => $item['title'],
                    'url' => mb_substr($item['url'], 0, 700, 'UTF-8'),
                    'hash' => news_url_hash($item['url']),
                    'summary' => $item['summary'] !== '' ? $item['summary'] : null,
                    'author' => $item['author'] !== '' ? $item['author'] : null,
                    'pub' => $item['published_at'],
                ]);
                $new += $insert->rowCount();
            }
        } catch (Throwable $exception) {
            $result['ok'] = false;
            $result['message'] = '写入失败：' . $exception->getMessage();
        }
    }

    $statusText = $result['ok'] ? ('成功 · ' . $seen . ' 条 · 新增 ' . $new) : ('失败 · ' . $result['message']);
    try {
        $pdo->prepare('UPDATE news_sources SET last_fetched_at = NOW(), last_status = :st, last_item_count = :seen, total_items = total_items + :new WHERE id = :id')
            ->execute(['st' => mb_substr($statusText, 0, 250, 'UTF-8'), 'seen' => $seen, 'new' => $new, 'id' => (int) $source['id']]);
    } catch (Throwable $exception) {
    }

    return ['ok' => $result['ok'], 'new' => $new, 'seen' => $seen, 'message' => $statusText];
}

/**
 * Fetch all active sources (or a single category). Pipeline + CLI entrypoint.
 */
function ingest_all_news_sources(?string $categorySlug = null): array
{
    ensure_news_schema();
    seed_news_sources();
    $filters = ['is_active' => 1];
    if ($categorySlug !== null && $categorySlug !== '') {
        $filters['category_slug'] = $categorySlug;
    }
    $sources = news_sources($filters);
    $summary = ['sources' => 0, 'ok' => 0, 'failed' => 0, 'new_items' => 0, 'seen' => 0, 'details' => []];
    foreach ($sources as $source) {
        $r = ingest_news_source($source);
        $summary['sources']++;
        $summary['new_items'] += (int) $r['new'];
        $summary['seen'] += (int) ($r['seen'] ?? 0);
        $r['ok'] ? $summary['ok']++ : $summary['failed']++;
        $summary['details'][] = [
            'name' => $source['name'],
            'category' => $source['category_slug'],
            'ok' => $r['ok'],
            'new' => (int) $r['new'],
            'message' => $r['message'],
        ];
    }
    if (function_exists('record_event')) {
        record_event('news_ingest_run', ['source' => $summary['sources'] . ' sources', 'slug' => 'new:' . $summary['new_items']]);
    }
    return $summary;
}

function news_items(array $filters = []): array
{
    ensure_news_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }
    $sql = 'SELECT i.*, s.name AS source_name FROM news_items i
            LEFT JOIN news_sources s ON s.id = i.source_id WHERE 1 = 1';
    $params = [];
    if (!empty($filters['category_slug'])) {
        $sql .= ' AND i.category_slug = :cat';
        $params['cat'] = $filters['category_slug'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND i.status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['q'])) {
        $sql .= ' AND (i.title LIKE :q1 OR i.summary LIKE :q2)';
        $params['q1'] = '%' . $filters['q'] . '%';
        $params['q2'] = '%' . $filters['q'] . '%';
    }
    $limit = (int) ($filters['limit'] ?? 100);
    $limit = max(1, min(300, $limit));
    $sql .= ' ORDER BY COALESCE(i.published_at, i.fetched_at) DESC, i.id DESC LIMIT ' . $limit;
    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

function news_ingest_summary(): array
{
    ensure_news_schema();
    $pdo = db();
    $out = [
        'sources_total' => 0,
        'sources_active' => 0,
        'items_total' => 0,
        'items_new' => 0,
        'items_today' => 0,
        'last_fetched_at' => null,
        'by_category' => [],
    ];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['sources_total'] = (int) $pdo->query('SELECT COUNT(*) FROM news_sources')->fetchColumn();
        $out['sources_active'] = (int) $pdo->query('SELECT COUNT(*) FROM news_sources WHERE is_active = 1')->fetchColumn();
        $out['items_total'] = (int) $pdo->query('SELECT COUNT(*) FROM news_items')->fetchColumn();
        $out['items_new'] = (int) $pdo->query("SELECT COUNT(*) FROM news_items WHERE status = 'new'")->fetchColumn();
        $out['items_today'] = (int) $pdo->query('SELECT COUNT(*) FROM news_items WHERE fetched_at >= CURDATE()')->fetchColumn();
        $out['last_fetched_at'] = $pdo->query('SELECT MAX(last_fetched_at) FROM news_sources')->fetchColumn() ?: null;
        foreach ($pdo->query("SELECT category_slug, COUNT(*) AS n, SUM(status='new') AS fresh FROM news_items GROUP BY category_slug")->fetchAll() as $row) {
            $out['by_category'][(string) $row['category_slug']] = ['total' => (int) $row['n'], 'new' => (int) $row['fresh']];
        }
    } catch (Throwable $exception) {
    }
    return $out;
}

/**
 * Housekeeping: prune ingested items older than N days to keep the table lean.
 */
function prune_old_news_items(int $days = 30): int
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return 0;
    }
    try {
        $statement = $pdo->prepare("DELETE FROM news_items WHERE status IN ('used','ignored') AND fetched_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $statement->bindValue('days', max(1, $days), PDO::PARAM_INT);
        $statement->execute();
        return $statement->rowCount();
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Freshness sweep — keeps the platform from rotting into a stale backlog.
 * News moves fast, so unprocessed material/topics that age out are dead weight.
 * TTLs are configurable via pipeline_settings (sane defaults below). Returns a
 * per-bucket count of what was pruned. Safe to run every pipeline cycle.
 *
 *   news_ttl_days     (default 10) — unprocessed/clustered raw items older than this
 *   cluster_ttl_days  (default 4)  — candidate/selected topics never written in time
 *   draft_ttl_days    (default 7)  — drafts stuck in human review, auto-retired
 */
function pipeline_cleanup(): array
{
    $out = ['news' => 0, 'clusters' => 0, 'drafts' => 0];
    $pdo = function_exists('db') ? db() : null;
    if (!$pdo instanceof PDO) {
        return $out;
    }
    $ttl = static function (string $key, int $def, int $min, int $max): int {
        $v = function_exists('pipeline_setting') ? (int) pipeline_setting($key, (string) $def) : $def;
        return max($min, min($max, $v > 0 ? $v : $def));
    };
    $newsTtl = $ttl('news_ttl_days', 10, 2, 60);
    $clusterTtl = $ttl('cluster_ttl_days', 4, 1, 30);
    $draftTtl = $ttl('draft_ttl_days', 7, 1, 60);

    try {
        // 1) Stale raw news: unprocessed 'new'/'clustered' past the news TTL, plus
        //    consumed 'used'/'ignored' past 30 days. These will never be written → drop.
        $s = $pdo->prepare("DELETE FROM news_items
            WHERE (status IN ('new','clustered') AND fetched_at < DATE_SUB(NOW(), INTERVAL :d DAY))
               OR (status IN ('used','ignored') AND fetched_at < DATE_SUB(NOW(), INTERVAL 30 DAY))");
        $s->bindValue('d', $newsTtl, PDO::PARAM_INT);
        $s->execute();
        $out['news'] = $s->rowCount();
    } catch (Throwable $exception) {
    }
    try {
        // 2) Stale topics: candidate/selected clusters never synthesized in time, plus
        //    old used/skipped clusters past 30 days.
        $s = $pdo->prepare("DELETE FROM story_clusters
            WHERE (status IN ('candidate','selected') AND created_at < DATE_SUB(NOW(), INTERVAL :d DAY))
               OR (status IN ('used','skipped') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY))");
        $s->bindValue('d', $clusterTtl, PDO::PARAM_INT);
        $s->execute();
        $out['clusters'] = $s->rowCount();
    } catch (Throwable $exception) {
    }
    try {
        // 3) Stale review drafts: auto-retire drafts stuck in the human queue past the
        //    TTL so they don't pile up as garbage. Mark rejected + close their review row.
        $ids = $pdo->prepare("SELECT id FROM ai_drafts WHERE status = 'needs_review' AND created_at < DATE_SUB(NOW(), INTERVAL :d DAY)");
        $ids->bindValue('d', $draftTtl, PDO::PARAM_INT);
        $ids->execute();
        $list = array_map('intval', array_column($ids->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id'));
        if ($list) {
            $in = implode(',', $list);
            $pdo->exec("UPDATE ai_drafts SET status = 'rejected' WHERE id IN ({$in})");
            // Close their pending auto-review rows so they leave the review queue/count.
            try {
                $pdo->exec("UPDATE auto_reviews SET decision = 'rejected', decided_by = 'auto_ttl', decided_at = NOW()
                    WHERE draft_id IN ({$in}) AND decision = 'pending'");
            } catch (Throwable $inner) {
            }
            $out['drafts'] = count($list);
        }
    } catch (Throwable $exception) {
    }

    if (($out['news'] + $out['clusters'] + $out['drafts']) > 0 && function_exists('set_pipeline_setting')) {
        set_pipeline_setting('last_cleanup_at', date('Y-m-d H:i:s'));
        set_pipeline_setting('last_cleanup_summary', '素材 ' . $out['news'] . ' · 选题 ' . $out['clusters'] . ' · 草稿 ' . $out['drafts']);
    }
    return $out;
}
