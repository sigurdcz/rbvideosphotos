param(
    [string]$ReferenceRoot = 'ukazka sablony premim photo',
    [string]$ThemeRoot = 'Templates/premium-photo'
)

$ErrorActionPreference = 'Stop'
$utf8NoBom = [Text.UTF8Encoding]::new($false)
$pages = [ordered]@{
    'index.html' = 'homepage.phtml'
    'nase-prace.html' = 'nase-prace.phtml'
    'blog.html' = 'blog.phtml'
    'recenze.html' = 'hodnoceni.phtml'
    'faq.html' = 'faq.phtml'
    'kontakt.html' = 'kontakt.phtml'
}
$overrideLayouts = [ordered]@{
    'nase-prace.html' = 'portfolio.html'
    'blog.html' = 'blog.html'
    'blog-clanek.html' = 'blog-post.html'
    'recenze.html' = 'reviews.html'
    'faq.html' = 'faq.html'
    'kontakt.html' = 'contact.html'
}

$required = @(
    (Join-Path $ReferenceRoot 'assets/style.css'),
    (Join-Path $ReferenceRoot 'assets/main.js')
) + (($pages.Keys + $overrideLayouts.Keys) | Sort-Object -Unique | ForEach-Object { Join-Path $ReferenceRoot $_ })

$missing = @($required | Where-Object { -not (Test-Path -LiteralPath $_) })
if ($missing.Count -gt 0) {
    throw "Chybi referencni soubory: $($missing -join ', ')"
}

$viewsRoot = Join-Path $ThemeRoot 'views/pages'
New-Item -ItemType Directory -Force -Path $viewsRoot | Out-Null
$overridesRoot = Join-Path $ThemeRoot 'overrides'
New-Item -ItemType Directory -Force -Path $overridesRoot | Out-Null

Copy-Item -LiteralPath (Join-Path $ReferenceRoot 'assets/style.css') -Destination (Join-Path $ThemeRoot 'assets/theme.css') -Force
Copy-Item -LiteralPath (Join-Path $ReferenceRoot 'assets/main.js') -Destination (Join-Path $ThemeRoot 'assets/theme.js') -Force

$linkMap = [ordered]@{
    'index.html' = '/'
    'nase-prace.html' = '/nase-prace'
    'blog-clanek.html' = '/blog/jak-sestavit-harmonogram-svatebniho-dne'
    'blog.html' = '/blog'
    'recenze.html' = '/hodnoceni'
    'faq.html' = '/faq'
    'kontakt.html' = '/kontakt'
}

foreach ($entry in $pages.GetEnumerator()) {
    $sourcePath = Join-Path $ReferenceRoot $entry.Key
    $html = [IO.File]::ReadAllText([IO.Path]::GetFullPath($sourcePath), [Text.Encoding]::UTF8)
    $match = [regex]::Match($html, '(?is)<main[^>]*>(?<content>.*)</main>')
    if (-not $match.Success) {
        throw "V $sourcePath chybi element <main>."
    }

    $content = $match.Groups['content'].Value.Trim()
    foreach ($link in $linkMap.GetEnumerator()) {
        $content = $content.Replace($link.Key, $link.Value)
    }
    $content = $content.Replace('<iframe ', '<iframe class="theme-hero-video" ')
    $content = $content.Replace('<nav class="crumbs">', '<nav class="crumbs" data-cms-breadcrumbs>')
    if ($entry.Key -eq 'index.html') {
        # Core composer injects these sections when their IDs are absent. The
        # reference already contains its own pricing-free flow and timeline.
        $content = $content.Replace(
            '<section id="galerie"',
            '<section id="cenik" hidden></section>' + "`n" +
            '<section id="proces" hidden></section>' + "`n" +
            '<section id="galerie"'
        )
    }

    $targetPath = Join-Path $viewsRoot $entry.Value
    [IO.File]::WriteAllText([IO.Path]::GetFullPath($targetPath), $content + "`n", $utf8NoBom)
}

foreach ($entry in $overrideLayouts.GetEnumerator()) {
    $sourcePath = Join-Path $ReferenceRoot $entry.Key
    $html = [IO.File]::ReadAllText([IO.Path]::GetFullPath($sourcePath), [Text.Encoding]::UTF8)
    $html = [regex]::Replace($html, '(?im)^<link rel="stylesheet" href="assets/style\.css">\s*$', '')
    $html = [regex]::Replace($html, '(?im)^<script src="assets/main\.js"></script>\s*$', '')
    foreach ($link in $linkMap.GetEnumerator()) {
        $html = $html.Replace($link.Key, $link.Value)
    }
    $html = $html.Replace('<iframe ', '<iframe class="theme-hero-video" ')
    $html = $html.Replace('<nav class="crumbs">', '<nav class="crumbs" data-cms-breadcrumbs>')
    $html = $html.Replace('<footer class="site-footer">', '<div class="site-footer" role="contentinfo">')
    $html = $html.Replace('</footer>', '</div>')
    $targetPath = Join-Path $overridesRoot $entry.Value
    [IO.File]::WriteAllText([IO.Path]::GetFullPath($targetPath), $html.Trim() + "`n", $utf8NoBom)
}

Write-Output "Premium Photo synchronized: $($pages.Count) page views, $($overrideLayouts.Count) route layouts, CSS + JS."
