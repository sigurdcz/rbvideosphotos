<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Server-owned allowlist for package selections.
 *
 * Browsers submit only one of these identifiers. Display values used in
 * contact e-mails must always be resolved here, never accepted from GET/POST.
 */
final class PricingPackageCatalog
{
    public const WEDDING_VIDEO_FULL_DAY = 'svatebni-video-cely-den';
    public const CEREMONY_AND_PORTRAITS = 'obrad-portrety';
    public const WEDDING_PHOTO_8_HOURS = 'svatebni-foto-8h';
    public const WEDDING_PHOTO_FULL_DAY = 'svatebni-foto-cely-den';
    public const PHOTO_VIDEO_COMPLETE = 'foto-video-komplet';
    public const PORTRAIT_AND_FAMILY = 'portret-rodina';
    public const STUDIO_PHOTOGRAPHY = 'atelier';
    public const WEDDING_VIDEO_ONLY_FULL_DAY = 'svatebni-video-jen-natoceni';

    /**
     * Card slot (`cenik_card_<slot>_*`, 1-based = display order on /cenik) ->
     * package id. The order follows migration 094; when cards are reordered,
     * the ids move with their cards so `data-package-id` / `?balicek=` keep
     * pointing to the same package.
     *
     * @var list<string>
     */
    private const SLOT_IDS = [
        self::WEDDING_PHOTO_FULL_DAY,
        self::PHOTO_VIDEO_COMPLETE,
        self::WEDDING_VIDEO_ONLY_FULL_DAY,
        self::WEDDING_VIDEO_FULL_DAY,
        self::CEREMONY_AND_PORTRAITS,
        self::WEDDING_PHOTO_8_HOURS,
        self::PORTRAIT_AND_FAMILY,
        self::STUDIO_PHOTOGRAPHY,
    ];

    /** @var array<string,array{name:string,price:string}> */
    private const DEFINITIONS = [
        self::WEDDING_VIDEO_FULL_DAY => ['name' => 'Dva kameramani', 'price' => '25 000 Kč'],
        self::CEREMONY_AND_PORTRAITS => ['name' => 'Focení obřadu a portrétů', 'price' => '7 000 Kč'],
        self::WEDDING_PHOTO_8_HOURS => ['name' => 'Svatební focení do 8 hodin', 'price' => '10 000 Kč'],
        self::WEDDING_PHOTO_FULL_DAY => ['name' => 'Celodenní svatební focení', 'price' => '15 000 Kč'],
        self::PHOTO_VIDEO_COMPLETE => ['name' => 'Kompletní balíček foto + video', 'price' => '32 000 Kč'],
        self::PORTRAIT_AND_FAMILY => ['name' => 'Portrétní a rodinné focení', 'price' => '1 800 Kč'],
        self::STUDIO_PHOTOGRAPHY => ['name' => 'Fotografování v ateliéru', 'price' => '2 600 Kč'],
        self::WEDDING_VIDEO_ONLY_FULL_DAY => ['name' => 'Celodenní svatební video', 'price' => '17 000 Kč'],
    ];

    public static function idForSlot(int $slot): string
    {
        return self::SLOT_IDS[$slot - 1] ?? '';
    }

    /** Inverse of idForSlot(); 0 for an unknown id. */
    public static function slotForId(string $id): int
    {
        $index = array_search($id, self::SLOT_IDS, true);

        return $index === false ? 0 : $index + 1;
    }

    /** @return array{id:string,name:string,price:string}|null */
    public static function resolve(?string $id): ?array
    {
        $id = trim((string) $id);
        if ($id === '' || !isset(self::DEFINITIONS[$id])) {
            return null;
        }

        return ['id' => $id] + self::DEFINITIONS[$id];
    }
}
