<?php

declare(strict_types=1);

use App\Core\Env;
use App\Repositories\MediaRepository;
use App\Repositories\SiteRepository;
use App\Services\MediaIngestionService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

Env::load(dirname(__DIR__, 2) . '/.env');

$site = (new SiteRepository())->findFirst();
if (!$site) {
    throw new RuntimeException('Nelze importovat portréty týmu: web nebyl nalezen.');
}

$siteId = (int) $site['id'];
$media = new MediaRepository();
$ingestion = new MediaIngestionService();
$assets = [
    [
        'filename' => 'tomas-sincl-kameraman.png',
        'title' => 'Tomáš Šincl - kameraman',
        'alt' => 'Portrét Tomáše Šincla, kameramana RB Videos & Photos',
    ],
    [
        'filename' => 'zdenek-cernohlavek-fotograf.png',
        'title' => 'Zdeněk Černohlávek - fotograf',
        'alt' => 'Portrét Zdeňka Černohlávka, fotografa RB Videos & Photos',
    ],
];

foreach ($assets as $asset) {
    $existing = null;
    foreach ($media->allBySite($siteId) as $row) {
        if ((string) $row['original_filename'] === $asset['filename']) {
            $existing = $row;
            break;
        }
    }

    if ($existing) {
        echo 'REUSED ' . $asset['filename'] . ' #' . $existing['id'] . PHP_EOL;
        continue;
    }

    $source = __DIR__ . '/demo_assets/' . $asset['filename'];
    if (!is_file($source)) {
        throw new RuntimeException('Chybí zdrojový portrét: ' . $source);
    }

    $result = $ingestion->ingestUploadedFile(
        $siteId,
        $source,
        $asset['filename'],
        $asset['alt'],
        $asset['title'],
        false
    );

    echo 'IMPORTED ' . $asset['filename']
        . ' #' . $result['media_id']
        . ' variants=' . $result['variant_count']
        . PHP_EOL;
}
