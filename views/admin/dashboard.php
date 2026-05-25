<?php $pageTitle = '工作台 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Day 2 工作台</p>
            <h1>钱潮编辑后台</h1>
            <p>欢迎，<?= e($user['name'] ?? 'Editor') ?>。今天先把订阅、内容和 AI 编辑流程的骨架立起来。</p>
        </div>
        <a class="ghost-link" href="<?= e(url('admin/logout')) ?>">退出</a>
    </div>

    <div class="status-banner <?= $dbReady ? 'is-ready' : 'is-warning' ?>">
        <strong><?= $dbReady ? '数据库已连接' : '数据库未连接' ?></strong>
        <span><?= $dbReady ? '订阅和统计会写入 MySQL。' : '请在 Hostinger 创建 config.php 并导入 database/schema.sql。' ?></span>
    </div>

    <div class="admin-stat-grid">
        <div><span>订阅用户</span><strong><?= e((string) $stats['subscribers']) ?></strong></div>
        <div><span>全部文章</span><strong><?= e((string) $stats['articles']) ?></strong></div>
        <div><span>已发布</span><strong><?= e((string) $stats['published']) ?></strong></div>
        <div><span>草稿/审核</span><strong><?= e((string) $stats['drafts']) ?></strong></div>
    </div>

    <div class="admin-module-grid">
        <a class="admin-module" href="<?= e(url('latest')) ?>">
            <strong>文章</strong>
            <span>Day 3 将加入创建、编辑、发布流程。</span>
        </a>
        <a class="admin-module" href="<?= e(url('subscribe')) ?>">
            <strong>Newsletter</strong>
            <span>Day 2 已支持真实邮箱入库。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/db-health')) ?>">
            <strong>数据库健康检查</strong>
            <span>查看当前生产数据库连接状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('editorial-standards')) ?>">
            <strong>AI 编辑任务</strong>
            <span>Day 4 接入栏目机器人和草稿审核队列。</span>
        </a>
    </div>
</section>
