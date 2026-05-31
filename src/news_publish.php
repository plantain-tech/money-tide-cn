<?php

declare(strict_types=1);

/**
 * Sprint 1 · Day 9·5 — Auto-publish + assemble daily category newsletters.
 *
 * Approved AI drafts (status 'approved' from the D9·4 gate) become PUBLISHED
 * articles; then the day's published articles are auto-assembled into one
 * newsletter issue PER CATEGORY (status 'ready'). Assembling is NOT sending —
 * broadcast stays a separate manual/Sprint-2 step (no auto-send here).
 */

function ensure_auto_publish_schema(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    // Link an ai_draft to the article it became (idempotent).
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM ai_drafts LIKE 'article_id'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE ai_drafts ADD COLUMN article_id INT UNSIGNED NULL AFTER status');
        }
    } catch (Throwable $exception) {
    }
    // Tag a newsletter issue with its category + auto flag (idempotent).
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM newsletter_issues LIKE 'category_slug'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE newsletter_issues ADD COLUMN category_slug VARCHAR(40) NULL');
            $pdo->exec('ALTER TABLE newsletter_issues ADD COLUMN auto_generated TINYINT(1) NOT NULL DEFAULT 0');
        }
    } catch (Throwable $exception) {
    }
    $ensured = true;
}

function estimate_read_time(string $body): int
{
    $chars = mb_strlen(preg_replace('/\s+/u', '', $body) ?? $body, 'UTF-8');
    return max(1, (int) round($chars / 400)); // ~400 zh chars/min
}

/**
 * Publish one approved draft as a real article. Idempotent via draft status.
 */
function publish_one_approved_draft(int $draftId): array
{
    ensure_auto_publish_schema();
    $draft = ai_draft_by_id($draftId);
    if (!$draft) {
        return ['ok' => false, 'code' => 'missing', 'message' => '草稿不存在。'];
    }
    if ((string) $draft['status'] === 'converted') {
        return ['ok' => true, 'code' => 'exists', 'article_id' => (int) ($draft['article_id'] ?? 0), 'message' => '该草稿已发布。'];
    }
    $categoryId = category_id_by_slug((string) $draft['section_slug']);
    if ($categoryId <= 0) {
        return ['ok' => false, 'code' => 'category', 'message' => '找不到对应栏目。'];
    }

    $payload = is_array($draft['draft_payload'] ?? null) ? $draft['draft_payload'] : [];
    $body = $payload['body'] ?? [];
    if (!is_array($body)) {
        $body = [(string) $body];
    }
    $bodyText = implode("\n\n", array_map('strval', $body));
    $title = (string) ($payload['title'] ?? 'AI 草稿');

    $result = save_article([
        'category_id' => $categoryId,
        'status' => 'published',
        'title' => $title,
        'slug' => unique_article_slug(slugify($title) ?: ('ai-' . $draftId)),
        'dek' => (string) ($payload['dek'] ?? ''),
        'brief' => (string) ($payload['brief'] ?? ''),
        'why_it_matters' => (string) ($payload['why_it_matters'] ?? ''),
        'body' => $bodyText,
        'hero_image_alt' => mb_substr($title, 0, 120, 'UTF-8'),
        'read_time_minutes' => estimate_read_time($bodyText),
        'published_at' => date('Y-m-d H:i:s'),
    ]);

    if (empty($result['ok'])) {
        return ['ok' => false, 'code' => 'save', 'message' => implode(' ', (array) ($result['errors'] ?? ['发布失败。']))];
    }

    $articleId = (int) $result['id'];
    $pdo = db();
    if ($pdo instanceof PDO) {
        try {
            $pdo->prepare("UPDATE ai_drafts SET status = 'converted', article_id = :a WHERE id = :id")
                ->execute(['a' => $articleId, 'id' => $draftId]);
        } catch (Throwable $exception) {
        }
    }
    if (function_exists('record_event')) {
        record_event('draft_published', ['slug' => (string) $draft['section_slug'], 'source' => 'article:' . $articleId]);
    }
    return ['ok' => true, 'code' => 'ok', 'article_id' => $articleId, 'title' => $title, 'message' => '已发布文章 #' . $articleId];
}

function publish_approved_drafts(int $limit = 12): array
{
    ensure_auto_publish_schema();
    $pdo = db();
    $summary = ['total' => 0, 'ok' => 0, 'failed' => 0, 'articles' => [], 'details' => []];
    if (!$pdo instanceof PDO) {
        return $summary;
    }
    try {
        $rows = $pdo->query("SELECT id FROM ai_drafts WHERE status = 'approved' ORDER BY id ASC LIMIT " . max(1, min(50, $limit)))->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return $summary;
    }
    foreach ($rows as $row) {
        $r = publish_one_approved_draft((int) $row['id']);
        $summary['total']++;
        if ($r['ok']) {
            $summary['ok']++;
            if (!empty($r['article_id'])) {
                $summary['articles'][] = (int) $r['article_id'];
            }
        } else {
            $summary['failed']++;
        }
        $summary['details'][] = ['draft_id' => (int) $row['id'], 'ok' => $r['ok'], 'message' => $r['message']];
    }
    return $summary;
}

/**
 * Assemble (or refresh) one category's daily newsletter from articles published
 * on $date. Deterministic slug => idempotent (re-runs update the same issue).
 */
