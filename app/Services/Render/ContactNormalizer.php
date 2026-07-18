<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Core\Logger;
use App\Repositories\PageRepository;
use App\Repositories\BlockRepository;
use App\Repositories\ContactRepository;

/**
 * Canonical contact-data normalization (phones, address, legacy-block enrichment).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class ContactNormalizer
{
    public static function normalizePhoneForHref(string $phone): string
{
    $normalized = preg_replace('/[^\d+]/', '', $phone) ?? '';
    if (str_starts_with($normalized, '00')) {
        $normalized = '+' . substr($normalized, 2);
    }
    if (str_starts_with($normalized, '420') && strlen($normalized) === 12) {
        $normalized = '+' . $normalized;
    }
    return $normalized;
}

    public static function formatPhoneForDisplay(string $phone): string
{
    $normalized = \App\Services\Render\ContactNormalizer::normalizePhoneForHref($phone);
    $digits = ltrim($normalized, '+');
    if (strlen($digits) === 12 && str_starts_with($digits, '420')) {
        return '+420 ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9, 3);
    }
    if (strlen($digits) === 9) {
        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3);
    }
    return trim($phone);
}

    public static function phoneDedupKey(string $phone): string
{
    $digits = preg_replace('/\D+/', '', \App\Services\Render\ContactNormalizer::normalizePhoneForHref($phone)) ?? '';
    return strlen($digits) > 9 ? substr($digits, -9) : $digits;
}

    public static function extractPhoneNumbersFromText(string $plain): array
{
    $phones = [];
    if (preg_match_all('/(?:\+?\d[\d\s().\-]{7,}\d)/u', $plain, $matches)) {
        foreach ($matches[0] as $candidate) {
            $normalized = \App\Services\Render\ContactNormalizer::normalizePhoneForHref((string)$candidate);
            $digits = ltrim($normalized, '+');
            if (strlen($digits) === 18) {
                foreach (str_split($digits, 9) as $chunk) {
                    if (strlen($chunk) === 9 && !in_array($chunk, $phones, true)) {
                        $phones[] = $chunk;
                    }
                }
                continue;
            }
            if (strlen($digits) === 24 && str_starts_with($digits, '420')) {
                foreach (str_split($digits, 12) as $chunk) {
                    if (strlen($chunk) === 12 && !in_array('+' . $chunk, $phones, true)) {
                        $phones[] = '+' . $chunk;
                    }
                }
                continue;
            }
            if (strlen($digits) >= 9 && !in_array($normalized, $phones, true)) {
                $phones[] = $normalized;
            }
        }
    }

    return $phones;
}

    public static function ensureRbDemoContactAddress(array $contact): array
{
    return $contact;
}

    public static function enrichContactWithLegacyBlocks(int $siteId, array $contact): array
{
    $pages = new PageRepository();
    $blocks = new BlockRepository();
    $htmlChunks = [];

    foreach (['kontakt', ''] as $path) {
        $page = $pages->findByUrlPath($path);
        if (!$page || (int)($page['site_id'] ?? 0) !== $siteId) {
            continue;
        }
        foreach (['kontakt_info', 'contact_info', 'contact_card'] as $blockKey) {
            $block = $blocks->findByKey((int)$page['id'], $blockKey);
            $content = trim((string)($block['content'] ?? ''));
            if ($content !== '') {
                $htmlChunks[] = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
    }

    if ($htmlChunks === []) {
        return $contact;
    }

    $html = implode("\n", $htmlChunks);
    $textHtml = preg_replace('~<\s*br\s*/?\s*>|</\s*(?:p|div|li|article|section|h[1-6])\s*>~i', "\n", $html) ?? $html;
    $plain = preg_replace('/\x{00A0}/u', ' ', strip_tags($textHtml)) ?? '';

    if (trim((string)($contact['email'] ?? '')) === '' && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $plain, $m)) {
        $contact['email'] = $m[0];
    }

    $phones = \App\Services\Render\ContactNormalizer::extractPhoneNumbersFromText($plain);

    $existingPhoneKeys = [];
    foreach (['phone_1', 'phone_2', 'phone_3'] as $key) {
        $phoneKey = \App\Services\Render\ContactNormalizer::phoneDedupKey((string)($contact[$key] ?? ''));
        if ($phoneKey !== '') {
            $existingPhoneKeys[$phoneKey] = true;
        }
    }
    foreach (['phone_1', 'phone_2', 'phone_3'] as $key) {
        if (trim((string)($contact[$key] ?? '')) !== '') {
            continue;
        }
        foreach ($phones as $phone) {
            $phoneKey = \App\Services\Render\ContactNormalizer::phoneDedupKey((string)$phone);
            if ($phoneKey === '' || isset($existingPhoneKeys[$phoneKey])) {
                continue;
            }
            $contact[$key] = $phone;
            $existingPhoneKeys[$phoneKey] = true;
            break;
        }
    }

    if (trim((string)($contact['address_1'] ?? '')) === '' || trim((string)($contact['address_2'] ?? '')) === '') {
        $lines = array_values(array_filter(array_map(
            static fn($line) => trim(preg_replace('/\s+/u', ' ', (string)$line) ?? ''),
            preg_split('/\R+/', $plain) ?: []
        )));
        foreach ($lines as $index => $line) {
            if (trim((string)($contact['address_2'] ?? '')) === '' && preg_match('/\b\d{3}\s?\d{2}\b.*\bOlomouc\b/iu', $line)) {
                $contact['address_2'] = $line;
                for ($i = $index - 1; $i >= 0; $i--) {
                    $candidate = $lines[$i] ?? '';
                    if ($candidate === '' || preg_match('/@|telefon|facebook|instagram|youtube|\+?\d[\d\s().-]{7,}\d/iu', $candidate)) {
                        continue;
                    }
                    if (trim((string)($contact['address_1'] ?? '')) === '') {
                        $contact['address_1'] = $candidate;
                    }
                    break;
                }
            }
        }
    }

    if (preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $linkMatches)) {
        $socialSlot = 1;
        foreach ($linkMatches[1] as $href) {
            $type = \App\Services\Render\SocialLinkRenderer::normalizeSocialType('', $href);
            if ($type === 'link' || !preg_match('~^(?:https?:)?//~i', $href)) {
                continue;
            }

            $alreadyPresent = false;
            for ($i = 1; $i <= 5; $i++) {
                if (trim((string)($contact['social_' . $i . '_url'] ?? '')) === trim((string)$href)) {
                    $alreadyPresent = true;
                    break;
                }
            }
            if ($alreadyPresent) {
                continue;
            }

            while ($socialSlot <= 5 && trim((string)($contact['social_' . $socialSlot . '_url'] ?? '')) !== '') {
                $socialSlot++;
            }
            if ($socialSlot > 5) {
                break;
            }
            $contact['social_' . $socialSlot . '_type'] = $type;
            $contact['social_' . $socialSlot . '_url'] = $href;
            $socialSlot++;
        }
    }

    if (trim((string)($contact['form_email'] ?? '')) === '') {
        $contact['form_email'] = trim((string)($contact['email'] ?? '')) !== ''
            ? trim((string)($contact['email'] ?? ''))
            : \App\Repositories\ContactRepository::DEFAULT_FORM_EMAIL;
    }

    return \App\Services\Render\ContactNormalizer::ensureRbDemoContactAddress($contact);
}
}
