<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\MediaRepository;
use App\Repositories\ServiceRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Pure data provider for the `sluzby` archetype (app/Views/archetypes/sluzby.phtml).
 *
 * Phase 4 of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeServicesPage(). Returns scalars/arrays only - no
 * HTML, no escaping (the view calls ViewContext::e()/inlineAttr()).
 */
final class SluzbyPageData
{
    /**
     * @return array{items: list<array{id:int,title:string,description:string,image_url:string,button_text:string,button_url:string}>, heroImage: string}
     */
    public static function forSite(int $siteId, string $pageHeroImage = ''): array
    {
        $items = [];
        $media = new MediaRepository();
        $heroImage = self::presentationImage($siteId, $pageHeroImage, $media);

        foreach ((new ServiceRepository())->allActive($siteId) as $row) {
            $image = MediaUrlResolver::normalizePublicAssetUrl((string)($row['image_url'] ?? ''));
            if ($image === '') {
                $image = MediaUrlResolver::publicMediaUrl((string)($row['image_url'] ?? ''));
            }
            $image = self::presentationImage($siteId, $image, $media);

            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => trim((string)($row['title'] ?? '')),
                'description' => trim((string)($row['description'] ?? '')),
                'image_url' => $image,
                'button_text' => trim((string)($row['button_text'] ?? '')) ?: 'Více informací',
                'button_url' => trim((string)($row['button_url'] ?? '')) ?: '/kontakt',
            ];
        }

        return ['items' => $items, 'heroImage' => $heroImage];
    }

    private static function presentationImage(int $siteId, string $image, MediaRepository $media): string
    {
        $image = MediaUrlResolver::normalizePublicAssetUrl($image);
        if ($image === '') {
            $image = MediaUrlResolver::publicMediaUrl($image);
        }
        $storagePath = self::storagePath($image);
        $mediaRow = $storagePath !== '' ? $media->findByStoragePath($siteId, $storagePath) : null;
        if ($mediaRow) {
            foreach ($media->variantsByMediaId((int)$mediaRow['id']) as $variant) {
                if (($variant['format'] ?? '') === 'medium' && !empty($variant['path'])) {
                    return MediaUrlResolver::publicMediaUrl((string)$variant['path']);
                }
            }
        }
        return $image;
    }

    private static function storagePath(string $url): string
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        if (str_starts_with($path, '/media/')) {
            return 'storage/media/' . ltrim(substr($path, strlen('/media/')), '/');
        }
        if (str_starts_with($path, '/storage/media/')) {
            return ltrim($path, '/');
        }
        return '';
    }
}
