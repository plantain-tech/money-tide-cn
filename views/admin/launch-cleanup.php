<?php $pageTitle = '上线数据清理 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">Launch Ops</p>
            <h1>上线数据清理</h1>
            <p>正式发布前清理测试读者、测试早报、AI 草稿和站内测试事件。系统配置、分类、模板、OAuth、Brevo、GA、已发布文章不会被清空。</p>
        </div>
        <div class="admin-actions">
            <a class="ghost-link" href="<?= e(url('admin/diagnostics')) ?>">诊断</a>
            <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">先备份</a>
            <a class="ghost-link" href="<?= e(url('admin/smoke')) ?>">自检</a>
        </div>
    </div>

    <?php if (is_array($result)): ?>
        <div class="status-banner <?= !empty($result['ok']) ? 'is-ready' : 'is-warning' ?>">
            <strong><?= !empty($result['ok']) ? '清理完成' : '清理未执行' ?></strong>
            <span><?= e((string) ($result['message'] ?? '')) ?></span>
        </div>
        <?php if (!empty($result['results'])): ?>
            <div class="cleanup-result-grid">
                <?php foreach ($result['results'] as $key => $count): ?>
                    <div>
                        <span><?= e((string) ($options[$key]['label'] ?? $key)) ?></span>
                        <strong><?= e((string) $count) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <section class="newsletter-block cleanup-warning-block">
        <h2>清理前确认</h2>
        <p>这是生产数据库操作。建议先到 <a href="<?= e(url('admin/backup')) ?>">备份导出</a> 下载核心 CSV。清理动作只针对测试模式和可重建数据，不会删除正式配置。</p>
        <div class="cleanup-safe-list">
            <span>保留：管理员、分类、标签、AI Bots、提示词模板、来源库、Brevo、Google Analytics、Google OAuth、已发布文章</span>
            <span>可清：测试账号、测试期号、AI 草稿、测试互动、站内测试 analytics</span>
        </div>
    </section>

    <form method="post" action="<?= e(url('admin/launch-cleanup')) ?>" class="cleanup-form">
        <section class="cleanup-option-grid">
            <?php foreach ($options as $key => $option): ?>
                <?php $count = (int) ($preview[$key] ?? 0); ?>
                <label class="cleanup-option <?= !empty($option['recommended']) ? 'is-recommended' : '' ?>">
                    <input type="checkbox" name="cleanup[]" value="<?= e($key) ?>" <?= !empty($option['recommended']) && $count > 0 ? 'checked' : '' ?>>
                    <span class="cleanup-option-meta">
                        <strong><?= e((string) $option['label']) ?></strong>
                        <small><?= e((string) $option['detail']) ?></small>
                    </span>
                    <span class="cleanup-count"><?= e((string) $count) ?></span>
                </label>
            <?php endforeach; ?>
        </section>

        <section class="newsletter-block cleanup-confirm-block">
            <h2>执行清理</h2>
            <label>
                输入确认短语
                <input type="text" name="confirm_phrase" placeholder="清理上线测试数据" autocomplete="off">
            </label>
            <div class="cms-form-actions">
                <button class="button" type="submit"
                    data-confirm="确认清理选中的测试数据？"
                    data-confirm-sub="此操作会修改生产数据库。建议已完成备份。"
                    data-confirm-title="上线数据清理"
                    data-confirm-confirm="确认清理">清理选中项目</button>
                <a class="ghost-link" href="<?= e(url('admin/backup')) ?>">先去备份</a>
            </div>
        </section>
    </form>
</section>
