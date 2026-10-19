<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\PortfolioRepository;
use PDO;
use RuntimeException;

final class ApprovedVideoPortfolioService
{
    /** User-approved order, 2026-10-17. Titles/dates verified through YouTube oEmbed. */
    public const MANIFEST = [
        [
            'slug' => 'svatba-ivanka-a-lubomir-neverilovi',
            'title' => 'Ivanka a Lubomír Nevěřilovi',
            'meta_title' => 'Ivanka a Lubomír Nevěřilovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Ivanka a Lubomír Nevěřilovi.',
            'date' => '2026-06-06',
            'video_url' => 'https://www.youtube.com/watch?v=L-zaa5hj-aU',
        ],
        [
            'slug' => 'svatba-simona-a-michal-novakovi',
            'title' => 'Simona a Michal Novákovi',
            'meta_title' => 'Simona a Michal Novákovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Simona a Michal Novákovi.',
            'date' => '2025-09-06',
            'video_url' => 'https://www.youtube.com/watch?v=cXU7Ty-kjyA',
        ],
        [
            'slug' => 'svatba-lucie-a-miroslav-provazovi',
            'title' => 'Lucie a Miroslav Provazovi',
            'meta_title' => 'Lucie a Miroslav Provazovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Lucie a Miroslav Provazovi.',
            'date' => '2025-06-07',
            'video_url' => 'https://www.youtube.com/watch?v=hYj2w3P5rK8',
        ],
        [
            'slug' => 'svatba-pavlina-a-marek-krylovi',
            'title' => 'Pavlína a Marek Krylovi',
            'meta_title' => 'Pavlína a Marek Krylovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Pavlína a Marek Krylovi.',
            'date' => '2025-09-26',
            'video_url' => 'https://www.youtube.com/watch?v=5ZlVVcIu10U',
        ],
        [
            'slug' => 'svatba-marie-a-miroslav-video',
            'title' => 'Marie a Miroslav',
            'meta_title' => 'Marie a Miroslav - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Marie a Miroslav.',
            'date' => '2024-06-01',
            'video_url' => 'https://www.youtube.com/watch?v=O5i9TzCms5c',
        ],
        [
            'slug' => 'svatba-martina-a-jaroslav-zackovi',
            'title' => 'Martina a Jaroslav Žáčkovi',
            'meta_title' => 'Martina a Jaroslav Žáčkovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Martina a Jaroslav Žáčkovi.',
            'date' => '2025-08-02',
            'video_url' => 'https://www.youtube.com/watch?v=b9WhNN8VYhc',
        ],
        [
            'slug' => 'svatba-nikola-a-jirka-kankovsti',
            'title' => 'Nikola a Jirka Kaňkovští',
            'meta_title' => 'Nikola a Jirka Kaňkovští - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Nikola a Jirka Kaňkovští.',
            'date' => '2023-05-20',
            'video_url' => 'https://www.youtube.com/watch?v=C1wL3QFZhn0',
        ],
        [
            'slug' => 'svatba-marketa-a-petr-plaskovi',
            'title' => 'Markéta a Petr Pláškovi',
            'meta_title' => 'Markéta a Petr Pláškovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Markéta a Petr Pláškovi.',
            'date' => '2023-06-17',
            'video_url' => 'https://www.youtube.com/watch?v=DBWKJ46eM4c',
        ],
        [
            'slug' => 'svatba-ester-a-tomas-krupovi',
            'title' => 'Ester a Tomáš Krůpovi',
            'meta_title' => 'Ester a Tomáš Krůpovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Ester a Tomáš Krůpovi.',
            'date' => '2023-10-14',
            'video_url' => 'https://www.youtube.com/watch?v=su0hKnBnCKw',
        ],
        [
            'slug' => 'svatba-michaela-a-jan-junglingovi',
            'title' => 'Michaela a Jan Jünglingovi',
            'meta_title' => 'Michaela a Jan Jünglingovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Michaela a Jan Jünglingovi.',
            'date' => '2024-07-27',
            'video_url' => 'https://www.youtube.com/watch?v=1z-qckQn5UU',
        ],
        [
            'slug' => 'svatba-iveta-a-rene-gruntovi',
            'title' => 'Iveta a René Gruntovi',
            'meta_title' => 'Iveta a René Gruntovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Iveta a René Gruntovi.',
            'date' => '2024-08-17',
            'video_url' => 'https://www.youtube.com/watch?v=o-1xGllIShY',
        ],
        [
            'slug' => 'svatba-tomas-a-helena-hufovi',
            'title' => 'Tomáš a Helena Hufovi',
            'meta_title' => 'Tomáš a Helena Hufovi - svatební video | RB Videos & Photos',
            'excerpt' => 'Svatební video: Tomáš a Helena Hufovi.',
            'date' => '2022-09-17',
            'video_url' => 'https://www.youtube.com/watch?v=GBKJ_VB_Nmw',
        ],
    ];

