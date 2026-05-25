INSERT INTO articles (category_id, slug, status, title, dek, brief, why_it_matters, body, read_time_minutes, published_at)
SELECT id, 'fed-rate-watch-money-tide', 'published',
       '美联储降息预期又变了，市场为什么还在冲？',
       '交易员正在重新定价利率路径，但股票市场更关心企业盈利和 AI 投资周期。',
       '利率预期摇摆没有终结风险偏好，资金仍在寻找增长确定性。',
       '中文投资者看美股，不能只盯降息时间表，还要同时看盈利、流动性和美元走势。',
       JSON_ARRAY('过去几周，美债收益率和降息预期反复摇摆，但主要股指并没有同步转弱。', '这背后有两个原因：一是大型科技公司的盈利仍然支撑指数，二是市场相信 AI 资本开支会继续带来收入增长。', '对普通读者来说，关键不是预测下一次议息会议，而是观察资金是否还愿意为增长支付高估值。'),
       4, NOW()
FROM categories WHERE slug = 'markets'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO articles (category_id, slug, status, title, dek, brief, why_it_matters, body, read_time_minutes, published_at)
SELECT id, 'ai-capex-china-readers', 'published',
       'AI 公司继续烧钱，真正的赢家可能是谁？',
       '从芯片、云服务到电力基础设施，AI 投资正在把科技新闻变成产业链新闻。',
       'AI 热潮的利润不只在模型公司，也在卖铲子的基础设施公司。',
       '这帮助读者从“哪个模型更强”转向“谁能持续收到订单”。',
       JSON_ARRAY('AI 竞争表面上是模型和应用的竞争，底层却是算力、电力、网络和数据中心的竞争。', '当头部公司继续扩大资本开支，芯片供应商、云平台、服务器制造商和能源服务商都会被拉进同一个增长故事。', '钱潮会持续跟踪这些订单如何传导到上市公司收入，而不只追逐发布会标题。'),
       3, NOW()
FROM categories WHERE slug = 'tech'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO articles (category_id, slug, status, title, dek, brief, why_it_matters, body, read_time_minutes, published_at)
SELECT id, 'chinese-brands-global-pricing', 'published',
       '中国品牌出海，下一场硬仗是定价权',
       '从跨境电商到新能源车，低价打开市场后，品牌需要证明自己能留住利润。',
       '出海不只是卖到海外，更是把毛利率和品牌心智带到海外。',
       '定价权决定中国公司在全球市场是短期流量玩家，还是长期利润玩家。',
       JSON_ARRAY('许多中国品牌已经证明自己能用供应链效率打开海外市场。', '但下一阶段更难：企业需要在本地渠道、售后、合规和品牌信任上持续投入，同时避免被困在低价竞争里。', '对投资者和创业者来说，观察毛利率变化，比单纯观察销售额增速更重要。'),
       4, NOW()
FROM categories WHERE slug = 'global-china'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
