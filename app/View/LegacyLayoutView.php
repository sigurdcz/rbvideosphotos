<?php

declare(strict_types=1);

namespace App\View;

/**
 * Adapter for legacy `layout.html` themes.
 *
 * Reproduces PageRenderer::applyLayout() placeholder substitution exactly so a
 * theme that still ships a legacy `layout.html` ({page_content}/{nav}/{site_name})
 * can be rendered through the new view pipeline without behaviour change.
 *
 * IMPORTANT: the substitution order and escaping MUST stay byte-identical to
 * PageRenderer::applyLayout() - content first, then nav, then the escaped site
 * name (htmlspecialchars ENT_QUOTES, UTF-8). This parity is asserted in
 * tests/ViewEngineTest.php.
 */
final class LegacyLayoutView
{
    public function render(string $layoutHtml, string $content, string $siteName, string $nav = ''): string
    {
        $safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $result = str_replace('{page_content}', $content, $layoutHtml);
        $result = str_replace('{nav}', $nav, $result);
        $result = str_replace('{site_name}', $safeName, $result);

        return $result;
    }
}
