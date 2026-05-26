CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    bio TEXT NULL,
    avatar_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    status ENUM('draft', 'review', 'published', 'archived') NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    dek VARCHAR(500) NOT NULL,
    brief VARCHAR(500) NOT NULL,
    why_it_matters TEXT NOT NULL,
    body MEDIUMTEXT NOT NULL,
    seo_title VARCHAR(255) NULL,
    seo_description VARCHAR(500) NULL,
    hero_image_path VARCHAR(255) NULL,
    read_time_minutes INT UNSIGNED NOT NULL DEFAULT 3,
    published_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_category FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES authors(id),
    INDEX idx_articles_status_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_tags (
    article_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    CONSTRAINT fk_article_tags_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT fk_article_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    status ENUM('pending', 'active', 'unsubscribed') NOT NULL DEFAULT 'active',
    referral_code VARCHAR(40) NULL UNIQUE,
    source VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_preferences (
    subscriber_id INT UNSIGNED NOT NULL,
    topic VARCHAR(80) NOT NULL,
    PRIMARY KEY (subscriber_id, topic),
    CONSTRAINT fk_preferences_subscriber FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NULL UNIQUE,
    display_name VARCHAR(120) NULL,
    password_hash VARCHAR(255) NULL,
    role ENUM('reader', 'writer', 'editor', 'admin') NOT NULL DEFAULT 'reader',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider ENUM('google', 'apple', 'wechat') NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_user (provider, provider_user_id),
    CONSTRAINT fk_login_providers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_drafts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_slug VARCHAR(120) NOT NULL,
    prompt_name VARCHAR(120) NOT NULL,
    source_links LONGTEXT NULL,
    draft_payload LONGTEXT NOT NULL,
    status ENUM('generated', 'reviewed', 'accepted', 'rejected') NOT NULL DEFAULT 'generated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_usage_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(60) NOT NULL,
    model VARCHAR(120) NOT NULL,
    section_slug VARCHAR(120) NULL,
    prompt_chars INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ok', 'error') NOT NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_usage_created (created_at),
    INDEX idx_ai_usage_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE editorial_tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    task_type ENUM('source_check', 'copy_edit', 'seo', 'social', 'newsletter') NOT NULL,
    status ENUM('open', 'doing', 'done') NOT NULL DEFAULT 'open',
    due_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_editorial_tasks_article FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
