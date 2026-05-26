<?php

declare(strict_types=1);

function db_categories(): ?array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $rows = $pdo->query('SELECT slug, name, summary FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
        return $rows ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function ensure_editorial_schema(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS authors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            bio TEXT NULL,
            avatar_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        foreach ([
            'author_id' => 'INT UNSIGNED NULL',
            'editor_id' => 'INT UNSIGNED NULL',
            'created_by_user_id' => 'INT UNSIGNED NULL',
            'updated_by_user_id' => 'INT UNSIGNED NULL',
            'seo_title' => 'VARCHAR(255) NULL',
            'seo_description' => 'VARCHAR(500) NULL',
            'hero_image_path' => 'VARCHAR(255) NULL',
            'hero_image_alt' => 'VARCHAR(255) NULL',
        ] as $column => $definition) {
            if (!db_column_exists('articles', $column)) {
                $pdo->exec('ALTER TABLE articles ADD COLUMN ' . $column . ' ' . $definition);
            }
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS article_audit_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            article_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            actor_email VARCHAR(255) NULL,
            action VARCHAR(80) NOT NULL,
            from_status VARCHAR(40) NULL,
            to_status VARCHAR(40) NULL,
            note VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_article_audit_article (article_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $statement = $pdo->prepare('INSERT IGNORE INTO authors (name, slug, bio) VALUES (:name, :slug, :bio)');
        $statement->execute([
            'name' => '钱潮编辑部',
            'slug' => 'money-tide-editors',
            'bio' => '钱潮 Money Tide 编辑团队。',
        ]);
    } catch (Throwable $exception) {
    }
}

function db_column_exists(string $table, string $column): bool
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return false;
    }

    try {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $statement->execute(['table' => $table, 'column' => $column]);
        return (int) $statement->fetchColumn() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function default_author_id(): ?int
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT id FROM authors WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => 'money-tide-editors']);
        $id = $statement->fetchColumn();
        return $id ? (int) $id : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function admin_authors(): array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        return $pdo->query('SELECT id, name, slug FROM authors ORDER BY name ASC')->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function admin_editors(): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        return $pdo->query("SELECT id, email, display_name, role FROM users WHERE role IN ('editor', 'admin') ORDER BY role DESC, display_name ASC, email ASC")->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function article_audit_logs(int $articleId): array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        $statement = $pdo->prepare('SELECT actor_email, action, from_status, to_status, note, created_at FROM article_audit_logs WHERE article_id = :id ORDER BY created_at DESC, id DESC LIMIT 20');
        $statement->execute(['id' => $articleId]);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function record_article_audit(int $articleId, string $action, ?string $fromStatus = null, ?string $toStatus = null, string $note = ''): void
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }

    $user = current_user();
    try {
        $statement = $pdo->prepare('INSERT INTO article_audit_logs (article_id, actor_user_id, actor_email, action, from_status, to_status, note)
            VALUES (:article_id, :actor_user_id, :actor_email, :action, :from_status, :to_status, :note)');
        $statement->execute([
            'article_id' => $articleId,
            'actor_user_id' => is_array($user) && (int) ($user['id'] ?? 0) > 0 ? (int) $user['id'] : null,
            'actor_email' => is_array($user) ? (string) ($user['email'] ?? '') : '',
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    } catch (Throwable $exception) {
    }
}

function article_media_url(array $article): string
{
    $image = trim((string) ($article['hero_image_path'] ?? ''));
    if ($image !== '') {
        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }
        return canonical_url(ltrim($image, '/'));
    }

    return category_fallback_image((string) ($article['category'] ?? $article['category_slug'] ?? ''));
}

function category_fallback_image(string $categorySlug): string
{
    $map = [
        'markets' => 'assets/img/hero-markets.svg',
        'business' => 'assets/img/hero-business.svg',
        'tech' => 'assets/img/hero-tech.svg',
        'crypto' => 'assets/img/hero-crypto.svg',
        'policy' => 'assets/img/hero-policy.svg',
        'global-china' => 'assets/img/hero-global.svg',
        'wealth' => 'assets/img/hero-wealth.svg',
    ];

    return canonical_url($map[$categorySlug] ?? 'assets/img/og-money-tide.svg');
}

function db_articles(?string $categorySlug = null): ?array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    $sql = "SELECT a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.read_time_minutes, a.published_at, a.seo_title, a.seo_description,
                a.hero_image_path, a.hero_image_alt, c.slug AS category, c.name AS category_name,
                au.name AS author_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN authors au ON au.id = a.author_id
            WHERE a.status = 'published'";
    $params = [];

    if ($categorySlug !== null) {
        $sql .= ' AND c.slug = :category';
        $params['category'] = $categorySlug;
    }

    $sql .= ' ORDER BY a.published_at DESC, a.id DESC LIMIT 50';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        if (!$rows) {
            return null;
        }

        return array_map('map_article_row', $rows);
    } catch (Throwable $exception) {
        return null;
    }
}

