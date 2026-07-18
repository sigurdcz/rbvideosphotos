<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\ServiceRepository;
use App\Services\Render\MediaUrlResolver;

/**
 * Pure data provider for the `sluzby` archetype (app/Views/archetypes/sluzby.phtml).
 *
 * Phase 4 of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeServicesPage(). Returns scalars/arrays only — no
 * HTML, no escaping (the view calls ViewContext::e()/inlineAttr()).
 */
final class SluzbyPageData
{
    /**
     * @return array{items: list<array{id:int,title:string,description:string,image_url:string,button_text:string,button_url:string}>, heroImage: string}
     */
    public static function forSite(int $siteId): array
    {
        $items = [];
        $heroImage = '';

        foreach ((new ServiceRepository())->allActive($siteId) as $row) {
            $image = MediaUrlResolver::normalizePublicAssetUrl((string)($row['image_url'] ?? ''));
            if ($image === '') {
                $image = MediaUrlResolver::publicMediaUrl((string)($row['image_url'] ?? ''));
            }
            if ($heroImage === '' && $image !== '') {
                $heroImage = $image;
            }

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
}
