<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Repositories\ReviewRepository;

/**
 * Pure data provider for the `reviews` archetype (app/Views/archetypes/reviews.phtml),
 * i.e. the /hodnoceni listing page.
 *
 * Phase 4 of the templating refactor: replaces the string-concatenation HTML
 * built by App\Services\ReviewContentService::buildGridMarkup() for the
 * standalone listing (buildListingHtml()). The homepage reviews teaser
 * (buildHomepageHtml(), different CSS classes + its own inline <style>) is
 * out of scope here — it belongs to the homepage archetype (phase 5).
 *
 * Unlike faq/sluzby/cenik, /hodnoceni already shared this data source between
 * live and publish before phase 4 (HomepageContentService::applyFallbacks()
 * runs identically in both public/index.php::tryServeDatabasePage() and
 * PublishService::publishPage()) — this change is a pure refactor of how the
 * HTML is produced, not a live/publish unification fix.
 */
final class ReviewsPageData
{
    /**
     * @return array{items: list<array{id:int,rating:int,review_text:string,author_name:string,context_label:string}>}
     */
    public static function forSite(int $siteId): array
    {
        $items = [];
        foreach ((new ReviewRepository())->allActive($siteId) as $row) {
            $rating = max(1, min(5, (int)($row['rating'] ?? 5)));
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'rating' => $rating,
                'review_text' => (string)($row['review_text'] ?? ''),
                'author_name' => (string)($row['author_name'] ?? ''),
                'context_label' => trim((string)($row['context_label'] ?? '')),
            ];
        }

        return ['items' => $items];
    }
}
