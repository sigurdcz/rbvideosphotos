<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;

class SettingsController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        $sites = new SiteRepository();
        $site  = $sites->findFirst();
        $media = $site ? (new MediaRepository())->allBySiteWithVariants((int)$site['id']) : [];

        $apiKey = (string) getenv('API_KEY') ?: null;

        $this->view('settings.index', [
            'site'    => $site,
            'media'   => $media,
            'apiKey'  => $apiKey,
            'success' => $this->getFlash('success'),
            'error'   => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $site  = $sites->findFirst();

        $data = [
            'name'                  => trim($_POST['name']                  ?? ''),
            'domain'                => rtrim(trim($_POST['domain'] ?? ''), '/'),
            'default_language'      => $_POST['default_language']           ?? 'cs',
            'google_analytics_id'   => trim($_POST['google_analytics_id']   ?? ''),
            'google_tag_manager_id' => trim($_POST['google_tag_manager_id'] ?? ''),
            'cookie_consent_enabled'=> isset($_POST['cookie_consent_enabled']) ? 1 : 0,
            'cookie_consent_analytics_enabled' => isset($_POST['cookie_consent_analytics_enabled']) ? 1 : 0,
            'cookie_consent_marketing_enabled' => isset($_POST['cookie_consent_marketing_enabled']) ? 1 : 0,
            'head_scripts'          => $_POST['head_scripts']               ?? '',
            'body_scripts'          => $_POST['body_scripts']               ?? '',
            'analytics_head_scripts'=> $_POST['analytics_head_scripts']      ?? '',
            'analytics_body_scripts'=> $_POST['analytics_body_scripts']      ?? '',
            'marketing_head_scripts'=> $_POST['marketing_head_scripts']      ?? '',
            'marketing_body_scripts'=> $_POST['marketing_body_scripts']      ?? '',
            'layout_html'           => $_POST['layout_html']                ?? '',
            'layout_css'            => $_POST['layout_css']                 ?? '',
            'layout_js'             => $_POST['layout_js']                  ?? '',
            'layout_nav_home'       => $_POST['layout_nav_home']            ?? '',
            'layout_nav_sub'        => $_POST['layout_nav_sub']             ?? '',
            'logo_url'              => trim($_POST['logo_url']              ?? ''),
            'favicon_ico_url'       => trim($_POST['favicon_ico_url']       ?? ''),
            'favicon_png_url'       => trim($_POST['favicon_png_url']       ?? ''),
            'apple_touch_icon_url'  => trim($_POST['apple_touch_icon_url']  ?? ''),
            'default_hero_video_url' => trim($_POST['default_hero_video_url'] ?? ''),
            'default_hero_poster_url'=> trim($_POST['default_hero_poster_url'] ?? ''),
        ];

        if ($site) {
            $sites->update($site['id'], $data);
        } else {
            $sites->create($data);
        }

        $this->flash('success', 'Nastavení webu uloženo.');
        $this->redirect('/admin/settings');
    }
}
