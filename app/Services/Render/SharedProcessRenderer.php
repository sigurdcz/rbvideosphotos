<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Repositories\SharedSectionRepository;
use App\Repositories\SiteRepository;
use App\Services\ThemeTemplateRegistry;
use App\View\ViewFactory;

final class SharedProcessRenderer
{
    public static function inject(string $html, ?string $themeKey): string
    {
        if (!ThemeTemplateRegistry::isGoldenBashFamily($themeKey)
            || str_contains($html, 'gb-shared-process')
            || str_contains($html, 'class="gb-article"')
            || str_contains($html, 'class="pf-main gb-work-detail"')) {
            return $html;
        }

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            throw new \RuntimeException('Sdilenou sekci nelze vyrenderovat: web nebyl nalezen.');
        }
        $content = (new SharedSectionRepository())->decodedByKey((int)$site['id'], 'process_cooperation');
        if ($content === null) {
            return $html;
        }
        $section = ViewFactory::forTheme(null)->render('partials/process-cooperation', ['section' => $content]);
        $patterns = [
            '~<section\b(?=[^>]*\bclass=(["\'])[^"\']*\bhp-process\b[^"\']*\1)[^>]*>.*?</section>~is',
            '~<section\b(?=[^>]*\bclass=(["\'])[^"\']*\bkt-process\b[^"\']*\1)[^>]*>.*?</section>~is',
            '~<section\b(?=[^>]*\bclass=(["\'])[^"\']*\bgb-services-process\b[^"\']*\1)[^>]*>.*?</section>~is',
            '~<section\b(?=[^>]*\bclass=(["\'])[^"\']*\bgb-about-process\b[^"\']*\1)[^>]*>.*?</section>~is',
        ];

        foreach ($patterns as $pattern) {
            $replaced = preg_replace($pattern, $section, $html, 1, $count);
            if ($count > 0 && is_string($replaced)) {
                return $replaced;
            }
        }

        $beforeCta = preg_replace(
            '~(<section\b(?=[^>]*\bclass=(["\'])[^"\']*\bgb-route-cta\b[^"\']*\2)[^>]*>)~i',
            $section . '$1',
            $html,
            1,
            $count
        );
        if ($count > 0 && is_string($beforeCta)) {
            return $beforeCta;
        }

        return preg_replace('~</main>~i', $section . '</main>', $html, 1) ?? $html;
    }
}
