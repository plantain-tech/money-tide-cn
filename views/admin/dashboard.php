<?php $pageTitle = '工作台 - 钱潮 Money Tide'; ?>
<section class="admin-shell">
    <div class="admin-topbar">
        <div>
            <p class="eyebrow">工作台</p>
            <h1>钱潮编辑后台</h1>
            <p>欢迎，<?= e($user['name'] ?? '编辑') ?>。今天可以创建、编辑和发布文章。</p>
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
        <div><span>今日浏览</span><strong><?= e((string) $analytics['views']['today']) ?></strong></div>
        <div><span>7 日浏览</span><strong><?= e((string) $analytics['views']['last_7d']) ?></strong></div>
        <div><span>7 日订阅</span><strong><?= e((string) $analytics['subscribes']['last_7d']) ?></strong></div>
        <div><span>累计浏览</span><strong><?= e((string) $analytics['views']['total']) ?></strong></div>
    </div>

    <div class="status-banner <?= ($externalAnalytics['ga_id'] !== '' || $externalAnalytics['plausible_domain'] !== '') ? 'is-ready' : 'is-warning' ?>">
        <strong>外部分析</strong>
        <span>
            GA: <?= $externalAnalytics['ga_id'] !== '' ? '已配置 (' . e($externalAnalytics['ga_id']) . ')' : '未配置' ?> ·
            Plausible: <?= $externalAnalytics['plausible_domain'] !== '' ? '已配置 (' . e($externalAnalytics['plausible_domain']) . ')' : '未配置' ?>
        </span>
    </div>

    <div class="analytics-panels">
        <section class="analytics-panel">
            <div class="analytics-panel-head">
                <h2>7 日热门文章</h2>
                <a class="ghost-link" href="<?= e(url('admin/analytics')) ?>">查看详情</a>
            </div>
            <?php if ($analytics['top_articles_7d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_articles_7d'] as $row): ?>
                        <li>
                            <a href="<?= e(url('article/' . $row['slug'])) ?>"><?= e((string) ($row['title'] ?: $row['slug'])) ?></a>
                            <span><?= e((string) $row['views']) ?> 次</span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>还没有浏览数据。访问几篇文章后回来看看。</small></p>
            <?php endif; ?>
        </section>

        <section class="analytics-panel">
            <h2>30 日订阅来源</h2>
            <?php if ($analytics['top_sources_30d']): ?>
                <ol class="analytics-list">
                    <?php foreach ($analytics['top_sources_30d'] as $row): ?>
                        <li>
                            <span><?= e((string) $row['source']) ?></span>
                            <span><?= e((string) $row['total']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p><small>还没有订阅事件。</small></p>
            <?php endif; ?>
        </section>
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
        <a class="admin-module" href="<?= e(url('admin/calendar')) ?>">
            <strong>编辑日历</strong>
            <span>按月/周查看文章发布时间与 newsletter 排期，并快速进入编辑页。</span>
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
        <a class="admin-module" href="<?= e(url('admin/ai-bots')) ?>">
            <strong>AI 编辑机器人</strong>
            <span>管理栏目机器人、语气、目标读者、来源规则、风险规则和启用状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/ai-intake')) ?>">
            <strong>选题录入</strong>
            <span>提交选题角度和来源链接，生成结构化编辑简报。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/ai-drafts/new')) ?>">
            <strong>生成 AI 草稿</strong>
            <span>输入来源链接，生成可审核的编辑草稿。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/ai-templates')) ?>">
            <strong>AI 任务模板</strong>
            <span>管理起草、改写、SEO、早报、社交文案和事实核查的任务提示词。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/research-desk')) ?>">
            <strong>研究简报</strong>
            <span>写文章之前，让 AI 先整理事实和角度。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/sources')) ?>">
            <strong>来源库</strong>
            <span>保存常用来源并标记可信度。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/source-templates')) ?>">
            <strong>输入模板</strong>
            <span>预设栏目 + 角度 + 来源，加速生成研究简报。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/newsletter')) ?>">
            <strong>钱潮早报</strong>
            <span>组装、预览、测试、广播每一期 newsletter。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/oauth')) ?>">
            <strong>第三方登录</strong>
            <span>查看 Google 登录配置状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/analytics')) ?>">
            <strong>站内分析</strong>
            <span>查看 14 天浏览趋势、热门文章、订阅来源。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/monetization')) ?>">
            <strong>会员与变现</strong>
            <span>管理 premium 标记、软付费墙提示和订阅层级准备。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/week4-checklist')) ?>">
            <strong>第 4 周检查</strong>
            <span>检查留存、SEO、分享、会员准备和 Week 5 backlog。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/week5-checklist')) ?>">
            <strong>第 5 周检查</strong>
            <span>检查 AI 编辑部、草稿队列、事实核查、改写、newsletter 助理与 Week 6 backlog。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/social')) ?>">
            <strong>社交分发</strong>
            <span>跨文章查看微信、小红书、LinkedIn、X 和 newsletter 短推荐的文案与状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/social/schedule')) ?>">
            <strong>社交发布队列</strong>
            <span>查看今天、未来、逾期和未排期的社交文案，按人工节奏发布。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/newsletter/schedule')) ?>">
            <strong>早报排期</strong>
            <span>检查今日可发送早报、未来排期和发送前 checklist。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/social-analytics')) ?>">
            <strong>传播分析</strong>
            <span>最常被分享的文章、分享渠道、社交回流和推荐订阅。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/week6-checklist')) ?>">
            <strong>第 6 周检查</strong>
            <span>检查社交分发、AI 文案、微信导出、分享卡、60秒看懂与 Week 7 backlog。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/week7-checklist')) ?>">
            <strong>第 7 周检查</strong>
            <span>检查搜索、RSS、编辑日历、排期队列、读者反馈和 Week 8 backlog。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/diagnostics')) ?>">
            <strong>系统诊断</strong>
            <span>数据库表行数、AI 额度、PHP 错误日志。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/smoke')) ?>">
            <strong>Smoke 检查</strong>
            <span>一键自检关键模块状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/audit')) ?>">
            <strong>审计日志</strong>
            <span>所有状态变更和删除操作记录。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/exports')) ?>">
            <strong>数据导出</strong>
            <span>把订阅者、文章、发送记录等导出 CSV。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/qa-checklist')) ?>">
            <strong>第 3 周检查</strong>
            <span>整周新功能上线前的检查清单。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/db-health')) ?>">
            <strong>数据库健康检查</strong>
            <span>查看当前生产数据库连接状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/qa')) ?>">
            <strong>生产 QA</strong>
            <span>检查上线前的内容、AI、订阅和数据库状态。</span>
        </a>
        <a class="admin-module" href="<?= e(url('admin/launch-checklist')) ?>">
            <strong>Launch Checklist</strong>
            <span>最终确认第一周 MVP 是否可以公开试运营。</span>
        </a>
        <form class="admin-module admin-module-form" method="post" action="<?= e(url('admin/seed-launch-articles')) ?>">
            <strong>启动文章</strong>
            <span>一键创建 3 篇可编辑的发布示例。</span>
            <button class="button button-small" type="submit">创建示例</button>
        </form>
    </div>
</section>
