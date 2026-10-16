<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\FaqRepository;

/**
 * Pure data provider for the `faq` archetype (app/Views/archetypes/faq.phtml).
 *
 * Phase 4 of the templating refactor: replaces the ad-hoc HTML built inline in
 * public/index.php::tryServeFaqPage(). Returns scalars/arrays only - no HTML,
 * no escaping (the view calls ViewContext::e()/inlineAttr()).
 */
final class FaqPageData
{
    /**
     * @return array{items: list<array{id:int,question:string,answer:string}>}
     */
    public static function forSite(int $siteId): array
    {
        $items = [];
        foreach ((new FaqRepository())->allActive($siteId) as $row) {
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'question' => (string)($row['question'] ?? ''),
                'answer' => (string)($row['answer'] ?? ''),
            ];
        }

        return ['items' => $items];
    }
}
