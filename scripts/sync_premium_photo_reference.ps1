param(
    [string]$ReferenceRoot = 'ukazka sablony premim photo',
    [string]$ThemeRoot = 'Templates/premium-photo'
)

$ErrorActionPreference = 'Stop'

$required = @(
    (Join-Path $ReferenceRoot 'assets/style.css'),
    (Join-Path $ReferenceRoot 'assets/main.js'),
    (Join-Path $ReferenceRoot 'index.html'),
    (Join-Path $ReferenceRoot 'nase-prace.html'),
    (Join-Path $ReferenceRoot 'blog.html'),
    (Join-Path $ReferenceRoot 'blog-clanek.html'),
    (Join-Path $ReferenceRoot 'recenze.html'),
    (Join-Path $ReferenceRoot 'faq.html'),
    (Join-Path $ReferenceRoot 'kontakt.html'),
    (Join-Path $ThemeRoot 'template.json'),
    (Join-Path $ThemeRoot 'layout.html'),
    (Join-Path $ThemeRoot 'views/pages/homepage.phtml')
)

$missing = @($required | Where-Object { -not (Test-Path -LiteralPath $_) })
if ($missing.Count -gt 0) {
    throw "Chybi referencni nebo theme soubory: $($missing -join ', ')"
}

$manifest = Get-Content -LiteralPath (Join-Path $ThemeRoot 'template.json') -Raw -Encoding utf8 | ConvertFrom-Json
if ($manifest.layout_html -ne 'layout.html' -or @($manifest.overrides.PSObject.Properties).Count -ne 0) {
    throw 'Premium Photo porusuje DB-first kontrakt: musi pouzivat layout.html bez route layout overrides.'
}

$homepage = Get-Content -LiteralPath (Join-Path $ThemeRoot 'views/pages/homepage.phtml') -Raw -Encoding utf8
if ($homepage -notmatch '\$fragment' -or $homepage -match 'youtube\.com/embed') {
    throw 'Premium Photo homepage musi vykreslit DB fragment a nesmi obsahovat staticke video.'
}

$forbiddenPages = @('blog', 'faq', 'hodnoceni', 'kontakt', 'nase-prace')
$staticPages = @($forbiddenPages | Where-Object { Test-Path -LiteralPath (Join-Path $ThemeRoot "views/pages/$_.phtml") })
if ($staticPages.Count -gt 0) {
    throw "Premium Photo znovu obsahuje staticke page views: $($staticPages -join ', ')"
}

Write-Output 'Premium Photo reference is present and the DB-first theme contract is valid.'
Write-Output 'Designer updates must be ported as presentation-only CSS/JS changes; full reference HTML must never be copied into CMS views.'
