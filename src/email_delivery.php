<?php

declare(strict_types=1);

function email_provider_catalog(): array
{
    return [
        'brevo' => [
            'label' => 'Brevo',
            'free_limit' => '300 封/天',
            'cost' => '免费计划可长期使用，无需信用卡',
            'fit' => '最适合当前阶段的钱潮早报：免费额度最高，同时支持 API、SMTP、联系人和基础统计。',
            'setup_url' => 'https://www.brevo.com/products/transactional-email/',
        ],
        'resend' => [
            'label' => 'Resend',
            'free_limit' => '100 封/天，3,000 封/月',
            'cost' => '免费计划可用，但每日额度更低',
            'fit' => '开发体验很好，适合 transactional email；如果早报订阅增长较快，免费额度会更早碰到上限。',
            'setup_url' => 'https://resend.com/docs/knowledge-base/what-is-resend-pricing',
        ],
        'mailgun' => [
            'label' => 'Mailgun',
            'free_limit' => '100 封/天',
            'cost' => '免费计划可用，但更偏 transactional 场景',
            'fit' => '可靠，但当前项目不如 Brevo 适合 newsletter-first 的免费启动。',
            'setup_url' => 'https://help.mailgun.com/hc/en-us/articles/203068914-What-does-the-Free-plan-offer',
        ],
        'log' => [
            'label' => 'Log',
            'free_limit' => '不限',
            'cost' => '本地/测试模式',
            'fit' => '只记录，不真实发送；适合开发测试，不适合正式运营。',
            'setup_url' => '',
        ],
    ];
}

function recommended_email_provider(): array
{
    return email_provider_catalog()['brevo'];
}

function email_delivery_status(): array
{
    $providerStatus = email_provider_status();
    $provider = (string) ($providerStatus['provider'] ?? 'log');
    $catalog = email_provider_catalog();
    $providerMeta = $catalog[$provider] ?? $catalog['log'];
    $fromAddress = (string) ($providerStatus['from_address'] ?? '');
    $fromDomain = '';
    if (strpos($fromAddress, '@') !== false) {
        $fromDomain = substr(strrchr($fromAddress, '@') ?: '', 1);
    }
    $unsubscribeSecret = (string) app_config('security.unsubscribe_secret', '');

    $checks = [
        [
            'label' => '已选择真实邮件服务商',
            'ok' => $provider !== 'log',
            'tip' => '建议设置 EMAIL_PROVIDER=brevo。当前 log 模式不会真正发出邮件。',
        ],
        [
            'label' => 'API Key 已配置',
            'ok' => (string) app_config('email.api_key', '') !== '',
            'tip' => '在 GitHub Actions Secrets 中设置 EMAIL_API_KEY。',
        ],
        [
            'label' => '发件邮箱已配置',
            'ok' => filter_var($fromAddress, FILTER_VALIDATE_EMAIL) !== false,
            'tip' => '设置 EMAIL_FROM_ADDRESS，例如 news@yourdomain.com。',
        ],
        [
            'label' => '发件域名可识别',
            'ok' => $fromDomain !== '',
            'tip' => '发件邮箱必须属于你能添加 DNS 记录的域名。',
        ],
        [
            'label' => '退订密钥已设置',
            'ok' => $unsubscribeSecret !== '',
            'tip' => '设置 UNSUBSCRIBE_SECRET，建议 32 位以上随机字符串。',
        ],
        [
            'label' => '退订令牌功能可用',
            'ok' => function_exists('generate_unsubscribe_token') && function_exists('verify_unsubscribe_token'),
            'tip' => '测试邮件会自动带一键退订链接。',
        ],
    ];
    if ($provider === 'mailgun') {
        $checks[] = [
            'label' => 'Mailgun Domain 已配置',
            'ok' => (string) app_config('email.mailgun_domain', '') !== '',
            'tip' => 'Mailgun 需要额外设置 MAILGUN_DOMAIN。',
        ];
    }

    $readyForRealSend = $provider !== 'log' && !empty($providerStatus['ready']);

    return [
        'provider' => $provider,
        'provider_meta' => $providerMeta,
        'provider_status' => $providerStatus,
        'recommended' => recommended_email_provider(),
        'checks' => $checks,
        'ready_for_real_send' => $readyForRealSend,
        'from_domain' => $fromDomain,
    ];
}

function send_email_delivery_test(string $toEmail): array
{
    $toEmail = trim($toEmail);
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => '请输入有效的测试邮箱。'];
    }

    $provider = (string) app_config('email.provider', 'log');
    $baseUrl = rtrim((string) app_config('app_url', ''), '/');
    if ($baseUrl === '') {
        $baseUrl = app_url('');
    }
    $unsubscribeUrl = '';
    if (function_exists('generate_unsubscribe_token')) {
        $unsubscribeUrl = $baseUrl . '/unsubscribe?token=' . rawurlencode(generate_unsubscribe_token($toEmail));
    }
    $subject = '钱潮 Money Tide 邮件投递测试';
    $unsubscribeHtml = $unsubscribeUrl !== ''
        ? '<p><a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0a0a0a;">测试退订链接</a></p>'
        : '';
    $html = '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>' . $subject . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f6f4ee;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,Microsoft YaHei,sans-serif;color:#0a0a0a;">'
        . '<div style="max-width:600px;margin:0 auto;background:#fff;border:2px solid #0a0a0a;padding:24px;">'
        . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#666;">钱潮 Money Tide</p>'
        . '<h1 style="margin:0 0 16px;font-size:26px;">邮件投递测试成功</h1>'
        . '<p style="line-height:1.7;">如果你收到这封邮件，说明生产环境已经可以通过当前邮件服务商发送邮件。</p>'
        . '<p style="line-height:1.7;">下一步可以回到后台创建一版测试早报，先发给自己，再人工广播给订阅用户。</p>'
        . $unsubscribeHtml
        . '<p style="margin-top:20px;font-size:12px;color:#666;">本文内容仅供参考，不构成投资建议。</p>'
        . '</div></body></html>';

    $result = send_email_via_provider($toEmail, $subject, $html);
    if (!$result['ok']) {
        return $result;
    }
    return [
        'ok' => true,
        'message' => $provider === 'log'
            ? '测试已通过，但当前是 log 模式：系统只记录，不会真正发出邮件。'
            : '测试邮件已从生产环境发出，请检查收件箱和垃圾邮件箱。',
        'unsubscribe_url' => $unsubscribeUrl,
    ];
}
