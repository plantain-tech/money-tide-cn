<?php

declare(strict_types=1);

/**
 * Week 6 Day 4: Share / OG image generator.
 * Renders SVG share cards server-side (no GD dependency). SVG is crisp,
 * scalable, and usable directly as an og:image or downloaded as an asset.
 */

function share_card_types(): array
{
    return [
        'headline' => '标题卡',
        'summary' => '60秒看懂卡',
        'quote' => '金句 / 关键数字卡',
    ];
}

function share_card_palette(string $categorySlug): array
{
    // Each category gets its own accent so cards are recognizable at a glance.
    $map = [
        'markets' => ['bg' => '#0a0a0a', 'accent' => '#dcff00', 'fg' => '#ffffff'],
        'business' => ['bg' => '#10243e', 'accent' => '#7ad7ff', 'fg' => '#ffffff'],
        'tech' => ['bg' => '#161032', 'accent' => '#c9b8ff', 'fg' => '#ffffff'],
        'crypto' => ['bg' => '#241803', 'accent' => '#ffb347', 'fg' => '#ffffff'],
        'policy' => ['bg' => '#2a0f12', 'accent' => '#ff8a80', 'fg' => '#ffffff'],
        'world' => ['bg' => '#06231d', 'accent' => '#6ee7b7', 'fg' => '#ffffff'],
        'wealth' => ['bg' => '#1c1a05', 'accent' => '#f4e04d', 'fg' => '#ffffff'],
        'global-china' => ['bg' => '#2a0a0a', 'accent' => '#ff6b6b', 'fg' => '#ffffff'],
    ];
    return $map[$categorySlug] ?? ['bg' => '#0a0a0a', 'accent' => '#dcff00', 'fg' => '#ffffff'];
}

/**
 * Wrap a CJK/Latin mixed string into lines of roughly $perLine display cells.
 * CJK chars count as 1, ASCII counts as ~0.5.
 */
function share_card_wrap(string $text, int $perLine, int $maxLines): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return [];
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $lines = [];
    $current = '';
    $width = 0.0;
    foreach ($chars as $ch) {
        $cell = preg_match('/[\x{4e00}-\x{9fff}\x{3000}-\x{303f}\x{ff00}-\x{ffef}]/u', $ch) ? 1.0 : 0.55;
        if ($width + $cell > $perLine && $current !== '') {
            $lines[] = $current;
            $current = '';
            $width = 0.0;
            if (count($lines) >= $maxLines) {
                break;
            }
        }
        $current .= $ch;
        $width += $cell;
    }
    if ($current !== '' && count($lines) < $maxLines) {
        $lines[] = $current;
    }
    // Ellipsis if truncated
    if (count($lines) >= $maxLines) {
        $consumed = mb_strlen(implode('', $lines), 'UTF-8');
        if ($consumed < mb_strlen($text, 'UTF-8')) {
            $last = $lines[$maxLines - 1] ?? '';
            $lines[$maxLines - 1] = mb_substr($last, 0, max(0, mb_strlen($last, 'UTF-8') - 1), 'UTF-8') . '…';
        }
    }
    return $lines;
}

