<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

// Explicit synthetic repaint fixture, not a substitute for the user's video.
$html = '<html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="theme-golden-bash theme-golden-bash-diagonal" data-cms-page-key="homepage">'
    . '<div class="gb-shell"><header class="gb-header"><nav class="gb-nav"><div class="nav-links"><a href="#first">First</a><a href="#second">Second</a></div></nav></header>'
    . '<main class="gb-main"><section class="hp-hero"><div class="theme-hero-video theme-hero-video--embed is-playing" style="--hero-video-cover-width:1280px">'
    . '<iframe title="Synthetic changing surface" srcdoc="&lt;body style=&quot;margin:0;background:red&quot;&gt;&lt;/body&gt;"></iframe></div>'
    . '<div class="container"><h1>Hero heading</h1><div class="hero-cta-wrap"><a class="gb-button" href="#first">First action</a><a class="gb-button" href="#second">Second action</a></div></div>'
    . '</section></main></div></body></html>';
$html = App\Support\FrontendAssetManager::injectGeneratedCss($html,
    file_get_contents(dirname(__DIR__) . '/Templates/golden-bash-diagonal/assets/theme.css'), 'page-css', 'data-generated-page-css');
$html = App\Support\PageCssSubsetter::apply($html);
preg_match_all('~<link[^>]*href="([^"]+\.css)"[^>]*>~', $html, $links);
$css = '';
foreach ($links[1] as $url) {
    $path = dirname(__DIR__) . '/public' . $url;
    if (!is_file($path)) throw new RuntimeException('Generated fixture CSS missing.');
    $css .= file_get_contents($path);
}
if ($css === '') throw new RuntimeException('No generated fixture CSS.');
echo json_encode(['html' => $html, 'css' => $css, 'assets' => $links[1]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
