<?php

$root = dirname(__DIR__);
$service = (string)file_get_contents($root . '/app/Services/HomepageContentService.php');
$partial = (string)file_get_contents($root . '/app/Views/partials/home-portfolio.phtml');
$themeJs = (string)file_get_contents($root . '/Templates/golden-bash/assets/theme.js');

$failures = [];
$expect = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expect(str_contains($service, '$stripPhotos = $photos;'), 'Homepage must pass every portfolio photo to the lightbox source.');
$expect(!str_contains($service, 'array_slice($photos, 1'), 'Homepage must not omit the cover photo from gallery data.');
$expect(str_contains($partial, 'data-full='), 'Homepage gallery items must preserve full-size media URLs.');
$expect(str_contains($themeJs, "card.querySelectorAll('.pt-photos img')"), 'Golden Bash lightbox must consume the complete hidden gallery source.');
$expect(str_contains($service, 'ThemeTemplateRegistry::isGoldenBashFamily($this->currentThemeKey)'), 'Golden Bash family themes must receive every published gallery while other themes keep their bounded homepage set.');
$expect(str_contains($service, 'refreshPortfolioTiles($blocks, $siteId)') && str_contains($service, 'empty($block[\'autofill_disabled\'])'), 'An enabled DB portfolio autofill block must refresh from current published galleries instead of preserving a stale snapshot.');
$expect(str_contains($themeJs, 'initHomepagePortfolioLoadMore'), 'Golden Bash must initialize progressive homepage gallery batches.');
$expect(str_contains($themeJs, "hiddenCards.slice(0, batchSize)"), 'Each click must reveal one bounded gallery batch.');
$expect(str_contains($themeJs, "card.tabIndex = -1") && str_contains($themeJs, "revealed[0].focus({ preventScroll: true })"), 'Hidden galleries must leave keyboard order and the final reveal must preserve focus.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Homepage portfolio gallery contract OK\n";