function svg_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function render_share_card_svg(array $article, string $type, array $shortFormat = []): string
{
    $category = (string) ($article['category'] ?? $article['category_slug'] ?? '');
    $categoryName = (string) ($article['category_name'] ?? '');
    $pal = share_card_palette($category);
    $W = 1200;
    $H = 630;
    $bg = $pal['bg'];
    $accent = $pal['accent'];
    $fg = $pal['fg'];

    $brand = '钱潮 MONEY TIDE';
    $catLabel = svg_escape(($categoryName !== '' ? $categoryName : '钱潮早报'));

    $fontStack = "'PingFang SC','Microsoft YaHei','Noto Sans SC',-apple-system,BlinkMacSystemFont,sans-serif";

    $bodySvg = '';

    if ($type === 'summary') {
        $title = (string) ($article['title'] ?? '');
        $bullets = [];
        if (!empty($shortFormat['bullets']) && is_array($shortFormat['bullets'])) {
            $bullets = array_slice($shortFormat['bullets'], 0, 3);
        }
        if (!$bullets) {
            $bullets = array_filter([
                (string) ($article['brief'] ?? ''),
                (string) ($article['why_it_matters'] ?? ''),
            ]);
            $bullets = array_slice($bullets, 0, 3);
        }
        $titleLines = share_card_wrap($title, 16, 2);
        $y = 200;
        foreach ($titleLines as $line) {
            $bodySvg .= '<text x="80" y="' . $y . '" font-size="50" font-weight="900" fill="' . $fg . '" font-family="' . $fontStack . '">' . svg_escape($line) . '</text>';
            $y += 64;
        }
        $y += 24;
        foreach ($bullets as $b) {
            $bLines = share_card_wrap((string) $b, 30, 2);
            $first = true;
            foreach ($bLines as $bl) {
                $prefix = $first ? '●  ' : '     ';
                $bodySvg .= '<text x="80" y="' . $y . '" font-size="30" fill="' . $fg . '" opacity="0.92" font-family="' . $fontStack . '">' . svg_escape($prefix . $bl) . '</text>';
                $y += 44;
                $first = false;
            }
            $y += 8;
        }
        $kicker = '60 秒看懂';
    } elseif ($type === 'quote') {
        $keyNumber = (string) ($shortFormat['key_number'] ?? '');
        $quote = $keyNumber !== '' ? $keyNumber : (string) ($article['brief'] ?? $article['dek'] ?? '');
        $isBigNumber = $keyNumber !== '' && mb_strlen($keyNumber, 'UTF-8') <= 14;
        if ($isBigNumber) {
            $bodySvg .= '<text x="80" y="340" font-size="120" font-weight="900" fill="' . $accent . '" font-family="' . $fontStack . '">' . svg_escape($keyNumber) . '</text>';
            $whyLines = share_card_wrap((string) ($article['why_it_matters'] ?? $article['brief'] ?? ''), 28, 2);
            $y = 430;
            foreach ($whyLines as $line) {
                $bodySvg .= '<text x="80" y="' . $y . '" font-size="34" fill="' . $fg . '" opacity="0.92" font-family="' . $fontStack . '">' . svg_escape($line) . '</text>';
                $y += 48;
            }
        } else {
            $quoteLines = share_card_wrap($quote, 18, 4);
            $y = 230;
            foreach ($quoteLines as $line) {
                $bodySvg .= '<text x="80" y="' . $y . '" font-size="48" font-weight="800" fill="' . $fg . '" font-family="' . $fontStack . '">' . svg_escape($line) . '</text>';
                $y += 66;
            }
        }
        $kicker = '关键数字';
    } else {
        // headline (default)
        $title = (string) ($article['title'] ?? '');
        $dek = (string) ($article['dek'] ?? '');
        $titleLines = share_card_wrap($title, 15, 3);
        $y = 250;
        foreach ($titleLines as $line) {
            $bodySvg .= '<text x="80" y="' . $y . '" font-size="62" font-weight="900" fill="' . $fg . '" font-family="' . $fontStack . '">' . svg_escape($line) . '</text>';
            $y += 80;
        }
        $y += 10;
        $dekLines = share_card_wrap($dek, 30, 2);
        foreach ($dekLines as $line) {
            $bodySvg .= '<text x="80" y="' . $y . '" font-size="30" fill="' . $fg . '" opacity="0.85" font-family="' . $fontStack . '">' . svg_escape($line) . '</text>';
            $y += 44;
        }
        $kicker = '深度解读';
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$W}" height="{$H}" viewBox="0 0 {$W} {$H}">
  <rect width="{$W}" height="{$H}" fill="{$bg}"/>
  <rect x="0" y="0" width="14" height="{$H}" fill="{$accent}"/>
  <rect x="80" y="64" width="200" height="6" fill="{$accent}"/>
  <text x="80" y="118" font-size="26" font-weight="900" letter-spacing="6" fill="{$accent}" font-family="{$fontStack}">{$kicker}</text>
  <text x="80" y="150" font-size="20" letter-spacing="2" fill="{$fg}" opacity="0.7" font-family="{$fontStack}">{$catLabel}</text>
  {$bodySvg}
  <text x="80" y="575" font-size="26" font-weight="900" letter-spacing="3" fill="{$fg}" font-family="{$fontStack}">{$brand}</text>
  <text x="80" y="600" font-size="18" fill="{$fg}" opacity="0.6" font-family="{$fontStack}">moneytidecn.avanturadeals.com</text>
</svg>
SVG;
}

function ensure_share_image_column(): void
{
    $pdo = db();
    if (!$pdo instanceof PDO) {
        return;
    }
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'social_image_path'")->fetchAll();
        if (!$cols) {
            $pdo->exec("ALTER TABLE articles ADD COLUMN social_image_path VARCHAR(255) NULL AFTER hero_image_alt");
        }
    } catch (Throwable $exception) {
    }
}

/**
 * Resolve the OG / social image for an article:
 *   explicit social override → hero image → category fallback → generated card route.
 */
function article_social_image_url(array $article): string
{
    $override = trim((string) ($article['social_image_path'] ?? ''));
    if ($override !== '') {
        if (preg_match('#^https?://#i', $override)) {
            return $override;
        }
        return function_exists('canonical_url') ? canonical_url(ltrim($override, '/')) : $override;
    }
    $hero = trim((string) ($article['hero_image_path'] ?? ''));
    if ($hero !== '') {
        return function_exists('article_media_url') ? article_media_url($article) : $hero;
    }
    // Fall back to the branded raster OG banner. NOTE: the generated headline
    // card is SVG, which X/Facebook/WeChat/知乎 all REJECT for link-preview
    // images (they only render PNG/JPG/WEBP/GIF). So an article with no hero
    // image must use the PNG here, or its social card shows no banner at all.
    // The SVG /share-card/{slug}/headline.svg route still exists for the admin
    // preview + manual download — it's just never used as og:image.
    return function_exists('default_og_image') ? default_og_image() : '';
}