function db_article(string $slug): ?array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare("SELECT a.slug, a.title, a.dek, a.brief, a.why_it_matters, a.body,
                a.read_time_minutes, a.published_at, a.seo_title, a.seo_description,
                a.hero_image_path, a.hero_image_alt, c.slug AS category, c.name AS category_name,
                au.name AS author_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN authors au ON au.id = a.author_id
            WHERE a.status = 'published' AND a.slug = :slug
            LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row ? map_article_row($row) : null;
    } catch (Throwable $exception) {
        return null;
    }
}

function map_article_row(array $row): array
{
    $body = json_decode((string) $row['body'], true);
    if (!is_array($body)) {
        $body = preg_split('/\R{2,}/', trim((string) $row['body'])) ?: [];
    }

    $article = [
        'slug' => (string) $row['slug'],
        'category' => (string) $row['category'],
        'category_name' => (string) $row['category_name'],
        'title' => (string) $row['title'],
        'dek' => (string) $row['dek'],
        'brief' => (string) $row['brief'],
        'why' => (string) $row['why_it_matters'],
        'numbers' => [],
        'body' => array_values(array_filter(array_map('strval', $body))),
        'read_time' => (int) $row['read_time_minutes'] . ' min read',
        'published_at' => $row['published_at'] ? date('Y-m-d', strtotime((string) $row['published_at'])) : '',
        'author_name' => (string) ($row['author_name'] ?? '钱潮编辑部'),
        'seo_title' => (string) ($row['seo_title'] ?? ''),
        'seo_description' => (string) ($row['seo_description'] ?? ''),
        'hero_image_alt' => (string) (($row['hero_image_alt'] ?? '') ?: $row['title']),
    ];
    $article['hero_image'] = article_media_url($article + ['hero_image_path' => $row['hero_image_path'] ?? '']);

    return $article;
}

function admin_categories(): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    try {
        return $pdo->query('SELECT id, slug, name, summary FROM categories ORDER BY sort_order ASC, name ASC')->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function admin_articles(array $filters = []): array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return [];
    }

    $sql = "SELECT a.id, a.slug, a.status, a.title, a.dek, a.read_time_minutes, a.published_at,
                a.updated_at, a.created_by_user_id, a.hero_image_path,
                c.name AS category_name, c.slug AS category_slug,
                au.name AS author_name,
                COALESCE(u.display_name, u.email) AS editor_name
            FROM articles a
            INNER JOIN categories c ON c.id = a.category_id
            LEFT JOIN authors au ON au.id = a.author_id
            LEFT JOIN users u ON u.id = a.editor_id
            WHERE 1 = 1";
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND a.status = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['category'])) {
        $sql .= ' AND c.slug = :category';
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (a.title LIKE :q_title OR a.slug LIKE :q_slug OR a.dek LIKE :q_dek)';
        $params['q_title'] = '%' . $filters['q'] . '%';
        $params['q_slug'] = '%' . $filters['q'] . '%';
        $params['q_dek'] = '%' . $filters['q'] . '%';
    }

    if (!empty($filters['from'])) {
        $sql .= ' AND a.updated_at >= :from';
        $params['from'] = $filters['from'] . ' 00:00:00';
    }

    if (!empty($filters['to'])) {
        $sql .= ' AND a.updated_at <= :to';
        $params['to'] = $filters['to'] . ' 23:59:59';
    }

    $sort = (string) ($filters['sort'] ?? 'updated_desc');
    $orderBy = match ($sort) {
        'updated_asc' => 'a.updated_at ASC, a.id ASC',
        'published_desc' => 'a.published_at DESC, a.id DESC',
        'published_asc' => 'a.published_at ASC, a.id ASC',
        'title_asc' => 'a.title ASC, a.id DESC',
        default => 'a.updated_at DESC, a.id DESC',
    };
    $sql .= ' ORDER BY ' . $orderBy . ' LIMIT 100';

    try {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    } catch (Throwable $exception) {
        return [];
    }
}

