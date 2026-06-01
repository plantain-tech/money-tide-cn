<?php

declare(strict_types=1);

/**
 * Day 10·4 — One-click assisted distribution packages.
 *
 * Reshapes an article that the AI already wrote into copy-ready text tuned for
 * each manual platform (小红书 / 头条 / 百家号 / 知乎). This is pure formatting —
 * no extra AI calls, instant, free — so the owner just opens a tab and pastes.
 *
 * Each package is platform-idiomatic: 小红书 is punchy + emoji + tags, 头条 /
 * 百家号 read like news, 知乎 reads like an analytical answer.
 */

function assisted_export_article_url(array $a, string $platform): string
{
    $slug = (string) ($a['slug'] ?? '');
    $url = function_exists('canonical_url') ? canonical_url('article/' . $slug) : '/article/' . $slug;
    return $url . '?utm_source=' . $platform . '&utm_medium=social&utm_campaign=assisted';
}

/** Split an article body (HTML or text) into clean plain-text paragraphs. */
function assisted_export_paragraphs(string $body): array
{
    $text = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $body));
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $parts = preg_split('/\n\s*\n|\n/u', trim($text)) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function assisted_export_hashtags(array $tags, array $extra = [], int $max = 6): string
{
    $seen = [];
    $out = [];
    foreach (array_merge($tags, $extra) as $t) {
        $t = trim((string) $t);
        $t = ltrim($t, '#＃');
        if ($t === '' || isset($seen[mb_strtolower($t)])) {
            continue;
        }
        $seen[mb_strtolower($t)] = true;
        $out[] = '#' . $t;
        if (count($out) >= $max) {
            break;
        }
    }
    return implode(' ', $out);
}

/**
 * Build the four platform packages for an article.
 * $article: title, dek, brief, why_it_matters, body, tags (array of names).
 * Returns key => [label, icon, title, text, tips].
 */
function assisted_export_packages(array $article): array
{
    $title = trim((string) ($article['title'] ?? ''));
    $dek = trim((string) ($article['dek'] ?? ''));
    $brief = trim((string) ($article['brief'] ?? ''));
    $why = trim((string) ($article['why_it_matters'] ?? ''));
    $paras = assisted_export_paragraphs((string) ($article['body'] ?? ''));
    $tags = array_values(array_filter(array_map('strval', (array) ($article['tags'] ?? []))));

    $lead = $brief !== '' ? $brief : ($dek !== '' ? $dek : ($paras[0] ?? ''));
    $first2 = implode("\n\n", array_slice($paras, 0, 2));
    $first3 = implode("\n\n", array_slice($paras, 0, 3));
    // Short bullet points from the opening paragraphs (sentence-level).
    $bullets = [];
    foreach (array_slice($paras, 0, 4) as $p) {
        $s = preg_split('/(?<=[。！？!?])/u', $p) ?: [];
        $s = array_values(array_filter(array_map('trim', $s)));
        if (!empty($s[0])) {
            $bullets[] = mb_substr($s[0], 0, 50, 'UTF-8');
        }
        if (count($bullets) >= 3) {
            break;
        }
    }

    // ---- 小红书：emoji, hooky, bullets, tags ----
    $xhsTitle = mb_substr('📈 ' . ($dek !== '' ? $dek : $title), 0, 20, 'UTF-8');
    $xhs = $xhsTitle . "\n\n";
    if ($lead !== '') {
        $xhs .= $lead . "\n\n";
    }
    if ($bullets) {
        $xhs .= "✅ 划重点：\n";
        foreach ($bullets as $b) {
            $xhs .= '· ' . $b . "\n";
        }
        $xhs .= "\n";
    }
    if ($why !== '') {
        $xhs .= '💡 为什么重要：' . mb_substr($why, 0, 80, 'UTF-8') . "\n\n";
    }
    $xhs .= '全文更香👉 ' . assisted_export_article_url($article, 'xiaohongshu') . "\n\n";
    $xhs .= assisted_export_hashtags($tags, ['财经', '投资笔记', '搞钱', '钱潮']);

    // ---- 头条：news lead + body excerpt ----
    $toutiao = $title . "\n\n";
    if ($lead !== '') {
        $toutiao .= $lead . "\n\n";
    }
    $toutiao .= $first3 . "\n\n";
    $toutiao .= '（全文：' . assisted_export_article_url($article, 'toutiao') . '）';

    // ---- 百家号：formal news + source ----
    $baijia = $title . "\n\n";
    $baijia .= ($first3 !== '' ? $first3 : $lead) . "\n\n";
    $baijia .= '来源：钱潮 Money Tide　' . assisted_export_article_url($article, 'baijiahao');

    // ---- 知乎：analytical answer ----
    $zhihu = $title . "\n\n";
    if ($why !== '') {
        $zhihu .= '先说结论：' . $why . "\n\n";
    } elseif ($lead !== '') {
        $zhihu .= $lead . "\n\n";
    }
    $zhihu .= ($first2 !== '' ? $first2 : $lead) . "\n\n";
    $zhihu .= '展开分析与数据见全文（来自钱潮 Money Tide）：' . assisted_export_article_url($article, 'zhihu');

    return [
        'xiaohongshu' => ['label' => '小红书', 'icon' => '📕', 'text' => trim($xhs), 'tips' => '标题≤20字，多用 emoji 和话题标签；正文短句分行。'],
        'toutiao' => ['label' => '今日头条', 'icon' => '🟠', 'text' => trim($toutiao), 'tips' => '信息密度高、客观；标题点明事实。可直接粘贴到头条号编辑器。'],
        'baijiahao' => ['label' => '百家号', 'icon' => '🐾', 'text' => trim($baijia), 'tips' => '正式新闻风、注明来源；适合百家号图文。'],
        'zhihu' => ['label' => '知乎', 'icon' => '🔵', 'text' => trim($zhihu), 'tips' => '先给结论再展开；适合作为回答或想法发布。'],
    ];
}
