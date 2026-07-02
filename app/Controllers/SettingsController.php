<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Env;
use App\Repositories\ContactRepository;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;

class SettingsController extends Controller
{
    public function index(array $params): void
    {
        $this->requireAdmin();
        $sites = new SiteRepository();
        $site = $sites->findFirst();
        $media = $site ? (new MediaRepository())->allBySiteWithVariants((int)$site['id']) : [];
        $contact = $site ? (new ContactRepository())->findBySiteSafe((int)$site['id']) : [];
        if (trim((string)($contact['form_email'] ?? '')) === '') {
            $contact['form_email'] = ContactRepository::DEFAULT_FORM_EMAIL;
        }

        $apiKey = Env::get('API_KEY') ?: null;

        $this->view('settings.index', [
            'site' => $site,
            'contact' => $contact,
            'media' => $media,
            'apiKey' => $apiKey,
            'success' => $this->getFlash('success'),
            'error' => $this->getFlash('error'),
        ]);
    }

    public function update(array $params): void
    {
        $this->requireAdmin();
        $this->csrfCheck();

        $sites = new SiteRepository();
        $contactRepo = new ContactRepository();
        $site = $sites->findFirst();
        $videoOverlayOpacity = str_replace(',', '.', trim((string)($_POST['video_overlay_opacity'] ?? '')));
        $videoOverlayOpacity = $videoOverlayOpacity === '' ? 0.62 : max(0, min(1, (float)$videoOverlayOpacity));

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'domain' => rtrim(trim($_POST['domain'] ?? ''), '/'),
            'default_language' => $_POST['default_language'] ?? 'cs',
            'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
            'google_tag_manager_id' => trim($_POST['google_tag_manager_id'] ?? ''),
            'cookie_consent_enabled' => isset($_POST['cookie_consent_enabled']) ? 1 : 0,
            'cookie_consent_analytics_enabled' => isset($_POST['cookie_consent_analytics_enabled']) ? 1 : 0,
            'cookie_consent_marketing_enabled' => isset($_POST['cookie_consent_marketing_enabled']) ? 1 : 0,
            'head_scripts' => $_POST['head_scripts'] ?? '',
            'body_scripts' => $_POST['body_scripts'] ?? '',
            'analytics_head_scripts' => $_POST['analytics_head_scripts'] ?? '',
            'analytics_body_scripts' => $_POST['analytics_body_scripts'] ?? '',
            'marketing_head_scripts' => $_POST['marketing_head_scripts'] ?? '',
            'marketing_body_scripts' => $_POST['marketing_body_scripts'] ?? '',
            'layout_html' => $_POST['layout_html'] ?? '',
            'layout_css' => $_POST['layout_css'] ?? '',
            'layout_js' => $_POST['layout_js'] ?? '',
            'layout_nav_home' => $_POST['layout_nav_home'] ?? '',
            'layout_nav_sub' => $_POST['layout_nav_sub'] ?? '',
            'logo_url' => trim($_POST['logo_url'] ?? ''),
            'favicon_ico_url' => trim($_POST['favicon_ico_url'] ?? ''),
            'favicon_png_url' => trim($_POST['favicon_png_url'] ?? ''),
            'apple_touch_icon_url' => trim($_POST['apple_touch_icon_url'] ?? ''),
            'default_hero_video_url' => trim($_POST['default_hero_video_url'] ?? ''),
            'default_hero_poster_url' => trim($_POST['default_hero_poster_url'] ?? ''),
            'video_overlay_opacity' => $videoOverlayOpacity,
            'tracking_enabled' => isset($_POST['tracking_enabled']) ? 1 : 0,
            'tracking_require_consent' => isset($_POST['tracking_require_consent']) ? 1 : 0,
            'tracking_debug' => isset($_POST['tracking_debug']) ? 1 : 0,
            'clarity_id' => trim($_POST['clarity_id'] ?? ''),
            'meta_pixel_id' => trim($_POST['meta_pixel_id'] ?? ''),
            'tracking_events_json' => $this->buildTrackingEventsJson(),
        ];

        $siteId = 0;
        if ($site) {
            $sites->update((int)$site['id'], $data);
            $siteId = (int)$site['id'];
        } else {
            $siteId = $sites->create($data);
        }

        if ($siteId > 0) {
            $contactData = $contactRepo->findBySiteSafe($siteId);
            $contactData['form_email'] = trim((string)($_POST['form_email'] ?? ''));
            $contactRepo->upsert($siteId, $contactData);
        }

        $this->flash('success', 'Nastaveni webu ulozeno.');
        $this->redirect('/admin/settings');
    }

    private function buildTrackingEventsJson(): string
    {
        $eventKeys = [
            'lead_form_submit', 'lead_thank_you_page', 'phone_click',
            'email_click', 'cta_click', 'social_click', 'gallery_open',
            'video_play', 'pricing_click', 'scroll_90',
        ];
        $result = [];
        foreach ($eventKeys as $key) {
            $result[$key] = isset($_POST['tracking_event_' . $key]);
        }
        return (string) json_encode($result);
    }
}