function admin_article_status_counts(): array
{
    ensure_editorial_schema();
    $pdo = db();
    $counts = ['all' => 0, 'draft' => 0, 'review' => 0, 'published' => 0, 'archived' => 0];
    if (!$pdo instanceof PDO) {
        return $counts;
    }

    try {
        $rows = $pdo->query('SELECT status, COUNT(*) AS total FROM articles GROUP BY status')->fetchAll();
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $total = (int) $row['total'];
            if (isset($counts[$status])) {
                $counts[$status] = $total;
            }
            $counts['all'] += $total;
        }
    } catch (Throwable $exception) {
    }

    return $counts;
}

function admin_article_by_id(int $id): ?array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return null;
    }

    try {
        $statement = $pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $article = $statement->fetch();

        return $article ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function save_article(array $input, ?int $id = null): array
{
    ensure_editorial_schema();
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。'], 'id' => $id];
    }

    $data = normalize_article_input($input);
    $existing = $id !== null ? admin_article_by_id($id) : null;
    if ($id === null && !can_create_article()) {
        return ['ok' => false, 'errors' => ['You do not have permission to create articles.'], 'id' => $id, 'data' => $data];
    }
    if ($id !== null) {
        if (!$existing) {
            return ['ok' => false, 'errors' => ['Article not found.'], 'id' => $id, 'data' => $data];
        }
        if (!can_edit_article($existing)) {
            return ['ok' => false, 'errors' => ['You do not have permission to edit this article.'], 'id' => $id, 'data' => $data];
        }
        if ($data['status'] !== (string) ($existing['status'] ?? 'draft') && !can_transition_article($existing, $data['status'])) {
            return ['ok' => false, 'errors' => ['You do not have permission to change this article status.'], 'id' => $id, 'data' => $data];
        }
    } elseif (in_array($data['status'], ['published', 'archived'], true) && !can_publish_article()) {
        return ['ok' => false, 'errors' => ['Only editors and admins can create published or archived articles.'], 'id' => $id, 'data' => $data];
    }
    if (!can_assign_editor()) {
        $data['editor_id'] = $existing['editor_id'] ?? null;
    }

    $errors = validate_article_input($data, $id);
    if (!$errors && $data['status'] === 'published') {
        $errors = array_merge($errors, publish_checklist_errors($data));
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors, 'id' => $id, 'data' => $data];
    }

    try {
        $user = current_user();
        $data['updated_by_user_id'] = is_array($user) && (int) ($user['id'] ?? 0) > 0 ? (int) $user['id'] : null;
        if ($id === null) {
            $data['created_by_user_id'] = $data['updated_by_user_id'];
            $statement = $pdo->prepare('INSERT INTO articles
                (category_id, author_id, editor_id, created_by_user_id, updated_by_user_id, slug, status, title, dek, brief, why_it_matters, body, seo_title, seo_description, hero_image_path, hero_image_alt, read_time_minutes, published_at)
                VALUES (:category_id, :author_id, :editor_id, :created_by_user_id, :updated_by_user_id, :slug, :status, :title, :dek, :brief, :why_it_matters, :body, :seo_title, :seo_description, :hero_image_path, :hero_image_alt, :read_time_minutes, :published_at)');
        } else {
            $statement = $pdo->prepare('UPDATE articles SET
                category_id = :category_id,
                author_id = :author_id,
                editor_id = :editor_id,
                updated_by_user_id = :updated_by_user_id,
                slug = :slug,
                status = :status,
                title = :title,
                dek = :dek,
                brief = :brief,
                why_it_matters = :why_it_matters,
                body = :body,
                seo_title = :seo_title,
                seo_description = :seo_description,
                hero_image_path = :hero_image_path,
                hero_image_alt = :hero_image_alt,
                read_time_minutes = :read_time_minutes,
                published_at = :published_at
                WHERE id = :id');
            $data['id'] = $id;
        }

        $statement->execute($data);
        $articleId = $id ?? (int) $pdo->lastInsertId();
        if ($id === null) {
            record_article_audit($articleId, 'created', null, $data['status'], 'Article created.');
        } elseif ($existing && $data['status'] !== (string) ($existing['status'] ?? '')) {
            record_article_audit($articleId, 'status_change', (string) $existing['status'], $data['status'], 'Status changed from article form.');
        }
        return ['ok' => true, 'errors' => [], 'id' => $articleId];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['保存失败：标题别名可能重复，或数据库暂时不可用。'], 'id' => $id, 'data' => $data];
    }
}

