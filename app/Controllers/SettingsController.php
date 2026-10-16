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
        $media = [];
        if ($site) {
            $repo = new MediaRepository();
            foreach (['default_og_image_id','default_instagram_image_id','default_twitter_image_id'] as $field) {
                $id = (int)($site[$field] ?? 0);
                if ($id && ($item = $repo->findByIdWithThumb($id))) $media[] = $item;
            }
        }
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
        $mediaImageQuality = trim((string)($_POST['media_image_quality'] ?? ''));
        $mediaImageQuality = $mediaImageQuality === '' ? 90 : (int) $mediaImageQuality;
        $mediaImageQuality = max(1, min(100, $mediaImageQuality));
        $defaultOgImageId = $this->validatedImageMediaId(
            (int)($_POST['default_og_image_id'] ?? 0),
            (int)($site['id'] ?? 0)
        );
        $defaultInstagramImageId = $this->validatedImageMediaId(
            (int)($_POST['default_instagram_image_id'] ?? 0),
            (int)($site['id'] ?? 0)
        );
        $defaultTwitterImageId = $this->validatedImageMediaId(
            (int)($_POST['default_twitter_image_id'] ?? 0),
            (int)($site['id'] ?? 0)
        );
        $companyRegistrationId = preg_replace('/\s+/', '', trim((string)($_POST['company_registration_id'] ?? '')));
        if ($companyRegistrationId !== '' && preg_match('/^\d{8}$/', $companyRegistrationId) !== 1) {
            $this->flash('error', 'IČO musí obsahovat přesně osm číslic.');
            $this->redirect('/admin/settings');
        }

        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'domain' => rtrim(trim($_POST['domain'] ?? ''), '/'),
            'company_registration_id' => $companyRegistrationId,
            'default_language' => $_POST['default_language'] ?? 'cs',
            'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
            'google_tag_manager_id' => trim($_POST['google_tag_manager_id'] ?? ''),
            'video_autoplay_enabled' => isset($_POST['video_autoplay_enabled']) ? 1 : 0,
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
            'default_og_image_id' => $defaultOgImageId,
            'default_instagram_image_id' => $defaultInstagramImageId,
            'default_twitter_image_id' => $defaultTwitterImageId,
            'default_hero_video_url' => trim($_POST['default_hero_video_url'] ?? ''),
            'default_hero_video_url_tablet' => trim($_POST['default_hero_video_url_tablet'] ?? ''),
            'default_hero_video_url_mobile' => trim($_POST['default_hero_video_url_mobile'] ?? ''),
            'default_hero_poster_url' => trim($_POST['default_hero_poster_url'] ?? ''),
            'media_image_quality' => $mediaImageQuality,
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

        $this->flash('success', 'Nastavení webu ulozeno.');
        $this->redirect('/admin/settings');
    }

    private function validatedImageMediaId(int $mediaId, int $siteId): ?int
    {
        if ($mediaId <= 0) {
            return null;
        }

        $media = (new MediaRepository())->findById($mediaId);
        if (
            !$media
            || (int)($media['site_id'] ?? 0) !== $siteId
            || !str_starts_with((string)($media['mime_type'] ?? ''), 'image/')
        ) {
            $this->flash('error', 'Vybrana fotografie pro socialni site neni platny obrazek tohoto webu.');
            $this->redirect('/admin/settings');
        }

        return $mediaId;
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
