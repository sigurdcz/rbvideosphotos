<?php

declare(strict_types=1);

namespace App\Services;

/**
 * What the admin shows about an uploaded video, and how much of what the browser reports
 * about it is believed.
 *
 * The browser reads the metadata (the server has no ffmpeg), so every number arriving from
 * it is untrusted: it is clamped here and used for display and advice only, never for
 * anything that could harm the server.
 */
final class VideoMediaInfo
{
    public const MAX_DIMENSION = 8192;
    public const HERO_MAX_BYTES = 25 * 1024 * 1024;
    public const HERO_MAX_HEIGHT = 1080;

    /**
     * @param mixed $raw decoded JSON from the upload form
     * @return array{width:int,height:int,duration:float}
     */
    public static function sanitize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $dimension = static function (mixed $value): int {
            $number = is_numeric($value) ? (int)round((float)$value) : 0;

            return $number >= 1 && $number <= self::MAX_DIMENSION ? $number : 0;
        };
        $width = $dimension($raw['width'] ?? 0);
        $height = $dimension($raw['height'] ?? 0);
        if ($width === 0 || $height === 0) {
            $width = $height = 0;
        }
        $duration = is_numeric($raw['duration'] ?? null) ? (float)$raw['duration'] : 0.0;

        return [
            'width' => $width,
            'height' => $height,
            'duration' => is_finite($duration) && $duration > 0 && $duration < 86400 ? round($duration, 1) : 0.0,
        ];
    }

    public static function qualityLabel(int $width, int $height): string
    {
        $long = max($width, $height);
        $short = min($width, $height);
        if ($short <= 0) {
            return '';
        }
        if ($short >= 2160 || $long >= 3840) {
            return '4K';
        }
        if ($short >= 1440) {
            return 'QHD';
        }
        if ($short >= 1080 || $long >= 1920) {
            return 'Full HD';
        }
        if ($short >= 720 || $long >= 1280) {
            return 'HD';
        }

        return 'SD';
    }

    /**
     * @return array{width:int,height:int,bytes:int,label:string,quality:string,warnings:list<string>}
     */
    public static function describe(int $width, int $height, int $bytes): array
    {
        $warnings = [];
        if ($width <= 0 || $height <= 0) {
            $warnings[] = 'Rozlišení se nepodařilo zjistit.';
        } else {
            if ($height > self::HERO_MAX_HEIGHT && $width >= $height) {
                $warnings[] = 'Nad Full HD je pro pozadí hero zbytečně těžké.';
            }
            if ($height > $width) {
                $warnings[] = 'Video na výšku; pro hero na desktopu se hodí video na šířku.';
            }
        }
        if ($bytes > self::HERO_MAX_BYTES) {
            $warnings[] = 'Soubor je velký (' . self::formatBytes($bytes) . '); pro hero doporučujeme do ' . self::formatBytes(self::HERO_MAX_BYTES) . '.';
        }
        $quality = self::qualityLabel($width, $height);
        $parts = [];
        if ($width > 0 && $height > 0) {
            $parts[] = $width . '×' . $height;
        }
        if ($quality !== '') {
            $parts[] = $quality;
        }
        if ($bytes > 0) {
            $parts[] = self::formatBytes($bytes);
        }

        return [
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes,
            'label' => implode(' · ', $parts),
            'quality' => $quality,
            'warnings' => $warnings,
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return str_replace('.', ',', (string)round($bytes / 1048576, 1)) . ' MB';
        }
        if ($bytes >= 1024) {
            return (string)round($bytes / 1024) . ' kB';
        }

        return $bytes . ' B';
    }
}