function normalize_article_input(array $input): array
{
    $status = (string) ($input['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'review', 'published', 'archived'], true)) {
        $status = 'draft';
    }

    $title = trim((string) ($input['title'] ?? ''));
    $slug = trim((string) ($input['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugify($title);
    }

    $publishedAt = trim((string) ($input['published_at'] ?? ''));
    if ($status === 'published' && $publishedAt === '') {
        $publishedAt = date('Y-m-d H:i:s');
    } elseif ($publishedAt !== '' && strlen($publishedAt) <= 16) {
        $publishedAt = str_replace('T', ' ', $publishedAt) . ':00';
    } elseif ($publishedAt === '') {
        $publishedAt = null;
    }

    $body = trim((string) ($input['body'] ?? ''));
    $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $body) ?: [])));
    if ($status === 'published') {
        $paragraphs = append_article_publish_disclaimer($paragraphs ?: [$body]);
    }

    return [
        'category_id' => (int) ($input['category_id'] ?? 0),
        'author_id' => (int) ($input['author_id'] ?? 0) > 0 ? (int) $input['author_id'] : default_author_id(),
        'editor_id' => (int) ($input['editor_id'] ?? 0) > 0 ? (int) $input['editor_id'] : null,
        'slug' => $slug,
        'status' => $status,
        'title' => $title,
        'dek' => trim((string) ($input['dek'] ?? '')),
        'brief' => trim((string) ($input['brief'] ?? '')),
        'why_it_matters' => trim((string) ($input['why_it_matters'] ?? '')),
        'body' => json_encode($paragraphs ?: [$body], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'seo_title' => trim((string) ($input['seo_title'] ?? '')),
        'seo_description' => trim((string) ($input['seo_description'] ?? '')),
        'hero_image_path' => trim((string) ($input['hero_image_path'] ?? '')),
        'hero_image_alt' => trim((string) ($input['hero_image_alt'] ?? '')),
        'read_time_minutes' => max(1, (int) ($input['read_time_minutes'] ?? 3)),
        'published_at' => $publishedAt,
    ];
}

function append_article_publish_disclaimer(array $paragraphs): array
{
    $disclaimer = '本文内容仅供参考，不构成投资建议。';
    $removedNotes = [
        'Editor note: This article was AI-assisted. Verify sources, facts, numbers, and tone before publishing.',
        'This is for information only and is not investment advice.',
    ];

    $cleaned = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '' || $paragraph === $disclaimer || in_array($paragraph, $removedNotes, true)) {
            continue;
        }
        $cleaned[] = $paragraph;
    }

    $cleaned[] = $disclaimer;
    return $cleaned;
}

function validate_article_input(array $data, ?int $id = null): array
{
    $errors = [];
    if ($data['category_id'] <= 0) {
        $errors[] = '请选择栏目。';
    }
    if ($data['title'] === '') {
        $errors[] = '标题不能为空。';
    }
    if ($data['slug'] === '') {
        $errors[] = 'URL slug 不能为空。';
    }
    if (!preg_match('/^[a-z0-9-]+$/', $data['slug'])) {
        $errors[] = 'URL slug 只能包含小写字母、数字和连字符。';
    }
    if ($data['dek'] === '') {
        $errors[] = '副标题不能为空。';
    }
    if ($data['brief'] === '') {
        $errors[] = '一句话看懂不能为空。';
    }
    if ($data['why_it_matters'] === '') {
        $errors[] = '为什么重要不能为空。';
    }
    if ($data['body'] === '' || $data['body'] === '[""]') {
        $errors[] = '正文不能为空。';
    }
    if ($data['status'] === 'published' && !$data['published_at']) {
        $errors[] = '发布文章需要发布时间。';
    }

    return $errors;
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'article-' . date('YmdHis');
}

