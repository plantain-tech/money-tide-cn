<?php
$pageTitle = (string) $issue['subject'] . ' - 钱潮早报';
$pageDescription = (string) ($issue['intro'] ?? '钱潮早报本期内容。');
$canonicalPath = 'newsletter/' . (string) ($issue['slug'] ?: ('issue-' . $issue['id']));
?>
<section class="article-shell article-reading-shell">
    <nav class="article-breadcrumbs" aria-label="路径">
        <a href="<?= e(url()) ?>">首页</a>
        <span>/</span>
        <a href="<?= e(url('newsletter')) ?>">早报</a>
        <span>/</span>
        <span>本期</span>
    </nav>

    <header class="article-header article-reading-header">
        <p class="eyebrow">钱潮早报 · <?= e(date('Y-m-d', strtotime((string) ($issue['sent_at'] ?: $issue['created_at'])))) ?></p>
        <h1><?= e((string) $issue['subject']) ?></h1>
        <?php if (!empty($issue['intro'])): ?>
            <p><?= nl2br(e((string) $issue['intro'])) ?></p>
        <?php endif; ?>
    </header>

    <div class="article-body article-reading-body">
        <?php foreach ($issue['articles'] as $article): ?>
            <section class="newsletter-public-block">
                <span class="pill"><?= e((string) $article['category_name']) ?></span>
                <h2><a href="<?= e(url('article/' . $article['slug'])) ?>"><?= e((string) $article['title']) ?></a></h2>
                <p><?= e((string) $article['dek']) ?></p>
                <?php if (!empty($article['blurb'])): ?>
                    <p><?= e((string) $article['blurb']) ?></p>
                <?php endif; ?>
                <a class="ghost-link" href="<?= e(url('article/' . $article['slug'])) ?>">阅读全文 →</a>
            </section>
        <?php endforeach; ?>

        <?php if (!empty($issue['outro'])): ?>
            <aside class="article-inline-newsletter">
                <p><?= nl2br(e((string) $issue['outro'])) ?></p>
            </aside>
        <?php endif; ?>
    </div>

    <p><a class="ghost-link" href="<?= e(url('newsletter')) ?>">← 返回所有期号</a></p>
</section>
