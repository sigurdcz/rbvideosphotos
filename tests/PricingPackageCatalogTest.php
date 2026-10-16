<?php

declare(strict_types=1);

use App\Domain\PricingPackageCatalog;

function register_pricing_package_catalog_tests(callable $test, callable $assert): void
{
    $test('pricing package catalog resolves only server-owned identifiers', function () use ($assert): void {
        $package = PricingPackageCatalog::resolve(PricingPackageCatalog::CEREMONY_AND_PORTRAITS);

        $assert($package !== null);
        $assert($package['id'] === 'obrad-portrety');
        $assert($package['name'] === 'Focení obřadu a portrétů');
        $assert($package['price'] === '7 000 Kč');
        $assert(PricingPackageCatalog::resolve('Focení obřadu a portrétů') === null);
        $assert(PricingPackageCatalog::resolve("obrad-portrety\r\nBcc: attacker@example.com") === null);
        $assert(PricingPackageCatalog::resolve('neznamy-balicek') === null);
    });

    $test('pricing package slots expose eight stable identifiers', function () use ($assert): void {
        $ids = [];
        for ($slot = 1; $slot <= 8; $slot++) {
            $ids[] = PricingPackageCatalog::idForSlot($slot);
        }

        $assert(count(array_unique($ids)) === 8);
        $assert(!in_array('', $ids, true));
        $assert(PricingPackageCatalog::idForSlot(1) === PricingPackageCatalog::WEDDING_PHOTO_FULL_DAY);
        $assert(PricingPackageCatalog::idForSlot(2) === PricingPackageCatalog::PHOTO_VIDEO_COMPLETE);
        $assert(PricingPackageCatalog::idForSlot(3) === PricingPackageCatalog::WEDDING_VIDEO_ONLY_FULL_DAY);
        $assert(PricingPackageCatalog::idForSlot(8) === PricingPackageCatalog::STUDIO_PHOTOGRAPHY);
        foreach ($ids as $index => $id) {
            $assert(PricingPackageCatalog::slotForId($id) === $index + 1);
        }
        $assert(PricingPackageCatalog::slotForId('neznamy-balicek') === 0);
        $assert(PricingPackageCatalog::idForSlot(9) === '');
    });
}