function article_form_defaults(): array
{
    $defaultAuthorId = default_author_id();

    return [
        'category_id' => '',
        'author_id' => $defaultAuthorId ? (string) $defaultAuthorId : '',
        'editor_id' => '',
        'slug' => '',
        'status' => 'draft',
        'title' => '',
        'dek' => '',
        'brief' => '',
        'why_it_matters' => '',
        'body' => '',
        'seo_title' => '',
        'seo_description' => '',
        'hero_image_path' => '',
        'hero_image_alt' => '',
        'created_by_user_id' => '',
        'read_time_minutes' => 3,
        'published_at' => '',
    ];
}

function article_to_form(array $article): array
{
    $body = json_decode((string) ($article['body'] ?? ''), true);
    if (is_array($body)) {
        $body = implode("\n\n", array_map('strval', $body));
    } else {
        $body = (string) ($article['body'] ?? '');
    }

    return [
        'category_id' => (string) ($article['category_id'] ?? ''),
        'author_id' => (string) ($article['author_id'] ?? ''),
        'editor_id' => (string) ($article['editor_id'] ?? ''),
        'slug' => (string) ($article['slug'] ?? ''),
        'status' => (string) ($article['status'] ?? 'draft'),
        'title' => (string) ($article['title'] ?? ''),
        'dek' => (string) ($article['dek'] ?? ''),
        'brief' => (string) ($article['brief'] ?? ''),
        'why_it_matters' => (string) ($article['why_it_matters'] ?? ''),
        'body' => $body,
        'seo_title' => (string) ($article['seo_title'] ?? ''),
        'seo_description' => (string) ($article['seo_description'] ?? ''),
        'hero_image_path' => (string) ($article['hero_image_path'] ?? ''),
        'hero_image_alt' => (string) ($article['hero_image_alt'] ?? ''),
        'created_by_user_id' => (string) ($article['created_by_user_id'] ?? ''),
        'read_time_minutes' => (int) ($article['read_time_minutes'] ?? 3),
        'published_at' => !empty($article['published_at']) ? date('Y-m-d\TH:i', strtotime((string) $article['published_at'])) : '',
    ];
}

