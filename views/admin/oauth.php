<?php $pageTitle = '第三方登录配置 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">账号</p>
            <h1>第三方登录配置</h1>
            <p>哪些登录方式已经接入了凭证。空缺的字段需要在 GitHub Secrets 设置后重新部署。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin')) ?>">工作台</a>
            <a class="ghost-link" href="<?= e(url('account/login')) ?>" target="_blank" rel="noopener">打开登录页</a>
        </div>
    </div>

    <section class="newsletter-block">
        <h2>Google 登录上线检查</h2>
        <ul class="schedule-checklist">
            <?php foreach (($qaChecks ?? []) as $item): ?>
                <li class="<?= !empty($item['ok']) ? 'is-pass' : 'is-warning' ?>">
                    <strong><?= !empty($item['ok']) ? 'OK' : 'Check' ?></strong>
                    <span><?= e((string) $item['label']) ?></span>
                    <small><?= e((string) $item['tip']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="admin-table">
        <div class="admin-table-row admin-table-head"><span>登录方式</span><span>状态</span><span>需要的 Secrets</span><span>测试链接</span></div>
        <?php foreach ($providers as $key => $info): ?>
            <div class="admin-table-row">
                <strong><?= e((string) $info['label']) ?></strong>
                <span><mark class="<?= $info['configured'] ? 'status-ok' : 'status-warn' ?>"><?= $info['configured'] ? '已配置' : '待配置' ?></mark></span>
                <span>
                    <?php foreach ($info['env_keys'] as $env): ?><code><?= e($env) ?></code><br><?php endforeach; ?>
                    <?php if (!empty($info['redirect_uri'])): ?><small>Redirect URI：<?= e((string) $info['redirect_uri']) ?></small><?php endif; ?>
                </span>
                <a href="<?= e(url('account/oauth/' . $key)) ?>" target="_blank" rel="noopener">查看响应</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
