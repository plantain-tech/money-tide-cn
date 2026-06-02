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
 * Map an article's tags/company names to 雪球 cashtags ($名称(代码)$). Only a
 * curated, verified set is emitted — a wrong ticker code breaks the cashtag, so
 * unknown names are left to fall through as ordinary hashtags.
 */
function assisted_xueqiu_cashtags(array $tags): array
{
    static $map = [
        'nvidia' => '$英伟达(NVDA)$', '英伟达' => '$英伟达(NVDA)$',
        'apple' => '$苹果(AAPL)$', '苹果' => '$苹果(AAPL)$', 'iphone' => '$苹果(AAPL)$',
        'amazon' => '$亚马逊(AMZN)$', '亚马逊' => '$亚马逊(AMZN)$', 'aws' => '$亚马逊(AMZN)$',
        'tesla' => '$特斯拉(TSLA)$', '特斯拉' => '$特斯拉(TSLA)$',
        'intel' => '$英特尔(INTC)$', '英特尔' => '$英特尔(INTC)$',
        'microsoft' => '$微软(MSFT)$', '微软' => '$微软(MSFT)$',
        'google' => '$谷歌(GOOG)$', '谷歌' => '$谷歌(GOOG)$', 'alphabet' => '$谷歌(GOOG)$',
        'meta' => '$Meta(META)$', 'facebook' => '$Meta(META)$',
        'amd' => '$AMD(AMD)$',
        'arm' => '$Arm(ARM)$',
        'netflix' => '$奈飞(NFLX)$', '奈飞' => '$奈飞(NFLX)$',
        'tsmc' => '$台积电(TSM)$', '台积电' => '$台积电(TSM)$',
        'alibaba' => '$阿里巴巴(BABA)$', '阿里巴巴' => '$阿里巴巴(BABA)$', '阿里' => '$阿里巴巴(BABA)$',
        'tencent' => '$腾讯控股(00700)$', '腾讯' => '$腾讯控股(00700)$',
        '台积电(tsm)' => '$台积电(TSM)$',
    ];
    $out = [];
    foreach ($tags as $t) {
        $k = mb_strtolower(trim((string) $t), 'UTF-8');
        if ($k !== '' && isset($map[$k]) && !in_array($map[$k], $out, true)) {
            $out[] = $map[$k];
        }
    }
    return $out;
}

/**
 * Build the platform packages for an article.
 * $article: title, dek, brief, why_it_matters, body, tags (array of names).
 * Returns key => [label, icon, tips, and either 'text' or 'blocks'=>[[label,text]]].
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

    // ---- 雪球：长文（早报体）+ 动态（短帖），自动插入 $现金标签$ ----
    $cashtags = assisted_xueqiu_cashtags($tags);
    $cashStr = $cashtags ? implode(' ', $cashtags) . ' ' : '';
    $xqUrl = assisted_export_article_url($article, 'xueqiu');

    $xqLong = '【钱潮早报】' . $title . "\n\n";
    $xqLong .= '📌 一句话总览' . "\n" . ($lead !== '' ? $lead : ($dek !== '' ? $dek : $title)) . "\n\n";
    if ($bullets) {
        $xqLong .= '📈 要点' . "\n";
        foreach ($bullets as $b) {
            $xqLong .= '· ' . $b . "\n";
        }
        $xqLong .= "\n";
    }
    if ($why !== '') {
        $xqLong .= '🔍 为什么重要' . "\n" . $why . "\n\n";
    }
    $xqLong .= '💡 关注「钱潮 Money Tide」，每天 5 分钟看懂全球市场。' . "\n";
    $xqLong .= '全文 👉 ' . $xqUrl . "\n\n";
    $xqLong .= $cashStr . assisted_export_hashtags($tags, ['财经', '投资', '美股', 'A股'], 5) . "\n";
    $xqLong .= '（仅供参考，不构成投资建议）';

    $xqShort1 = ($dek !== '' ? $dek : $title) . "\n";
    if (!empty($bullets[0])) {
        $xqShort1 .= '· ' . $bullets[0] . "\n";
    }
    if ($why !== '') {
        $xqShort1 .= '一句话：' . mb_substr($why, 0, 60, 'UTF-8') . "\n";
    }
    $xqShort1 .= $cashStr . assisted_export_hashtags($tags, ['财经'], 3);

    $xqBlocks = [
        ['label' => '📄 长文（每日早报）', 'text' => trim($xqLong)],
        ['label' => '⚡ 动态 ①（短帖）', 'text' => trim($xqShort1)],
    ];
    if (!empty($bullets[1])) {
        $xqShort2 = $title . "\n· " . $bullets[1] . "\n" . $cashStr . assisted_export_hashtags($tags, ['投资'], 3);
        $xqBlocks[] = ['label' => '⚡ 动态 ②（短帖）', 'text' => trim($xqShort2)];
    }

    return [
        'xueqiu' => [
            'label' => '雪球',
            'icon' => '❄️',
            'blocks' => $xqBlocks,
            'tips' => '雪球是中文财经第一阵地。长文当「长帖/文章」发；动态当「短帖」单独发。已自动插入 $股票$ 现金标签——它会把帖子带进个股讨论页，曝光最高。新号别每帖都放链接。',
        ],
        'xiaohongshu' => ['label' => '小红书', 'icon' => '📕', 'text' => trim($xhs), 'tips' => '标题≤20字，多用 emoji 和话题标签；正文短句分行。'],
        'toutiao' => ['label' => '今日头条', 'icon' => '🟠', 'text' => trim($toutiao), 'tips' => '信息密度高、客观；标题点明事实。可直接粘贴到头条号编辑器。'],
        'baijiahao' => ['label' => '百家号', 'icon' => '🐾', 'text' => trim($baijia), 'tips' => '正式新闻风、注明来源；适合百家号图文。'],
        'zhihu' => ['label' => '知乎', 'icon' => '🔵', 'text' => trim($zhihu), 'tips' => '先给结论再展开；适合作为回答或想法发布。'],
    ];
}
