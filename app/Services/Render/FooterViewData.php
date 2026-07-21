<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Repositories\ContactRepository;
use App\Repositories\PageRepository;
use App\Repositories\SiteRepository;

/**
 * View-model provider for the rich footer partial (app/Views/partials/footer.phtml).
 *
 * Returns plain data (scalars/arrays) only — never HTML markup, except for the
 * trusted inline social icon SVG which is emitted through raw() in the view.
 * The shape mirrors exactly what FooterRenderer::buildRichFooterHtml() used to
 * concatenate inline, so the PHTML partial reproduces byte-identical output.
 */
final class FooterViewData
{
    /**
     * @return array{
     *   brand: array{logoUrl:string, mark:string, name:string},
     *   phones: array<int, array{tel:string, display:string}>,
     *   email: string,
     *   addressLines: array<int, string>,
     *   socials: array<int, array{type:string, url:string, label:string, iconSvg:string}>,
     *   navLinks: array<int, array{url:string, label:string}>,
     *   year: string
     * }
     */
    public static function forSite(int $siteId): array
    {
        $contact = ContactNormalizer::enrichContactWithLegacyBlocks(
            $siteId,
            (new ContactRepository())->findBySiteSafe($siteId)
        );
        $site = (new SiteRepository())->findById($siteId) ?? [];
        $siteName = (string) ($site['name'] ?? 'RB Videos & Photos');
        $logoUrl = MediaUrlResolver::normalizePublicAssetUrl((string) ($site['logo_url'] ?? ''));

        $phones = [];
        foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
            $phone = trim((string) ($contact[$phoneKey] ?? ''));
            if ($phone === '') {
                continue;
            }
            $phones[] = [
                'tel' => ContactNormalizer::normalizePhoneForHref($phone),
                'display' => ContactNormalizer::formatPhoneForDisplay($phone),
            ];
        }

        $email = trim((string) ($contact['email'] ?? ''));

        $addressLines = [];
        foreach (['address_1', 'address_2'] as $addressKey) {
            $addressLine = trim((string) ($contact[$addressKey] ?? ''));
            if ($addressLine !== '') {
                $addressLines[] = $addressLine;
            }
        }

        $socials = [];
        for ($i = 1; $i <= 5; $i++) {
            $type = trim((string) ($contact['social_' . $i . '_type'] ?? ''));
            $url = trim((string) ($contact['social_' . $i . '_url'] ?? ''));
            if ($type === '' || $url === '') {
                continue;
            }
            $canonicalType = SocialLinkRenderer::normalizeSocialType($type, $url);
            $socials[] = [
                'type' => $canonicalType,
                'url' => $url,
                'label' => SocialLinkRenderer::socialLabel($canonicalType),
                'iconSvg' => SocialLinkRenderer::socialIconSvg($canonicalType),
            ];
        }

        $pages = new PageRepository();
        $navLinks = [];
        foreach (['nase-prace' => 'Naše práce', 'blog' => 'Blog', 'hodnoceni' => 'Hodnocení', 'faq' => 'FAQ', 'kontakt' => 'Kontakt'] as $urlPath => $fallbackLabel) {
            $page = $pages->findByUrlPath($urlPath);
            if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
                continue;
            }
            $label = trim((string)($page['title'] ?? '')) ?: $fallbackLabel;
            $navLinks[] = ['url' => '/' . $urlPath, 'label' => $label];
        }

        return [
            'brand' => [
                'logoUrl' => $logoUrl,
                'mark' => mb_substr($siteName, 0, 2),
                'name' => $siteName,
            ],
            'phones' => $phones,
            'email' => $email,
            'addressLines' => $addressLines,
            'socials' => $socials,
            'navLinks' => $navLinks,
            'year' => date('Y'),
        ];
    }
}
