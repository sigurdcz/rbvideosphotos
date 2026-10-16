<?php

declare(strict_types=1);

use App\Services\SeoRenderer;

function register_content_seo_remediation_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('content SEO seed separates service and editorial search intent', function () use ($assert, $root): void {
        $source = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        foreach ([
            'Svatební foto a video Olomouc | RB Videos & Photos',
            'Jak probíhá svatební focení v Olomouci',
            'Firemní foto a reklamní video Olomouc | RB Videos & Photos',
            'Jak připravit reklamní focení a video pro firmu',
            'Tipy pro svatební, párové a rodinné focení',
            'Reference a hodnocení klientů',
        ] as $copy) {
            $assert(str_contains($source, $copy), 'Missing approved SEO copy: ' . $copy);
        }
        $assert(str_contains($source, "'sections' => ["), 'Editorial articles are not split into useful topic sections.');
        $assert(str_contains($source, '$this->seedBlogPosts($siteId, false)'), 'Production remediation may silently generate placeholder article media.');
        $assert(str_contains($source, '$this->db->beginTransaction()'), 'Content remediation is not atomic.');
    });

    $test('obsolete meta keywords are not rendered', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test title',
                'meta_description' => 'Test description',
                'meta_keywords' => 'obsolete, keywords',
                'robots_index' => 1,
                'robots_follow' => 1,
            ],
            ['name' => 'Test', 'domain' => 'https://example.test'],
            'https://example.test/test',
            null,
            null
        );
        $assert(!str_contains($html, 'name="keywords"'), 'Renderer still publishes obsolete meta keywords.');
    });

    $test('article detail templates use compact contextual CTA instead of repeated process copy', function () use ($assert, $root): void {
        $blog = (string)file_get_contents($root . '/Templates/golden-bash/views/archetypes/blog-detail.phtml');
        $portfolio = (string)file_get_contents($root . '/Templates/golden-bash/views/archetypes/portfolio-detail.phtml');
        $shared = (string)file_get_contents($root . '/app/Services/Render/SharedProcessRenderer.php');
        $assert(str_contains($blog, 'gb-route-cta') && str_contains($blog, '/sluzby'));
        $assert(str_contains($portfolio, 'gb-route-cta') && str_contains($portfolio, '/cenik'));
        $assert(str_contains($shared, 'class="gb-article"'));
        $assert(str_contains($shared, 'class="pf-main gb-work-detail"'));
    });

    $test('about page renders its audited H1 from DB content', function () use ($assert, $root): void {
        $view = (string)file_get_contents($root . '/Templates/golden-bash/views/pages/o-nas.phtml');
        $assert(str_contains($view, "\$blockMap['hero_h1']"));
        $assert(str_contains($view, '$this->e($heroHeading)'));
        $assert(!str_contains($view, '<h1>O nás</h1>'));
    });

    $test('content migration is append-only and wired for production upgrade and clean reset', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/055_apply_content_seo_remediation.sql');
        $dispatcher = (string)file_get_contents($root . '/app/Services/ApplicationCompatibleMigrationService.php');
        $setup = (string)file_get_contents($root . '/app/Services/SetupEnvironmentService.php');
        $assert(str_contains($migration, 'SET @content_seo_remediation_marker = 1;'));
        $assert(str_contains($dispatcher, "'055_apply_content_seo_remediation.sql'"));
        $assert(str_contains($dispatcher, 'restoreContentSeoRemediation'));
        $assert(str_contains($setup, 'requiresExistingDemoContent'));
    });
}
