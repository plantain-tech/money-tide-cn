<?php

declare(strict_types=1);

function site_meta(): array
{
    return [
        'name' => '钱潮 Money Tide',
        'tagline' => '每天5分钟，看懂全球市场。',
        'description' => '面向中文读者的全球财经、科技与商业简报平台。',
    ];
}

function get_categories(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $dbCategories = db_categories();
    if ($dbCategories !== null) {
        $cache = $dbCategories;
        return $cache;
    }

    $cache = [
        ['slug' => 'markets', 'name' => '市场', 'summary' => '股票、宏观、利率、外汇与大宗商品。'],
        ['slug' => 'business', 'name' => '商业', 'summary' => '公司、财报、消费品牌与商业模式。'],
        ['slug' => 'tech', 'name' => '科技', 'summary' => 'AI、芯片、平台公司与创业趋势。'],
        ['slug' => 'crypto', 'name' => '加密', 'summary' => '数字资产、ETF、监管与链上市场。'],
        ['slug' => 'policy', 'name' => '政策', 'summary' => '央行、监管、地缘政治与贸易政策。'],
        ['slug' => 'world', 'name' => '全球', 'summary' => '全球经济、供应链与跨境资本流动。'],
        ['slug' => 'wealth', 'name' => '理财', 'summary' => '个人理财、长期投资与风险教育。'],
        ['slug' => 'global-china', 'name' => '出海', 'summary' => '中国公司全球化、品牌出海与海外市场。'],
    ];
    return $cache;
}

function get_articles(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $dbArticles = db_articles();
    if ($dbArticles !== null) {
        $cache = $dbArticles;
        return $cache;
    }

    $cache = [
        [
            'slug' => 'fed-rate-path-global-markets',
            'category' => 'markets',
            'category_name' => '市场',
            'title' => '美联储路径变了，全球资金开始重新定价',
            'dek' => '美元、黄金、科技股与新兴市场都在等待下一次利率信号。',
            'brief' => '利率预期正在成为本周全球资产价格的主线。',
            'why' => '如果美元流动性重新宽松，成长股、黄金和部分亚洲资产都可能获得新的资金关注。',
            'numbers' => ['2次' => '市场押注的潜在降息次数', '5分钟' => '读完本篇所需时间', '3类' => '最敏感资产'],
            'tags' => ['Fed', '美元', '黄金', '美股'],
            'published_at' => '2026-05-25',
            'read_time' => '4分钟',
            'body' => [
                '全球市场现在最关心的不是某一家公司的财报，而是资金价格会不会继续下降。',
                '当利率预期变化时，估值、汇率、商品价格和跨境资金都会被重新计算。对中文读者来说，这也是理解美股、港股和人民币资产联动的关键入口。',
                '钱潮会持续追踪利率、美元和风险资产之间的关系，并把复杂的市场语言翻译成更容易行动的中文简报。',
            ],
        ],
        [
            'slug' => 'ai-chip-race-after-nvidia',
            'category' => 'tech',
            'category_name' => '科技',
            'title' => '英伟达之后，AI芯片战争进入第二阶段',
            'dek' => '云厂商、自研芯片和中国供应链正在重塑AI基础设施。',
            'brief' => 'AI算力竞争正在从单一赢家变成生态系统之争。',
            'why' => '芯片供应决定AI产品成本，也决定云计算和应用公司的利润空间。',
            'numbers' => ['3条' => '主要竞争路线', '1个' => '核心变量是算力成本'],
            'tags' => ['AI', '芯片', '云计算'],
            'published_at' => '2026-05-25',
            'read_time' => '3分钟',
            'body' => [
                'AI芯片不再只是硬件公司的故事，它正在影响云服务价格、模型训练节奏和企业软件竞争。',
                '下一阶段的竞争会发生在供应链、软件生态和客户锁定能力之间。',
            ],
        ],
        [
            'slug' => 'bitcoin-etf-liquidity-signal',
            'category' => 'crypto',
            'category_name' => '加密',
            'title' => '比特币ETF的资金流，正在变成加密市场的新温度计',
            'dek' => '机构资金进出让加密市场更接近传统资产，但波动并没有消失。',
            'brief' => 'ETF资金流已经成为观察比特币需求的重要信号。',
            'why' => '当加密资产进入传统账户体系，散户与机构看到的是同一个价格，但承受的是不同风险。',
            'numbers' => ['24/7' => '加密市场交易时间', '1项' => '资金流是核心指标'],
            'tags' => ['Bitcoin', 'ETF', 'Crypto'],
            'published_at' => '2026-05-24',
            'read_time' => '4分钟',
            'body' => [
                'ETF让更多投资者可以通过熟悉的金融账户接触比特币。',
                '但产品便利性并不等于低风险。钱潮会把价格、资金流和监管变化放在同一张图里观察。',
            ],
        ],
        [
            'slug' => 'chinese-brands-global-growth',
            'category' => 'global-china',
            'category_name' => '出海',
            'title' => '中国品牌出海，从流量战转向本地化耐力赛',
            'dek' => '便宜和速度曾经有效，现在品牌、合规和供应链韧性更重要。',
            'brief' => '出海竞争正在从“卖出去”变成“留下来”。',
            'why' => '跨境电商、EV、消费电子和AI工具都面临更复杂的本地规则与用户信任问题。',
            'numbers' => ['4类' => '重点出海行业', '2个' => '核心挑战是合规与品牌'],
            'tags' => ['出海', '电商', '品牌'],
            'published_at' => '2026-05-24',
            'read_time' => '5分钟',
            'body' => [
                '出海不再只是投广告、铺渠道和打低价。',
                '越来越多市场要求本地团队、数据合规、售后能力和更清晰的品牌承诺。',
            ],
        ],
    ];
    return $cache;
}

function find_category(string $slug): ?array
{
    foreach (get_categories() as $category) {
        if ($category['slug'] === $slug) {
            return $category;
        }
    }
    return null;
}

function find_article(string $slug): ?array
{
    $dbArticle = db_article($slug);
    if ($dbArticle !== null) {
        return $dbArticle;
    }

    foreach (get_articles() as $article) {
        if ($article['slug'] === $slug) {
            return $article;
        }
    }
    return null;
}

function filter_articles_by_category(string $slug): array
{
    $dbArticles = db_articles($slug);
    if ($dbArticles !== null) {
        return $dbArticles;
    }

    return array_values(array_filter(get_articles(), static fn (array $article): bool => $article['category'] === $slug));
}

function related_articles(array $current): array
{
    return array_values(array_filter(get_articles(), static fn (array $article): bool => $article['slug'] !== $current['slug']));
}
