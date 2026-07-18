<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Repositories\ContactRepository;
use App\Repositories\SiteRepository;

/**
 * Canonical rich footer builder and footer injection (unified live/publish).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class FooterRenderer
{
    public static function buildRichFooterHtml(int $siteId): string
{
    $contact = \App\Services\Render\ContactNormalizer::enrichContactWithLegacyBlocks($siteId, (new ContactRepository())->findBySiteSafe($siteId));
    $site = (new SiteRepository())->findById($siteId) ?? [];
    $siteName = (string)($site['name'] ?? 'RB Videos & Photos');
    $logoUrl = \App\Services\Render\MediaUrlResolver::normalizePublicAssetUrl((string)($site['logo_url'] ?? ''));
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);

    $contactItems = [];
    foreach (['phone_1', 'phone_2', 'phone_3'] as $phoneKey) {
        $phone = trim((string)($contact[$phoneKey] ?? ''));
        if ($phone === '') {
            continue;
        }
        $tel = \App\Services\Render\ContactNormalizer::normalizePhoneForHref($phone);
        $contactItems[] = '<a class="site-footer-contact__item" href="tel:' . $h($tel) . '"><span>Telefon</span><strong>' . $h(\App\Services\Render\ContactNormalizer::formatPhoneForDisplay($phone)) . '</strong></a>';
    }

    $email = trim((string)($contact['email'] ?? ''));
    if ($email !== '') {
        $contactItems[] = '<a class="site-footer-contact__item" href="mailto:' . $h($email) . '"><span>E-mail</span><strong>' . $h($email) . '</strong></a>';
    }

    $addressLines = [];
    foreach (['address_1', 'address_2'] as $addressKey) {
        $addressLine = trim((string)($contact[$addressKey] ?? ''));
        if ($addressLine !== '') {
            $addressLines[] = $h($addressLine);
        }
    }
    if ($addressLines !== []) {
        $contactItems[] = '<div class="site-footer-contact__item"><span>Lokalita</span><strong>' . implode('<br>', $addressLines) . '</strong></div>';
    }

    $socialItems = [];
    for ($i = 1; $i <= 5; $i++) {
        $type = trim((string)($contact['social_' . $i . '_type'] ?? ''));
        $url = trim((string)($contact['social_' . $i . '_url'] ?? ''));
        if ($type === '' || $url === '') {
            continue;
        }
        $canonicalType = \App\Services\Render\SocialLinkRenderer::normalizeSocialType($type, $url);
        $label = $h(\App\Services\Render\SocialLinkRenderer::socialLabel($canonicalType));
        $socialItems[] = '<a class="site-footer-social site-footer-social--' . $h($canonicalType) . '" href="' . $h($url) . '" target="_blank" rel="noopener" aria-label="' . $label . '" title="' . $label . '"><span class="site-footer-social__icon">' . \App\Services\Render\SocialLinkRenderer::socialIconSvg($canonicalType) . '</span></a>';
    }

    $brand = $logoUrl !== ''
        ? '<span class="site-footer-brand__logoBox"><img class="site-footer-brand__logo" src="' . $h($logoUrl) . '" alt="' . $h($siteName) . '"></span>'
        : '<span class="site-footer-brand__mark">' . $h(mb_substr($siteName, 0, 2)) . '</span>';

    return '<style data-cms-rich-footer>'
        . 'html,body{min-height:100%}body{min-height:100vh;display:flex;flex-direction:column}.theme-shell{width:100%;flex:1 0 auto;display:flex;flex-direction:column}.theme-shell,.theme-shell__frame{min-height:100vh}.theme-shell__frame{display:flex;flex-direction:column;flex:1 0 auto}.theme-main,main,#page-content{flex:1 0 auto}footer,.theme-footer,.site-footer{margin-top:auto}'
        . 'footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{width:100%;max-width:1180px;margin:0 auto;padding:0;display:grid;grid-template-columns:minmax(220px,.85fr) minmax(300px,1fr) minmax(220px,.75fr);gap:34px;align-items:start;text-align:left}'
        . '.site-footer-brand{display:flex;flex-direction:column;gap:12px;align-items:flex-start}.site-footer-brand__logoBox{display:inline-flex;align-items:center;justify-content:center;min-width:74px;min-height:58px;padding:9px 12px;border:1px solid rgba(198,148,61,.7);background:rgba(0,0,0,.45);box-shadow:0 14px 32px rgba(198,148,61,.12)}.site-footer-brand__logo{max-height:42px;max-width:220px;width:auto;height:auto;object-fit:contain}.site-footer-brand__mark{display:inline-grid;place-items:center;width:54px;height:54px;border:1px solid rgba(198,148,61,.55);color:#c6943d;font-family:Georgia,serif;font-weight:800;font-size:20px;letter-spacing:.08em;text-transform:uppercase}.site-footer-brand__name{color:#f3efe7;font-family:Georgia,serif;font-size:22px;line-height:1.1}.site-footer-brand__note{color:rgba(255,255,255,.52);font-size:13px;line-height:1.65}'
        . '.site-footer-contact{display:grid;gap:9px}.site-footer-title{margin:0 0 12px;color:#c6943d;font-size:11px;font-weight:900;letter-spacing:.18em;text-transform:uppercase}.site-footer-contact__item{display:grid;grid-template-columns:86px minmax(0,1fr);gap:14px;align-items:baseline;color:#f3efe7;text-decoration:none}.site-footer-contact__item span{color:rgba(255,255,255,.44);font-size:11px;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap}.site-footer-contact__item strong,.site-footer-contact__item a{min-width:0;color:#f3efe7;font-size:14px;line-height:1.45;font-weight:800;word-break:break-word}'
        . '.site-footer-socials{display:flex;flex-direction:column;gap:14px;align-items:flex-start}.site-footer-socials__list{display:flex;flex-wrap:wrap;gap:10px}.site-footer-social{--social:#d4b06f;display:inline-flex;align-items:center;justify-content:center;width:50px;height:40px;padding:0;border:1px solid rgba(212,176,111,.24);border-radius:0;background:transparent;color:#d8d1c6;text-decoration:none;transition:transform .2s,border-color .2s,color .2s,background-color .2s}.site-footer-social:hover{transform:translateY(-1px);border-color:rgba(212,176,111,.62);background:rgba(212,176,111,.06);color:#f2e7d5}.site-footer-social__icon{display:grid;place-items:center;line-height:1;transition:color .2s,transform .2s}.site-footer-social:hover .site-footer-social__icon{transform:scale(1.06)}.site-footer-social__icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}.site-footer-bottom{grid-column:1/-1;margin-top:4px;padding-top:20px;border-top:1px solid rgba(255,255,255,.09);color:rgba(255,255,255,.36);font-size:12px;text-align:left}'
        . '@media(max-width:820px){footer .site-footer-rich,.theme-footer .site-footer-rich,.site-footer .site-footer-rich{grid-template-columns:1fr;gap:24px}.site-footer-bottom{text-align:left}}'
        . '</style>'
        . '<div class="site-footer-rich">'
            . '<div class="site-footer-brand">' . $brand . '<div class="site-footer-brand__name">' . $h($siteName) . '</div></div>'
        . '<div class="site-footer-contact"><div class="site-footer-title">Kontakt</div>' . ($contactItems !== [] ? implode('', $contactItems) : '<div class="site-footer-contact__item"><span>Kontakt</span><strong>Doplnte kontaktni udaje v administraci.</strong></div>') . '</div>'
        . '<div class="site-footer-socials"><div class="site-footer-title">Socialni site</div><div class="site-footer-socials__list">' . ($socialItems !== [] ? implode('', $socialItems) : '<span class="site-footer-brand__note">Doplnte socialni site v kontaktu.</span>') . '</div></div>'
        . '<div class="site-footer-bottom">&copy; ' . date('Y') . ' ' . $h($siteName) . ' · <a href="/podminky-uziti">Podmínky užití</a></div>'
        . '</div>';
}

    public static function injectFooterSocials(string $html, int $siteId): string
{
    $socials = \App\Services\Render\FooterRenderer::buildRichFooterHtml($siteId);
    if ($socials === '') {
        return $html;
    }

    if (preg_match('~<footer\b([^>]*)>.*?</footer>~is', $html)) {
        return preg_replace('~<footer\b([^>]*)>.*?</footer>~is', '<footer$1>' . $socials . '</footer>', $html, 1) ?? $html;
    }

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $socials . '</body>', $html);
    }

    return $html . $socials;
}
}