function seed_launch_articles(): array
{
    $categories = admin_categories();
    if (!$categories) {
        return ['ok' => false, 'message' => '请先导入栏目数据。'];
    }

    $categoryIds = [];
    foreach ($categories as $category) {
        $categoryIds[$category['slug']] = (int) $category['id'];
    }

    $samples = [
        [
            'category_id' => $categoryIds['markets'] ?? reset($categoryIds),
            'slug' => 'fed-rate-watch-money-tide',
            'status' => 'published',
            'title' => '美联储降息预期又变了，市场为什么还在冲？',
            'dek' => '交易员正在重新定价利率路径，但股票市场更关心企业盈利和 AI 投资周期。',
            'brief' => '利率预期摇摆没有终结风险偏好，资金仍在寻找增长确定性。',
            'why_it_matters' => '中文投资者看美股，不能只盯降息时间表，还要同时看盈利、流动性和美元走势。',
            'body' => "过去几周，美债收益率和降息预期反复摇摆，但主要股指并没有同步转弱。\n\n这背后有两个原因：一是大型科技公司的盈利仍然支撑指数，二是市场相信 AI 资本开支会继续带来收入增长。\n\n对普通读者来说，关键不是预测下一次议息会议，而是观察资金是否还愿意为增长支付高估值。",
            'read_time_minutes' => 4,
            'published_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ],
        [
            'category_id' => $categoryIds['tech'] ?? reset($categoryIds),
            'slug' => 'ai-capex-china-readers',
            'status' => 'published',
            'title' => 'AI 公司继续烧钱，真正的赢家可能是谁？',
            'dek' => '从芯片、云服务到电力基础设施，AI 投资正在把科技新闻变成产业链新闻。',
            'brief' => 'AI 热潮的利润不只在模型公司，也在卖铲子的基础设施公司。',
            'why_it_matters' => '这帮助读者从“哪个模型更强”转向“谁能持续收到订单”。',
            'body' => "AI 竞争表面上是模型和应用的竞争，底层却是算力、电力、网络和数据中心的竞争。\n\n当头部公司继续扩大资本开支，芯片供应商、云平台、服务器制造商和能源服务商都会被拉进同一个增长故事。\n\n钱潮会持续跟踪这些订单如何传导到上市公司收入，而不只追逐发布会标题。",
            'read_time_minutes' => 3,
            'published_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ],
        [
            'category_id' => $categoryIds['global-china'] ?? reset($categoryIds),
            'slug' => 'chinese-brands-global-pricing',
            'status' => 'published',
            'title' => '中国品牌出海，下一场硬仗是定价权',
            'dek' => '从跨境电商到新能源车，低价打开市场后，品牌需要证明自己能留住利润。',
            'brief' => '出海不只是卖到海外，更是把毛利率和品牌心智带到海外。',
            'why_it_matters' => '定价权决定中国公司在全球市场是短期流量玩家，还是长期利润玩家。',
            'body' => "许多中国品牌已经证明自己能用供应链效率打开海外市场。\n\n但下一阶段更难：企业需要在本地渠道、售后、合规和品牌信任上持续投入，同时避免被困在低价竞争里。\n\n对投资者和创业者来说，观察毛利率变化，比单纯观察销售额增速更重要。",
            'read_time_minutes' => 4,
            'published_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        ],
    ];

    $created = 0;
    foreach ($samples as $sample) {
        $result = save_article($sample);
        if ($result['ok']) {
            $created++;
        }
    }

    return ['ok' => true, 'message' => "已尝试创建 {$created} 篇启动文章。"];
}

function publish_checklist(array $article): array
{
    $body = $article['body'] ?? '';
    $paragraphs = is_array($body) ? $body : json_decode((string) $body, true);
    if (!is_array($paragraphs)) {
        $paragraphs = preg_split('/\R{2,}/', trim((string) $body)) ?: [];
    }
    $paragraphs = array_values(array_filter(array_map('strval', $paragraphs)));
    $bodyText = implode("\n\n", $paragraphs);
    $wordCount = mb_strlen(strip_tags($bodyText), 'UTF-8');
    $disclaimer = '本文内容仅供参考，不构成投资建议。';

    return [
        [
            'key' => 'title',
            'label' => '标题已填写',
            'passed' => trim((string) ($article['title'] ?? '')) !== '',
        ],
        [
            'key' => 'category',
            'label' => '已选择栏目',
            'passed' => (int) ($article['category_id'] ?? 0) > 0,
        ],
        [
            'key' => 'slug',
            'label' => 'URL slug 合法',
            'passed' => (bool) preg_match('/^[a-z0-9-]+$/', (string) ($article['slug'] ?? '')),
        ],
        [
            'key' => 'dek',
            'label' => '副标题已填写',
            'passed' => trim((string) ($article['dek'] ?? '')) !== '',
        ],
        [
            'key' => 'brief',
            'label' => '一句话看懂已填写',
            'passed' => trim((string) ($article['brief'] ?? '')) !== '',
        ],
        [
            'key' => 'why',
            'label' => '为什么重要已填写',
            'passed' => trim((string) ($article['why_it_matters'] ?? '')) !== '',
        ],
        [
            'key' => 'body_length',
            'label' => '正文不少于 120 字',
            'passed' => $wordCount >= 120,
        ],
    ];
}

function publish_checklist_errors(array $data): array
{
    $errors = [];
    foreach (publish_checklist($data) as $item) {
        if (!$item['passed']) {
            $errors[] = '发布前检查未通过：' . $item['label'];
        }
    }
    return $errors;
}

function transition_article_status(int $id, string $status): array
{
    $allowed = ['draft', 'review', 'published', 'archived'];
    if (!in_array($status, $allowed, true)) {
        return ['ok' => false, 'errors' => ['未知状态。']];
    }

    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }

    $article = admin_article_by_id($id);
    if (!$article) {
        return ['ok' => false, 'errors' => ['文章不存在。']];
    }
    if (!can_edit_article($article)) {
        return ['ok' => false, 'errors' => ['You do not have permission to duplicate this article.']];
    }
    if (!can_transition_article($article, $status)) {
        return ['ok' => false, 'errors' => ['You do not have permission to change this article status.']];
    }

    if ($status === 'published') {
        $checklistData = $article;
        $checklistData['status'] = 'published';
        if (empty($checklistData['published_at'])) {
            $checklistData['published_at'] = date('Y-m-d H:i:s');
        }
        $bodyParagraphs = json_decode((string) ($article['body'] ?? '[]'), true);
        if (!is_array($bodyParagraphs)) {
            $bodyParagraphs = preg_split('/\R{2,}/', trim((string) ($article['body'] ?? ''))) ?: [];
        }
        $bodyParagraphs = append_article_publish_disclaimer($bodyParagraphs);
        $checklistData['body'] = json_encode($bodyParagraphs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $errors = publish_checklist_errors($checklistData);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        try {
            $pdo->prepare('UPDATE articles SET status = :status, body = :body, published_at = :published_at WHERE id = :id')
                ->execute([
                    'status' => 'published',
                    'body' => $checklistData['body'],
                    'published_at' => $checklistData['published_at'],
                    'id' => $id,
                ]);
            record_article_audit($id, 'status_change', (string) $article['status'], 'published', 'Published through workflow.');
            return ['ok' => true, 'errors' => []];
        } catch (Throwable $exception) {
            return ['ok' => false, 'errors' => ['更新状态失败。']];
        }
    }

    try {
        $pdo->prepare('UPDATE articles SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $id]);
        record_article_audit($id, 'status_change', (string) $article['status'], $status, 'Changed through workflow.');
        return ['ok' => true, 'errors' => []];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['更新状态失败。']];
    }
}

function unique_article_slug(string $baseSlug, ?int $ignoreId = null): string
{
    $pdo = db();
    $base = $baseSlug !== '' ? $baseSlug : 'article-' . date('YmdHis');
    if (!$pdo instanceof PDO) {
        return $base;
    }

    $candidate = $base;
    $suffix = 2;
    try {
        while (true) {
            $sql = 'SELECT id FROM articles WHERE slug = :slug';
            $params = ['slug' => $candidate];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :id';
                $params['id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            if (!$statement->fetch()) {
                return $candidate;
            }
            $candidate = $base . '-' . $suffix;
            $suffix++;
            if ($suffix > 50) {
                return $base . '-' . substr((string) time(), -4);
            }
        }
    } catch (Throwable $exception) {
        return $base;
    }
}

function duplicate_article(int $id): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['数据库未连接。']];
    }

    $article = admin_article_by_id($id);
    if (!$article) {
        return ['ok' => false, 'errors' => ['文章不存在。']];
    }

    $newSlug = unique_article_slug(((string) $article['slug']) . '-copy');
    $newTitle = (string) $article['title'] . '（副本）';

    try {
        $statement = $pdo->prepare('INSERT INTO articles
            (category_id, author_id, editor_id, created_by_user_id, updated_by_user_id, slug, status, title, dek, brief, why_it_matters, body, seo_title, seo_description, hero_image_path, hero_image_alt, read_time_minutes, published_at)
            VALUES (:category_id, :author_id, :editor_id, :created_by_user_id, :updated_by_user_id, :slug, :status, :title, :dek, :brief, :why_it_matters, :body, :seo_title, :seo_description, :hero_image_path, :hero_image_alt, :read_time_minutes, :published_at)');
        $user = current_user();
        $userId = is_array($user) && (int) ($user['id'] ?? 0) > 0 ? (int) $user['id'] : null;
        $statement->execute([
            'category_id' => (int) $article['category_id'],
            'author_id' => (int) ($article['author_id'] ?? 0) ?: default_author_id(),
            'editor_id' => $article['editor_id'] ?? null,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
            'slug' => $newSlug,
            'status' => 'draft',
            'title' => $newTitle,
            'dek' => (string) $article['dek'],
            'brief' => (string) $article['brief'],
            'why_it_matters' => (string) $article['why_it_matters'],
            'body' => (string) $article['body'],
            'seo_title' => (string) ($article['seo_title'] ?? ''),
            'seo_description' => (string) ($article['seo_description'] ?? ''),
            'hero_image_path' => (string) ($article['hero_image_path'] ?? ''),
            'hero_image_alt' => (string) ($article['hero_image_alt'] ?? ''),
            'read_time_minutes' => (int) $article['read_time_minutes'],
            'published_at' => null,
        ]);

        $newId = (int) $pdo->lastInsertId();
        record_article_audit($newId, 'created', null, 'draft', 'Duplicated from article #' . $id . '.');
        return ['ok' => true, 'id' => $newId];
    } catch (Throwable $exception) {
        return ['ok' => false, 'errors' => ['复制文章失败。']];
    }
}

function delete_article(int $id): array
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return ['ok' => false, 'errors' => ['Database is not connected.']];
    }

    $article = admin_article_by_id($id);
    if (!$article) {
        return ['ok' => false, 'errors' => ['Article not found.']];
    }
    if (!can_delete_article()) {
        return ['ok' => false, 'errors' => ['Only admins can delete articles.']];
    }

    $status = (string) ($article['status'] ?? '');
    if (!in_array($status, ['draft', 'archived'], true)) {
        return ['ok' => false, 'errors' => ['Only draft or archived articles can be deleted.']];
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM article_tags WHERE article_id = :id')->execute(['id' => $id]);
        $pdo->prepare('DELETE FROM articles WHERE id = :id LIMIT 1')->execute(['id' => $id]);
        $pdo->commit();
        return ['ok' => true, 'errors' => []];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'errors' => ['Delete failed.']];
    }
}