function assemble_category_newsletter(string $categorySlug, ?string $date = null): array
{
    ensure_newsletter_issues_schema();
    ensure_auto_publish_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'message' => '数据库未连接。', 'articles' => 0];
    }
    $date = $date ?: date('Y-m-d');
    $categoryId = category_id_by_slug($categorySlug);
    if ($categoryId <= 0) {
        return ['ok' => false, 'message' => '未知栏目。', 'articles' => 0];
    }
    $catName = $categorySlug;
    foreach ((function_exists('get_categories') ? get_categories() : []) as $c) {
        if ($c['slug'] === $categorySlug) {
            $catName = (string) $c['name'];
            break;
        }
    }

    // Today's published articles in this category.
    try {
        $st = $pdo->prepare("SELECT id, title, brief, dek FROM articles
            WHERE status = 'published' AND category_id = :cid AND DATE(published_at) = :d
            ORDER BY published_at ASC, id ASC");
        $st->execute(['cid' => $categoryId, 'd' => $date]);
        $articles = $st->fetchAll() ?: [];
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '查询文章失败：' . $exception->getMessage(), 'articles' => 0];
    }
    if (!$articles) {
        return ['ok' => false, 'code' => 'empty', 'message' => '今日无已发布文章。', 'articles' => 0];
    }

    $slug = 'daily-' . $categorySlug . '-' . str_replace('-', '', $date);
    $subject = '钱潮·' . $catName . '早报 · ' . $date;
    $intro = '今天「' . $catName . '」栏目的 AI 精选，' . count($articles) . ' 条值得读的信号。';
    $outro = '本文内容由 AI 协助整理，仅供参考，不构成投资建议。';

    // Find existing auto issue for this category+date, else create.
    $issueId = 0;
    try {
        $f = $pdo->prepare('SELECT id FROM newsletter_issues WHERE slug = :slug LIMIT 1');
        $f->execute(['slug' => $slug]);
        $issueId = (int) ($f->fetchColumn() ?: 0);
    } catch (Throwable $exception) {
    }

    try {
        if ($issueId <= 0) {
            $ins = $pdo->prepare("INSERT INTO newsletter_issues (slug, subject, intro, outro, scheduled_at, status, category_slug, auto_generated)
                VALUES (:slug, :subject, :intro, :outro, :sched, 'ready', :cat, 1)");
            $ins->execute([
                'slug' => $slug, 'subject' => $subject, 'intro' => $intro, 'outro' => $outro,
                'sched' => $date . ' 08:00:00', 'cat' => $categorySlug,
            ]);
            $issueId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare("UPDATE newsletter_issues SET subject = :subject, intro = :intro, outro = :outro, category_slug = :cat, auto_generated = 1 WHERE id = :id")
                ->execute(['subject' => $subject, 'intro' => $intro, 'outro' => $outro, 'cat' => $categorySlug, 'id' => $issueId]);
            // Re-sync articles.
            $pdo->prepare('DELETE FROM newsletter_issue_articles WHERE issue_id = :id')->execute(['id' => $issueId]);
        }
    } catch (Throwable $exception) {
        return ['ok' => false, 'message' => '创建早报失败：' . $exception->getMessage(), 'articles' => 0];
    }

    foreach ($articles as $a) {
        add_article_to_issue($issueId, (int) $a['id'], (string) ($a['brief'] ?? $a['dek'] ?? ''));
    }

    return ['ok' => true, 'code' => 'ok', 'issue_id' => $issueId, 'articles' => count($articles), 'category' => $catName, 'message' => $catName . '：' . count($articles) . ' 篇 → 早报 #' . $issueId];
}

function assemble_daily_newsletters(?string $date = null): array
{
    ensure_auto_publish_schema();
    $summary = ['categories' => 0, 'issues' => 0, 'skipped' => 0, 'details' => []];
    foreach ((function_exists('get_categories') ? get_categories() : []) as $c) {
        $slug = (string) $c['slug'];
        $r = assemble_category_newsletter($slug, $date);
        $summary['categories']++;
        if ($r['ok']) {
            $summary['issues']++;
            $summary['details'][] = ['category' => (string) $c['name'], 'ok' => true, 'issue_id' => (int) ($r['issue_id'] ?? 0), 'message' => $r['message']];
        } else {
            $summary['skipped']++;
            $summary['details'][] = ['category' => (string) $c['name'], 'ok' => false, 'message' => $r['message']];
        }
    }
    return $summary;
}

/**
 * One run = publish all approved drafts, then assemble every category's daily
 * newsletter. This is the D9·5 orchestration (and the cron entry for D9·6).
 */
function run_auto_publish_and_assemble(int $publishLimit = 12, ?string $date = null): array
{
    $publish = publish_approved_drafts($publishLimit);
    $assemble = assemble_daily_newsletters($date);
    if (function_exists('record_event')) {
        record_event('auto_publish_run', ['source' => $publish['ok'] . ' published / ' . $assemble['issues'] . ' issues']);
    }
    return ['publish' => $publish, 'assemble' => $assemble];
}

function auto_publish_summary(): array
{
    ensure_auto_publish_schema();
    $pdo = db();
    $out = ['approved_pending' => 0, 'published_today' => 0, 'issues_today' => 0, 'total_published' => 0];
    if (!$pdo instanceof PDO) {
        return $out;
    }
    try {
        $out['approved_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM ai_drafts WHERE status = 'approved'")->fetchColumn();
        $out['published_today'] = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published' AND DATE(published_at) = CURDATE()")->fetchColumn();
        $out['issues_today'] = (int) $pdo->query("SELECT COUNT(*) FROM newsletter_issues WHERE auto_generated = 1 AND slug LIKE CONCAT('daily-%-', DATE_FORMAT(CURDATE(), '%Y%m%d'))")->fetchColumn();
        $out['total_published'] = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE status = 'published'")->fetchColumn();
    } catch (Throwable $exception) {
    }
    return $out;
}
