<?php

declare(strict_types=1);

use App\Services\Render\ArticleVisual;

$test('article visual focus defaults and clamps safely', function () use ($assert): void {
    $assert(ArticleVisual::focusY([], 'hero') === 50);
    $assert(ArticleVisual::focusY(['hero_focus_y' => null], 'hero') === 50);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 0], 'hero') === 0);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 73], 'hero') === 73);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 150], 'hero') === 100);
    $legacy = ['hero_focus_y' => 24, 'thumbnail_focus_y' => 80];
    $assert(ArticleVisual::focusX($legacy, 'hero') === 50);
    $assert(ArticleVisual::focusX($legacy, 'thumbnail') === 50);
    $assert(ArticleVisual::focusX(['hero_focus_x' => 0], 'hero') === 0);
    $assert(ArticleVisual::focusX(['thumbnail_focus_x' => 120], 'thumbnail') === 100);
    $both = $legacy + ['hero_focus_x' => 90, 'thumbnail_focus_x' => 10];
    $assert(ArticleVisual::focusX($both, 'hero') === 90 && ArticleVisual::focusY($both, 'hero') === 24);
    $assert(ArticleVisual::focusX($both, 'thumbnail') === 10 && ArticleVisual::focusY($both, 'thumbnail') === 80);
});

$test('article visual migration is append-only and covers both article types', function () use ($assert): void {
    $sql = (string)file_get_contents(dirname(__DIR__) . '/database/migrations/132_add_article_visual_media_controls.sql');
    foreach (['blog_posts', 'portfolio_entries', 'hero_image_id', 'thumbnail_image_id', 'hero_focus_y', 'thumbnail_focus_y'] as $needle) {
        $assert(str_contains($sql, $needle), 'Migration is missing ' . $needle);
    }
    $assert(substr_count($sql, 'ON DELETE SET NULL') === 4, 'Every optional media reference must clear safely on media deletion.');
    $horizontal = (string)file_get_contents(dirname(__DIR__) . '/database/migrations/133_add_article_horizontal_focus.sql');
    foreach (['blog_posts', 'portfolio_entries'] as $table) {
        $assert(str_contains($horizontal, $table), 'Horizontal migration is missing ' . $table);
    }
    foreach (['hero_focus_x', 'thumbnail_focus_x'] as $column) {
        $assert(substr_count($horizontal, 'ADD COLUMN `' . $column . '` TINYINT UNSIGNED DEFAULT NULL') === 2, 'Both tables need a nullable ' . $column . ' column.');
    }
});

$test('article visual admin exposes two pickers, focal controls and six previews', function () use ($assert): void {
    $view = (string)file_get_contents(dirname(__DIR__) . '/admin/views/partials/article-visual-media.php');
    $blogForm = (string)file_get_contents(dirname(__DIR__) . '/admin/views/blog/form.php');
    $portfolioForm = (string)file_get_contents(dirname(__DIR__) . '/admin/views/portfolio/form.php');
    $assert(str_contains($view, 'name="<?= $visualKey ?>_image_id"'), 'Expected a media input for each visual surface.');
    $assert(str_contains($view, 'name="<?= $visualKey ?>_focus_<?= $axis ?>"'), 'Expected a stored field per surface and axis.');
    $assert(str_contains($view, "['x' => 'Vodorovná poloha', 'y' => 'Svislá poloha']"), 'The position must read horizontal before vertical.');
    foreach (['desktop', 'tablet', 'mobile'] as $device) {
        $assert(str_contains($view, "'{$device}' =>"), 'Expected ' . $device . ' preview variant.');
    }
    $assert(str_contains($view, "['hero' => 'Hero', 'thumbnail' => 'Thumbnail']"), 'Expected previews for both surfaces.');
    $assert(str_contains($blogForm, "\$articleVisualContext = 'blog'"), 'Blog form must select blog preview geometry.');
    $assert(str_contains($portfolioForm, "\$articleVisualContext = 'portfolio'"), 'Portfolio form must select portfolio preview geometry.');
});

$test('article visual preview geometry mirrors public responsive crop frames', function () use ($assert): void {
    $css = (string)file_get_contents(dirname(__DIR__) . '/admin/assets/admin.css');
    $publicCss = (string)file_get_contents(dirname(__DIR__) . '/Templates/golden-bash/assets/theme.css');
    foreach ([
        '1440/590', '768/590', '390/480',
        '429/220', '335/220', '358/210',
        '664/230', '736/260',
    ] as $ratio) {
        $assert(str_contains($css, 'aspect-ratio:' . $ratio . '!important'), 'Missing preview ratio ' . $ratio);
    }
    $overridePosition = strrpos($css, 'aspect-ratio:390/480!important');
    $legacyMobilePosition = strrpos($css, 'aspect-ratio:16/9!important');
    $assert($overridePosition !== false && $legacyMobilePosition !== false && $overridePosition > $legacyMobilePosition,
        'Exact simulated viewport ratios must override the admin viewport media query.');
    foreach ([
        '.gb-blog-listing .bl-thumb{display:block;height:220px',
        '.gb-blog-listing .bl-thumb{height:210px}',
        'grid-template-columns:repeat(4,minmax(0,1fr))!important;grid-template-rows:repeat(4,230px)',
        'grid-auto-rows:260px!important',
        'grid-auto-rows:220px!important',
    ] as $publicContract) {
        $assert(str_contains($publicCss, $publicContract), 'Public crop geometry changed without updating admin previews: ' . $publicContract);
    }
});