    public function replace(int $siteId): int
    {
        $db = Database::getInstance();
        $repo = new PortfolioRepository();
        $entries = $repo->allBySite($siteId);
        $wanted = array_column(self::MANIFEST, null, 'slug');
        $bySlug = [];
        $photoOffset = 0;
        foreach ($entries as $entry) {
            $bySlug[$entry['slug']] = $entry;
            if ($entry['content_type'] !== 'video') {
                $photoOffset = max($photoOffset, (int)$entry['sort_order']);
                if (isset($wanted[$entry['slug']])) {
                    throw new RuntimeException('Slug schváleného videa koliduje s fotografickou galerií: ' . $entry['slug']);
                }
            }
        }
        if ($photoOffset + count(self::MANIFEST) > PortfolioRepository::MAX_SORT_ORDER) {
            throw new RuntimeException('Pořadí fotografií neponechává rozsah pro schválená videa.');
        }
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            foreach ($entries as $entry) {
                if ($entry['content_type'] !== 'video' || isset($wanted[$entry['slug']])) continue;
                if (!preg_match('/^[a-z0-9][a-z0-9-]*$/D', (string)$entry['slug'])) {
                    throw new RuntimeException('Neplatný slug starého video exportu; nic nemažte ručně.');
                }
                // Remove the obsolete export before deleting its DB owner, so failure
                // keeps the row available for a visible retry. Never remove media files.
                $export = dirname(__DIR__, 2) . '/storage/published/nase-prace/' . $entry['slug'] . '/index.html';
                if (is_link(dirname($export)) || is_link($export)) {
                    throw new RuntimeException('Video export je odkaz; automatické odstranění je odmítnuto.');
                }
                (new PortfolioPublishService())->removePublishedFiles((string)$entry['slug']);
                if (is_file($export)) throw new RuntimeException('Starý video export nelze odstranit: ' . $entry['slug']);
                $repo->delete((int)$entry['id']);
            }
            foreach (self::MANIFEST as $index => $record) {
                $data = [
                    'site_id' => $siteId, 'title' => $record['title'], 'slug' => $record['slug'],
                    'description' => '<p>' . htmlspecialchars($record['excerpt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
                    'video_url' => $record['video_url'], 'content_type' => 'video', 'status' => 'published',
                    'published_at' => $record['date'] . ' 12:00:00',
                    'sort_order' => $photoOffset + $index + 1,
                    'meta_title' => $record['meta_title'], 'meta_description' => $record['excerpt'],
                ];
                $existing = $bySlug[$record['slug']] ?? null;
                $same = $existing !== null;
                if ($existing) {
                    foreach ($data as $key => $value) {
                        if ((string)($existing[$key] ?? '') !== (string)$value) $same = false;
                    }
                    $videos = $repo->getVideos((int)$existing['id']);
                    if ($same && $repo->getMedia((int)$existing['id']) === [] && count($videos) === 1
                        && $videos[0]['url'] === $record['video_url'] && $videos[0]['sort_order'] === 0) continue;
                    $id = (int)$existing['id'];
                    $repo->update($id, $data);
                } else {
                    $id = $repo->create($data);
                }
                $repo->setMedia($id, []);
                $repo->setVideos($id, [$record['video_url']]);
            }
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $error) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $error;
        }
        return count(self::MANIFEST);
    }
}

