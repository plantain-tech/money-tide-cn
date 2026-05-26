<?php
$pageTitle = '话题 - 钱潮 Money Tide';
$pageDescription = '按话题浏览钱潮 Money Tide 的最新报道。';
$canonicalPath = 'topics';
?>
<section class="topics-shell">
    <div class="tag-header">
        <p class="eyebrow">Topics</p>
        <h1>按话题浏览</h1>
        <p>钱潮编辑部用标签整理每篇报道；最热门的话题排在前面。</p>
    </div>

    <?php if ($tags): ?>
        <div class="topic-cloud">
            <?php foreach ($tags as $tag): ?>
                <a class="topic-chip" href="<?= e(url('tag/' . $tag['slug'])) ?>">
                    <strong>#<?= e($tag['name']) ?></strong>
                    <small><?= e((string) $tag['article_count']) ?> 篇</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state reader-empty-state">
            <strong>还没有任何标签。</strong>
            <p>编辑部正在给文章打标签。</p>
        </div>
    <?php endif; ?>
</section>
