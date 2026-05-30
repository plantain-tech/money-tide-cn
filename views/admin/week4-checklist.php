<?php $pageTitle = '第 4 周检查 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">第 4 周</p>
            <h1>生产检查与第 5 周储备清单</h1>
            <p>覆盖账号、留存、SEO、分享、早报和会员准备。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">系统自检</a>
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
        </div>
    </div>

    <section class="newsletter-block">
        <h2>第 4 周检查清单</h2>
        <ul class="analytics-list">
            <?php foreach ($items as $item): ?>
                <li>
                    <span><?= e($item['label']) ?></span>
                    <small><?= e($item['tip']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="newsletter-block">
        <h2>第 5 周储备清单</h2>
        <div class="story-grid">
            <?php foreach ($backlog as $item): ?>
                <article class="story-card">
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['detail']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
