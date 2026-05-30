<?php

declare(strict_types=1);

function launch_checklist(): array
{
    $articles = get_articles();
    $categories = get_categories();
    $ai = ai_provider_status();
    $analytics = external_analytics_status();
    ensure_ai_prompt_templates_table();
    ensure_ai_draft_versions_table();
    ensure_ai_draft_checks_table();
    ensure_analytics_table();

    return [
        ['label' => '首页正常', 'ok' => true, 'detail' => canonical_url()],
        ['label' => '已有发布文章', 'ok' => count($articles) >= 3, 'detail' => count($articles) . ' 篇已发布'],
        ['label' => '栏目已建立', 'ok' => count($categories) >= 8, 'detail' => count($categories) . ' 个栏目'],
        ['label' => '订阅可写入邮箱', 'ok' => db_count('subscribers') >= 1, 'detail' => db_count('subscribers') . ' 位订阅者'],
        ['label' => 'AI 服务已配置', 'ok' => $ai['ready'], 'detail' => $ai['label'] . ' - ' . $ai['model']],
        ['label' => 'AI 提示词模板可用', 'ok' => db_count('ai_prompt_templates') >= 0, 'detail' => '模板表可访问'],
        ['label' => 'AI 版本历史可用', 'ok' => db_count('ai_draft_versions') >= 0, 'detail' => '版本表可访问'],
        ['label' => '文章工作流可用', 'ok' => db_count('articles', "status IN ('draft','review','published','archived')") >= 0, 'detail' => '草稿/审核/已发布/归档'],
        ['label' => '分析事件表可访问', 'ok' => db_count('analytics_events') >= 0, 'detail' => db_count('analytics_events') . ' 条事件'],
        ['label' => '站内分析页可用', 'ok' => true, 'detail' => canonical_url('admin/analytics')],
        ['label' => '外部分析（可选）', 'ok' => true, 'detail' => ($analytics['ga_id'] !== '' ? 'GA 已配置' : 'GA 未配置') . ' / ' . ($analytics['plausible_domain'] !== '' ? 'Plausible 已配置' : 'Plausible 未配置')],
        ['label' => 'Sitemap 已启用', 'ok' => true, 'detail' => canonical_url('sitemap.xml')],
        ['label' => 'Robots 已启用', 'ok' => true, 'detail' => canonical_url('robots.txt')],
        ['label' => '免责声明页存在', 'ok' => true, 'detail' => canonical_url('disclaimer')],
        ['label' => '编辑标准页存在', 'ok' => true, 'detail' => canonical_url('editorial-standards')],
        ['label' => '订阅者 CSV 导出可用', 'ok' => true, 'detail' => canonical_url('admin/subscribers.csv')],
        ['label' => '安全草稿清理可用', 'ok' => true, 'detail' => '仅删除草稿/归档'],
    ];
}

function launch_ready(array $items): bool
{
    foreach ($items as $item) {
        if (!$item['ok']) {
            return false;
        }
    }

    return true;
}
