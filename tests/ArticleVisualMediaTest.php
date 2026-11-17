<?php

declare(strict_types=1);

use App\Services\Render\ArticleVisual;

$test('article visual focus defaults and clamps safely', function () use ($assert): void {
    $assert(ArticleVisual::focusY([], 'hero') === 50);
    $assert(ArticleVisual::focusY(['hero_focus_y' => null], 'hero') === 50);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 0], 'hero') === 0);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 73], 'hero') === 73);
    $assert(ArticleVisual::focusY(['hero_focus_y' => 150], 'hero') === 100);
});

$test('article visual migration is append-only and covers both article types', function () use ($assert): void {
    $sql = (string)file_get_contents(dirname(__DIR__) . '/database/migrations/132_add_article_visual_media_controls.sql');
    foreach (['blog_posts', 'portfolio_entries', 'hero_image_id', 'thumbnail_image_id', 'hero_focus_y', 'thumbnail_focus_y'] as $needle) {
        $assert(str_contains($sql, $needle), 'Migration is missing ' . $needle);
    }
    $assert(substr_count($sql, 'ON DELETE SET NULL') === 4, 'Every optional media reference must clear safely on media deletion.');
});

$test('article visual admin exposes two pickers, focal controls and six previews', function () use ($assert): void {
    $view = (string)file_get_contents(dirname(__DIR__) . '/admin/views/partials/article-visual-media.php');
    $assert(str_contains($view, 'name="hero_image_id"'));
    $assert(str_contains($view, 'name="thumbnail_image_id"'));
    $assert(str_contains($view, 'name="hero_focus_y"'));
    $assert(str_contains($view, 'name="thumbnail_focus_y"'));
    $assert(str_contains($view, "['desktop', 'tablet', 'mobile']"), 'Expected desktop, tablet and mobile preview variants.');
    $assert(str_contains($view, "['hero' => 'Hero', 'thumbnail' => 'Thumbnail']"), 'Expected previews for both surfaces.');
});
