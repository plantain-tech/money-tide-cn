<?php $pageTitle = '工作台 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Day 3 工作台</p>
            <h1>钱潮编辑后台</h1>
            <p>欢迎，<?= e($user['name'] ?? 'Editor') ?>。今天可以创建、编辑和发布文章。</p>
        </div>
        <a class="ghost-link" href="<?= e(url('admin/logout')) ?>">退出</a>
    </div>

    <div class="status-banner <?= $dbReady ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $dbReady ? '数据库已连接' : '数据库未连接' ?></strong>
        <span><?= $dbReady ? '订阅、统计和文章会写入 MySQL。' : '请先连接 Hostinger MySQL。' ?></span>
    </div>

    <div class="status-banner <?= $aiProvider['ready'] ? 'is-ready' : 'is-warning' ?>">
        <strong><?= e($aiProvider['label']) ?> · <?= e($aiProvider['model']) ?></strong>
        <span><?= e($aiProvider['message']) ?> 今日 AI：<?= e((string) $aiUsage['used_today']) ?>/<?= e((string) $aiUsage['daily_limit']) ?></span>
    </div>

    <div class="admin-stat-grid">
        <div><span>订阅用户</span><strong><?= e((string) $stats['subscribers']) ?></strong></div>
        <div><span>全部文章</span><strong><?= e((string) $stats['articles']) ?></strong></div>
        <div><span>已发布</span><strong><?= e((string) $stats['published']) ?></strong></div>
        <div><span>草稿/审核</span><strong><?= e((string) $stats['drafts']) ?></strong></div>
    </div>

    <div class="admin-module-grid">
        <a class="admin-module" href="<?= e(url('admin/articles')) ?>">
            <strong>文章管理</strong>
            <span>查看、搜索、编辑和发布文章。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/articles/new')) ?>">
            <strong>新建文章</strong>
            <span>录入标题、摘要、正文和发布状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('subscribe')) ?>">
            <strong>Newsletter</strong>
            <span>查看公开订阅入口，订阅数据已写入 MySQL。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/subscribers')) ?>">
            <strong>订阅用户</strong>
            <span>搜索订阅者、查看偏好并导出 CSV。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/categories')) ?>">
            <strong>栏目</strong>
            <span>查看当前栏目和公开频道结构。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/ai-drafts')) ?>">
            <strong>AI 草稿</strong>
            <span>查看栏目机器人生成的新闻草稿。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/ai-drafts/new')) ?>">
            <strong>生成 AI 草稿</strong>
            <span>输入来源链接，生成可审核的编辑草稿。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/db-health')) ?>">
            <strong>数据库健康检查</strong>
            <span>查看当前生产数据库连接状态。</span>
        </a>
        <form class="admin-module admin-module-form" method="post" action="<?= e(url('admin/seed-launch-articles')) ?>">
            <strong>启动文章</strong>
            <span>一键创建 3 篇可编辑的发布示例。</span>
            <button class="button button-small" type="submit">创建示例</button>
        </form>
    </div>
</section>
