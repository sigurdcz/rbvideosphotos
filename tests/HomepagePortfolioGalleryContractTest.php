<?php

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use App\View\ViewFactory;

$service = (string)file_get_contents($root . '/app/Services/HomepageContentService.php');
$partial = (string)file_get_contents($root . '/app/Views/partials/home-portfolio.phtml');
$themeJs = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.js');

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expect(str_contains($service, '$stripPhotos = $photos;'), 'Homepage must pass every portfolio photo to the lightbox source.');
$expect(!str_contains($service, 'array_slice($photos, 1'), 'Homepage must not omit the cover photo from gallery data.');
$expect(str_contains($partial, "'src' => (string)(\$photo['fullUrl']"), 'Homepage gallery JSON must preserve full-size media URLs.');
$expect(str_contains($partial, 'class="pt-photos-data"') && !str_contains($partial, 'class="pt-photos"'), 'Homepage must serialize gallery data without hidden image elements.');
$expect(str_contains($partial, 'JSON_HEX_TAG') && str_contains($partial, 'JSON_HEX_AMP'), 'Homepage gallery JSON must be safe inside a script element.');
$expect(str_contains($partial, 'JSON_THROW_ON_ERROR') && !str_contains($partial, "?: '[]'"), 'Gallery serialization errors must be visible instead of silently falling back to an empty gallery.');
$expect(str_contains($themeJs, "card.querySelector('.pt-photos-data')") && str_contains($themeJs, 'JSON.parse(dataNode.textContent'), 'Golden Bash lightbox must consume compact gallery JSON on demand.');
$expect(str_contains($themeJs, "card.querySelectorAll('.pt-photos img')"), 'Golden Bash lightbox must retain compatibility with already-published hidden image galleries.');
$expect(str_contains($themeJs, "card.dataset.galleryError = 'invalid-data'") && str_contains($themeJs, "console.error('Portfolio gallery data is invalid.'"), 'Invalid primary gallery data must be surfaced instead of silently replaced.');
$expect(str_contains($service, 'ThemeTemplateRegistry::isGoldenBashFamily($this->currentThemeKey)'), 'Golden Bash family themes must receive every published gallery while other themes keep their bounded homepage set.');
$expect(str_contains($service, 'refreshPortfolioTiles($blocks, $siteId)') && str_contains($service, 'empty($block[\'autofill_disabled\'])'), 'An enabled DB portfolio autofill block must refresh from current published galleries instead of preserving a stale snapshot.');
$expect(str_contains($themeJs, 'initHomepagePortfolioLoadMore'), 'Golden Bash must initialize progressive homepage gallery batches.');
$expect(str_contains($themeJs, "hiddenCards.slice(0, batchSize)"), 'Each click must reveal one bounded gallery batch.');
$expect(str_contains($themeJs, "card.tabIndex = -1") && str_contains($themeJs, "revealed[0].focus({ preventScroll: true })"), 'Hidden galleries must leave keyboard order and the final reveal must preserve focus.');

$hostileAlt = '</script><script>window.galleryPwned=true</script>&\"\'ěšč';
$rendered = ViewFactory::forTheme(null)->render('partials/home-portfolio', ['tiles' => [[
    'href' => '/nase-prace/test',
    'title' => 'Test galerie',
    'thumbUrl' => '/media/thumb.webp',
    'hasVideo' => false,
    'photos' => [[
        'url' => '/media/thumb.webp',
        'fullUrl' => '/media/full.webp',
        'alt' => $hostileAlt,
    ]],
    'videoEmbedUrl' => '',
    'date' => '',
    'description' => '',
    'author' => '',
]]]);
$expect(substr_count($rendered, '<img') === 1, 'Initial homepage markup must contain only the visible cover image, not hidden gallery images.');
$expect(!str_contains($rendered, '</script><script>window.galleryPwned'), 'Gallery JSON must not allow a script-closing injection.');
if (preg_match('~<script type="application/json" class="pt-photos-data">(.*?)</script>~s', $rendered, $match) === 1) {
    $decoded = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    $expect(($decoded[0]['src'] ?? '') === '/media/full.webp', 'Rendered gallery JSON must retain the original full-size URL.');
    $expect(($decoded[0]['alt'] ?? '') === $hostileAlt, 'Rendered gallery JSON must round-trip escaped Unicode and hostile alt text.');
} else {
    $expect(false, 'Rendered homepage must contain one gallery JSON payload.');
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Homepage portfolio gallery contract OK\n";
