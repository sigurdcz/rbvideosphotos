<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Repositories\SiteRepository;
use App\Services\PublishService;
use App\Services\ThemeTemplateRegistry;

class ThemeController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAuth();

        $site = (new SiteRepository())->findFirst();
        $registry = new ThemeTemplateRegistry();
        $themes = array_map(static fn(array $theme): array => $registry->getThemeSummary($theme), $registry->all());
        $activeKey = trim((string)($site['active_theme_key'] ?? ''));
        if ($activeKey === '') {
            $activeKey = $registry->getPersistedActiveThemeKey();
        }

        $themes = array_map(static function (array $theme) use ($activeKey): array {
            $theme['active'] = $activeKey !== '' && ($theme['key'] ?? '') === $activeKey;
            return $theme;
        }, $themes);

        $this->view('themes.index', [
            'site' => $site,
            'themes' => $themes,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function activate(array $params): void
    {
        $this->requireAuth();
        $this->csrfCheck();

        $site = (new SiteRepository())->findFirst();
        if (!$site) {
            $this->flash('error', 'Web nebyl nalezen.');
            $this->redirect('/admin/shablony');
        }

        $sites = new SiteRepository();
        if (!$sites->supportsActiveThemeKey()) {
            $this->flash('error', 'Pro sablony je treba spustit novou migraci databaze.');
            $this->redirect('/admin/shablony');
        }

        $key = trim((string)($_POST['theme_key'] ?? ''));
        $registry = new ThemeTemplateRegistry();
        if ($key !== '' && !$registry->find($key)) {
            $this->flash('error', 'Sablona nebyla nalezena.');
            $this->redirect('/admin/shablony');
        }

        Logger::infoTo('theme-debug', 'theme.activate.request', 'Theme activation requested', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'requested_key' => $key === '' ? null : $key,
            'current_db_key' => $site['active_theme_key'] ?? null,
        ]);

        $sites->update((int)$site['id'], [
            'active_theme_key' => $key === '' ? null : $key,
        ]);
        $registry->persistActiveThemeKey($key === '' ? null : $key);
        Logger::infoTo('theme-debug', 'theme.activate.persisted', 'Theme activation persisted', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'persisted_key' => $key === '' ? null : $key,
        ]);

        $user = \App\Core\Auth::user();
        $publishResult = (new PublishService())->publishSite((int)$site['id'], (int)($user['id'] ?? 0));
        Logger::infoTo('theme-debug', 'theme.activate.publish', 'Theme activation publish finished', [
            'site_id' => $site['id'] ?? null,
            'site_name' => $site['name'] ?? null,
            'publish_errors' => $publishResult['errors'] ?? [],
        ]);
        if (!empty($publishResult['errors'])) {
            $this->flash('error', 'Sablona byla ulozena, ale publikace webu mela chyby: ' . implode(' | ', $publishResult['errors']));
            $this->redirect('/admin/shablony');
        }

        $this->flash('success', $key === '' ? 'Sablona byla deaktivovana a web byl znovu publikovan.' : 'Sablona byla aktivovana a web byl znovu publikovan.');
        $this->redirect('/admin/shablony');
    }
}
