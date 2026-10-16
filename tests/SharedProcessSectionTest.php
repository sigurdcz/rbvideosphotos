<?php

declare(strict_types=1);

use App\Services\Render\SharedProcessRenderer;
use App\Services\ThemeTemplateRegistry;

function register_shared_process_section_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('Golden Bash uses one shared process on core routes and concise CTAs on articles', function () use ($assert, $root): void {
        $partial = (string) file_get_contents($root . '/app/Views/partials/process-cooperation.phtml');
        $migration = (string) file_get_contents($root . '/database/migrations/065_add_portfolio_type_and_shared_sections.sql');
        foreach (['Nezávazná poptávka', 'Konzultace a plán', 'Focení / natáčení', 'Výběr a dodání', 'gb-shared-process__benefits'] as $needle) {
            $assert(str_contains($partial . $migration, $needle), 'Shared process source is missing: ' . $needle);
        }
        foreach (['mail', 'assignment', 'photo_camera', 'video_library', 'chat', 'event_available', 'schedule', 'favorite'] as $icon) {
            $assert(str_contains($partial, "'{$icon}' =>"), 'Shared process SVG map is missing: ' . $icon);
        }
        $assert(substr_count($partial, '<svg class="gb-shared-process__') === 2, 'Shared process must render step and benefit icons through local SVG templates.');
        $assert(!str_contains($partial, 'material-symbols-outlined'), 'Shared process still depends on the inaccurate text-symbol fallback.');

        $legacyShapes = [
            '<main><section class="section hp-process" id="proces"><div>home copy</div></section><section id="kontakt"></section></main>',
            '<main><section class="kt-process"><div>contact copy</div></section><section class="kt-faq"></section></main>',
            '<main><section class="gb-services-process"><div>services copy</div></section><section class="gb-route-cta"></section></main>',
            '<main><section class="gb-about-section gb-about-process"><div>about copy</div></section></main>',
            '<main><section class="gb-route-cta"><div>generic CTA</div></section></main>',
        ];

        foreach (ThemeTemplateRegistry::GOLDEN_BASH_THEME_KEYS as $themeKey) {
            foreach ($legacyShapes as $html) {
                $rendered = SharedProcessRenderer::inject($html, $themeKey);
                $assert(substr_count($rendered, 'class="gb-shared-process"') === 1, 'Route must contain exactly one shared process section for ' . $themeKey . '.');
                $assert(!str_contains($rendered, 'home copy') && !str_contains($rendered, 'contact copy') && !str_contains($rendered, 'services copy') && !str_contains($rendered, 'about copy'), 'Legacy route-specific process markup survived for ' . $themeKey . '.');
                $assert(SharedProcessRenderer::inject($rendered, $themeKey) === $rendered, 'Shared process injection must be idempotent for ' . $themeKey . '.');
            }
        }

        $blogDetail = '<main class="gb-main"><article class="gb-article"><main class="bp-content"><p>Article copy</p></main></article></main>';
        $assert(SharedProcessRenderer::inject($blogDetail, 'golden-bash') === $blogDetail, 'Blog detail must use its concise route CTA instead of the global process block.');
        $assert(SharedProcessRenderer::inject($blogDetail, 'golden-bash-diagonal') === $blogDetail, 'Diagonal blog detail must use its concise route CTA instead of the global process block.');

        $portfolioDetail = '<main class="pf-main gb-work-detail"><p>Portfolio copy</p></main>';
        $assert(SharedProcessRenderer::inject($portfolioDetail, 'golden-bash') === $portfolioDetail, 'Portfolio detail must use its concise route CTA instead of the global process block.');
        $assert(SharedProcessRenderer::inject($portfolioDetail, 'golden-bash-diagonal') === $portfolioDetail, 'Diagonal portfolio detail must use its concise route CTA instead of the global process block.');

        $foreign = '<main><section class="section hp-process">Keep me</section></main>';
        $assert(SharedProcessRenderer::inject($foreign, 'elegant-white') === $foreign, 'Golden Bash process renderer changed another theme.');
    });

    $test('Golden Bash Diagonal is the declared and migration-backed default theme', function () use ($assert, $root): void {
        $assert(ThemeTemplateRegistry::DEFAULT_THEME_KEY === 'golden-bash-diagonal', 'Registry default is not Golden Bash Diagonal.');
        $assert(ThemeTemplateRegistry::GOLDEN_BASH_THEME_KEYS === ['golden-bash', 'golden-bash-diagonal'], 'Golden Bash family keys changed when the default moved.');

        $migration = (string) file_get_contents($root . '/database/migrations/066_set_golden_bash_diagonal_default_theme.sql');
        $assert(str_contains($migration, "DEFAULT 'golden-bash-diagonal'"), 'Migration 066 does not set the Golden Bash Diagonal schema default.');
        $assert(str_contains($migration, "SET `active_theme_key` = 'golden-bash-diagonal'"), 'Migration 066 does not activate the new default.');
        $assert(str_contains($migration, "OR `active_theme_key` = 'golden-bash'"), 'Migration 066 does not upgrade sites still using the former default.');

        $schema = (string) file_get_contents($root . '/database/schema.sql');
        $seed = (string) file_get_contents($root . '/database/seed.sql');
        $assert(str_contains($schema, "DEFAULT 'golden-bash-diagonal'"), 'Clean schema does not use the new default.');
        $assert(str_contains($seed, "'golden-bash-diagonal'"), 'Clean SQL seed does not select the new default.');

        $historicalGolden = (string) file_get_contents($root . '/database/migrations/038_set_golden_bash_default_theme.sql');
        $historicalElegant = (string) file_get_contents($root . '/database/migrations/021_set_elegant_white_default_theme.sql');
        $assert(str_contains($historicalGolden, "DEFAULT 'golden-bash'"), 'Historical migration 038 was rewritten instead of superseded.');
        $assert(str_contains($historicalElegant, "DEFAULT 'elegant-white'"), 'Historical migration 021 was rewritten instead of superseded.');
    });

    $test('Golden Bash FAQ has readable copy without filter tags', function () use ($assert, $root): void {
        $view = (string) file_get_contents($root . '/Templates/golden-bash/views/archetypes/faq.phtml');
        $css = (string) file_get_contents($root . '/Templates/golden-bash/assets/theme.css');
        foreach (['gb-faq-item__label', 'Vše důležité na jednom místě'] as $needle) {
            $assert(str_contains($view, $needle), 'FAQ view is missing richer hierarchy: ' . $needle);
        }
        $assert(!str_contains($view, 'gb-faq-tags'), 'FAQ route still renders the removed filter tags.');
        $assert(str_contains($css, 'data-cms-page-key="faq"] .gb-faq-listing'), 'FAQ contrast fix is not route scoped.');
        $assert(str_contains($css, 'color:#211a16!important'), 'FAQ does not enforce dark text on the light surface.');
        $assert(str_contains($css, 'color:#5f554c!important'), 'FAQ answers do not enforce readable copy contrast.');
    });
}