function admin_article_preview(int $id): ?array
{
    $article = admin_article_by_id($id);
    if (!$article) {
        return null;
    }

    $pdo = db();
    $categoryName = '';
    $categorySlug = '';
    $authorName = '钱潮编辑部';
    if ($pdo instanceof PDO) {
        try {
            $statement = $pdo->prepare('SELECT slug, name FROM categories WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $article['category_id']]);
            $category = $statement->fetch();
            if ($category) {
                $categorySlug = (string) $category['slug'];
                $categoryName = (string) $category['name'];
            }
        } catch (Throwable $exception) {
        }
        try {
            if (!empty($article['author_id'])) {
                $statement = $pdo->prepare('SELECT name FROM authors WHERE id = :id LIMIT 1');
                $statement->execute(['id' => (int) $article['author_id']]);
                $authorName = (string) ($statement->fetchColumn() ?: $authorName);
            }
        } catch (Throwable $exception) {
        }
    }

    $body = json_decode((string) $article['body'], true);
    if (!is_array($body)) {
        $body = preg_split('/\R{2,}/', trim((string) $article['body'])) ?: [];
    }

    $preview = [
        'slug' => (string) $article['slug'],
        'category' => $categorySlug,
        'category_name' => $categoryName,
        'title' => (string) $article['title'],
        'dek' => (string) $article['dek'],
        'brief' => (string) $article['brief'],
        'why' => (string) $article['why_it_matters'],
        'numbers' => [],
        'body' => array_values(array_filter(array_map('strval', $body))),
        'read_time' => (int) $article['read_time_minutes'] . ' min read',
        'published_at' => !empty($article['published_at']) ? date('Y-m-d', strtotime((string) $article['published_at'])) : date('Y-m-d'),
        'status' => (string) $article['status'],
        'author_name' => $authorName,
        'seo_title' => (string) ($article['seo_title'] ?? ''),
        'seo_description' => (string) ($article['seo_description'] ?? ''),
        'hero_image_alt' => (string) (($article['hero_image_alt'] ?? '') ?: $article['title']),
    ];
    $preview['hero_image'] = article_media_url($preview + ['hero_image_path' => $article['hero_image_path'] ?? '']);

    return $preview;
}
