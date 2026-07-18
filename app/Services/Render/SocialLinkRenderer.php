<?php

declare(strict_types=1);

namespace App\Services\Render;

/**
 * Canonical social-network link helpers (type normalization, labels, inline SVG icons).
 *
 * Extracted in phase 2 of the templating refactor as the single source of truth
 * shared by the live render path (public/index.php) and the publish path
 * (App\Services\PublishService). Do not fork these implementations.
 */
final class SocialLinkRenderer
{
    public static function normalizeSocialType(string $type, string $url = ''): string
{
    $value = strtolower(trim($type));
    $value = preg_replace('~[^a-z0-9]+~', '', $value) ?? '';
    $host = strtolower((string)(parse_url(trim($url), PHP_URL_HOST) ?: trim($url)));

    $map = [
        'ig' => 'instagram',
        'in' => 'instagram',
        'insta' => 'instagram',
        'instagram' => 'instagram',
        'fb' => 'facebook',
        'fa' => 'facebook',
        'facebook' => 'facebook',
        'yt' => 'youtube',
        'yo' => 'youtube',
        'youtube' => 'youtube',
        'tt' => 'tiktok',
        'ti' => 'tiktok',
        'tiktok' => 'tiktok',
        'li' => 'linkedin',
        'linkedin' => 'linkedin',
        'x' => 'twitter',
        'tw' => 'twitter',
        'twitter' => 'twitter',
        'pinterest' => 'pinterest',
        'wa' => 'whatsapp',
        'whatsapp' => 'whatsapp',
        'telegram' => 'telegram',
    ];

    if (isset($map[$value])) {
        return $map[$value];
    }

    foreach ($map as $needle => $canonical) {
        if ($needle !== '' && str_contains($host, $needle)) {
            return $canonical;
        }
    }

    if (str_contains($host, 'youtu')) {
        return 'youtube';
    }

    return $value !== '' ? $value : 'link';
}

    public static function socialLabel(string $type): string
{
    return [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'linkedin' => 'LinkedIn',
        'twitter' => 'X',
        'pinterest' => 'Pinterest',
        'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram',
    ][$type] ?? 'Odkaz';
}

    public static function socialIconSvg(string $type): string
{
    $icons = [
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"/><circle cx="12" cy="12" r="3.6"/><circle cx="17" cy="7" r=".8"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M13.5 19v-5h1.9l.4-2.3h-2.3v-1.5c0-.9.3-1.6 1.5-1.6h1V6.6c-.2 0-.9-.1-1.8-.1-2.7 0-4.2 1.4-4.2 4v1.2h-1.7V14H10V19"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6.5" width="17" height="11" rx="3.2"/><path d="M10 9.4 15.3 12 10 14.6z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4c.6 1.7 1.8 2.8 3.6 3.1v2.5c-1.2 0-2.4-.4-3.5-1.1v5.1a4.4 4.4 0 1 1-4.4-4.4c.3 0 .7 0 1 .1v2.5c-.2-.1-.5-.1-.7-.1a1.9 1.9 0 1 0 1.8 1.9V4z"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="9" width="3" height="10"/><circle cx="5.5" cy="5.8" r="1.4"/><path d="M10 9v10"/><path d="M10 13.2c0-2.2 1.4-4.2 4-4.2 2.4 0 3.5 1.6 3.5 4.3V19"/><path d="M17.5 19v-5.1"/></svg>',
        'twitter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5 19.5 19"/><path d="M19.5 5 4 19"/></svg>',
        'pinterest' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M10.7 18.5c.3-1.2.7-2.6 1-3.9"/><path d="M9.8 10.2c0-2.2 1.7-3.8 4-3.8 2 0 3.5 1.3 3.5 3.2 0 2.4-1 5.4-3.2 5.4-.9 0-1.6-.8-1.4-1.7.3-1 1-2.2 1-3.4 0-2-2.5-1.7-2.5.8 0 .6.1 1.1.3 1.5-.2 1-.6 2.4-1 3.6"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.7 18.1A8.9 8.9 0 1 0 6 19.6L3.5 20.5l1-2.4"/><path d="M9.2 8.8c-.2-.4-.4-.4-.7-.4h-.6c-.2 0-.5.1-.7.4-.3.3-.9.9-.9 2.1s1 2.4 1.1 2.6c.1.1 1.9 3 4.8 4.1 2.4.9 2.9.7 3.4.6.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.1.2-1.2s-.2-.2-.5-.3l-1.7-.8c-.3-.1-.5-.1-.7.1l-.8 1c-.1.2-.3.2-.6.1-.3-.1-1.2-.4-2.2-1.3-.8-.7-1.4-1.6-1.6-1.9-.2-.3 0-.4.1-.6l.4-.5.3-.4c.1-.2.1-.4 0-.6z"/></svg>',
        'telegram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 4 3.8 10.7l5.4 1.8 1.9 5.6 2.6-2.5 4.9 3.6z"/><path d="M9.2 12.5 20.2 5.2"/><path d="M11.1 18.1 12 14.2"/></svg>',
        'link' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 14 8.2 15.8a3.2 3.2 0 1 1-4.5-4.5L5.6 9.5"/><path d="M14 10 15.8 8.2a3.2 3.2 0 1 1 4.5 4.5L18.4 14.5"/><path d="M8.8 15.2 15.2 8.8"/></svg>',
    ];

    return $icons[$type] ?? $icons['link'];
}
}
