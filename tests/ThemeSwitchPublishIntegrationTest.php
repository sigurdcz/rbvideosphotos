<?php

declare(strict_types=1);

use App\Repositories\SiteRepository;
use App\Repositories\UserRepository;
use App\Core\Env;
use App\Services\PublishService;
use App\Services\ThemeTemplateRegistry;

function register_theme_switch_publish_integration_tests(callable $test, callable $assert): void
{
    $test('switching Golden Bash and Elegant White republishes the complete site', function () use ($assert): void {
        Env::load(dirname(__DIR__) . '/.env');
        $sites = new SiteRepository();
        $site = $sites->findFirst();
        $assert($site !== null, 'Theme switch integration test requires an existing site.');
        $assert($sites->supportsActiveThemeKey(), 'Theme switch integration test requires sites.active_theme_key.');

        $siteId = (int) $site['id'];
        $userId = (new UserRepository())->firstId();
        $assert($userId !== null, 'Theme switch integration test requires an existing publisher user.');
        $originalKey = trim((string) ($site['active_theme_key'] ?? ''));
        $registry = new ThemeTemplateRegistry();
        $publisher = new PublishService();

        try {
            foreach (['golden-bash', 'elegant-white'] as $themeKey) {
                $assert($registry->find($themeKey) !== null, 'Required test theme is missing: ' . $themeKey);
                $sites->update($siteId, ['active_theme_key' => $themeKey]);
                $registry->persistActiveThemeKey($themeKey);

                $result = $publisher->publishSite($siteId, $userId);
                $assert(($result['success'] ?? false) === true, 'Publishing failed after switching to ' . $themeKey . '.');
                $assert(($result['errors'] ?? []) === [], 'Publishing returned errors after switching to ' . $themeKey . ': ' . implode(' | ', $result['errors'] ?? []));

                $persisted = $sites->findById($siteId);
                $assert(($persisted['active_theme_key'] ?? null) === $themeKey, 'Database did not persist active theme ' . $themeKey . '.');
            }
        } finally {
            $restoreKey = $originalKey !== '' ? $originalKey : null;
            $sites->update($siteId, ['active_theme_key' => $restoreKey]);
            $registry->persistActiveThemeKey($restoreKey);
            $restoreResult = $publisher->publishSite($siteId, $userId);
            $assert(($restoreResult['success'] ?? false) === true, 'Original theme could not be republished after integration test.');
            $assert(($restoreResult['errors'] ?? []) === [], 'Restoring original theme returned publish errors.');
        }
    });
}
