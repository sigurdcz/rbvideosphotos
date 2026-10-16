<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PortfolioRepository
{
    /** Highest value the INT UNSIGNED sort_order columns accept from a form. */
    public const MAX_SORT_ORDER = 1000000;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allBySite(int $siteId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE site_id = ? ORDER BY (sort_order = 0) ASC, sort_order ASC, id ASC');
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function allPublished(int $siteId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM portfolio_entries WHERE site_id = ? AND status = 'published' ORDER BY (sort_order = 0) ASC, sort_order ASC, id ASC");
        $stmt->execute([$siteId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM portfolio_entries WHERE slug = ? AND status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlugAdmin(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_entries WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $d): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO portfolio_entries (site_id, title, slug, description, video_url, content_type, status, published_at, sort_order, meta_title, meta_description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $d['site_id'],
            $d['title'],
            $d['slug'],
            $d['description']      ?? '',
            $d['video_url']        ?? null,
            $d['content_type']     ?? 'photo',
            $d['status']           ?? 'draft',
            $d['published_at']     ?? null,
            $d['sort_order']       ?? 0,
            $d['meta_title']       ?? null,
            $d['meta_description'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $d): void
    {
        $stmt = $this->db->prepare(
            'UPDATE portfolio_entries SET title=?, slug=?, description=?, video_url=?, content_type=?, status=?, published_at=?, sort_order=?, meta_title=?, meta_description=? WHERE id=?'
        );
        $stmt->execute([
            $d['title'],
            $d['slug'],
            $d['description']      ?? '',
            $d['video_url']        ?? null,
            $d['content_type']     ?? 'photo',
            $d['status']           ?? 'draft',
            $d['published_at']     ?? null,
            $d['sort_order']       ?? 0,
            $d['meta_title']       ?? null,
            $d['meta_description'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare('DELETE FROM portfolio_media  WHERE portfolio_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM portfolio_videos WHERE portfolio_id = ?')->execute([$id]);
        $this->db->prepare('DELETE FROM portfolio_entries WHERE id = ?')->execute([$id]);
    }

    /**
     * A sort_order the database can actually store. The column is INT UNSIGNED and
     * MySQL runs in strict mode, so a negative or absurd number would abort the
     * INSERT mid-rewrite and leave the gallery empty; anything out of range is simply
     * pulled back to the ends of the scale.
     */
    public static function clampSortOrder(mixed $value): int
    {
        return min(max(0, (int)$value), self::MAX_SORT_ORDER);
    }

    /**
     * Replace all media for a portfolio entry. $mediaIds is an ordered array of
     * media.id values. $sortById optionally maps media.id => the number typed in the
     * admin; without it the selection order becomes the order, exactly as before, so
     * the seeds and the API keep working untouched.
     *
     * @param array<mixed> $mediaIds
     * @param array<int|string,mixed>|null $sortById
     */
    public function setMedia(int $portfolioId, array $mediaIds, ?array $sortById = null): void
    {
        // The rewrite is a DELETE followed by INSERTs: without a transaction a single
        // rejected row would leave the entry with no photos at all.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare('DELETE FROM portfolio_media WHERE portfolio_id = ?')->execute([$portfolioId]);
            $stmt = $this->db->prepare('INSERT INTO portfolio_media (portfolio_id, media_id, sort_order) VALUES (?, ?, ?)');
            $seen = [];
            foreach (array_values($mediaIds) as $i => $mid) {
                $mid = (int)$mid;
                if ($mid <= 0 || isset($seen[$mid])) {
                    continue;
                }
                $seen[$mid] = true;
                $sort = $sortById === null ? $i : self::clampSortOrder($sortById[$mid] ?? 0);
                $stmt->execute([$portfolioId, $mid, $sort]);
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Return media rows joined with media table, ordered by sort_order.
     *  Includes thumb_path (≤300 px) and medium_path (≤900 px) variant paths when available. */
    public function getMedia(int $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, pm.sort_order AS pm_sort,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS thumb_path,
                    (SELECT mv.path FROM media_variants mv WHERE mv.media_id = m.id AND mv.format = ? LIMIT 1) AS medium_path
             FROM portfolio_media pm
             JOIN media m ON m.id = pm.media_id
             WHERE pm.portfolio_id = ?
             ORDER BY pm.sort_order ASC, pm.id ASC'
        );
        $stmt->execute(['thumb', 'medium', $portfolioId]);
        return $stmt->fetchAll();
    }

    /** Return the first media item (for thumbnail use). */
    public function getFirstMedia(int $portfolioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT m.* FROM portfolio_media pm JOIN media m ON m.id = pm.media_id
             WHERE pm.portfolio_id = ? ORDER BY pm.sort_order ASC, pm.id ASC LIMIT 1'
        );
        $stmt->execute([$portfolioId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * True only for URLs embedUrl() actually understands: a YouTube watch/short/embed
     * link with an 11 character id, or a numeric Vimeo id. Everything else - including
     * an empty string - is rejected, so this is the single definition of "a supported
     * video" for the admin form, the API and the repository.
     */
    public static function isSupportedVideoUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        return (bool)preg_match('~(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url)
            || (bool)preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url);
    }

    /**
     * Trim, drop empty and unsupported entries, keep the first occurrence of a
     * duplicate and reindex. An item is either a plain URL (which means "no number
     * typed", 0) or ['url' => ..., 'sort_order' => ...]. Pure - no database access.
     *
     * @param array<mixed> $videos
     * @return list<array{url:string,sort_order:int}>
     */
    public static function normalizeVideoItems(array $videos): array
    {
        $normalized = [];
        $seen = [];
        foreach ($videos as $video) {
            if (is_scalar($video)) {
                $url = trim((string)$video);
                $sort = 0;
            } elseif (is_array($video)) {
                $url = is_scalar($video['url'] ?? null) ? trim((string)$video['url']) : '';
                $sort = self::clampSortOrder($video['sort_order'] ?? 0);
            } else {
                continue;
            }

            if (!self::isSupportedVideoUrl($url) || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $normalized[] = ['url' => $url, 'sort_order' => $sort];
        }

        return $normalized;
    }

    /**
     * The one definition of "which video comes first": the typed number, and among
     * items sharing a number the order they were entered in (usort is stable).
     *
     * @param list<array{url:string,sort_order:int}> $items
     * @return list<array{url:string,sort_order:int}>
     */
    public static function orderVideoItems(array $items): array
    {
        usort($items, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

        return $items;
    }

    /**
     * @param array<mixed> $urls
     * @return list<string>
     */
    public static function normalizeVideoUrls(array $urls): array
    {
        return array_column(self::normalizeVideoItems($urls), 'url');
    }

    /**
     * Ordered original video URLs of an entry. Entries written by the seeds and the
     * demo importers only ever fill portfolio_entries.video_url, so a row without any
     * portfolio_videos falls back to that single URL instead of looking video-less.
     *
     * @return list<string>
     */
    public function getVideoUrls(int $portfolioId): array
    {
        return array_column($this->fetchVideoRows($portfolioId), 'url');
    }

    /**
     * @return list<array{url:string,sort_order:int}>
     */
    private function fetchVideoRows(int $portfolioId): array
    {
        $stmt = $this->db->prepare(
            'SELECT video_url, sort_order FROM portfolio_videos WHERE portfolio_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$portfolioId]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $url = trim((string)($row['video_url'] ?? ''));
            if ($url !== '') {
                $rows[] = ['url' => $url, 'sort_order' => (int)($row['sort_order'] ?? 0)];
            }
        }
        if ($rows !== []) {
            return $rows;
        }

        $legacy = $this->db->prepare('SELECT video_url FROM portfolio_entries WHERE id = ? LIMIT 1');
        $legacy->execute([$portfolioId]);
        $single = trim((string)($legacy->fetchColumn() ?: ''));

        return $single !== '' ? [['url' => $single, 'sort_order' => 0]] : [];
    }

    /**
     * The same list prepared for views: the original URL, its embed URL and the
     * YouTube thumbnail ('' for Vimeo, which needs a network call to resolve).
     *
     * @return list<array{url:string,embed_url:string,thumbnail:string,sort_order:int}>
     */
    public function getVideos(int $portfolioId): array
    {
        return array_map(static fn(array $row): array => [
            'url' => $row['url'],
            'embed_url' => self::embedUrl($row['url']),
            'thumbnail' => self::youtubeThumbnail($row['url']),
            'sort_order' => $row['sort_order'],
        ], $this->fetchVideoRows($portfolioId));
    }

    /**
     * Replace every video of an entry and keep portfolio_entries.video_url pointing at
     * the first one (NULL when the list ends up empty) - that mirror is what the
     * listing, the homepage tiles, the lightbox, the JSON-LD and the API still read.
     *
     * "First" means the lowest typed number, and among equal numbers the row the
     * editor put first.
     *
     * @param array<mixed> $videos plain URLs, or ['url' => ..., 'sort_order' => ...]
     */
    public function setVideos(int $portfolioId, array $videos): void
    {
        $items = self::orderVideoItems(self::normalizeVideoItems($videos));

        // A caller may already have a transaction open (the demo seeder wraps whole
        // runs in one), and PDO refuses a nested beginTransaction().
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->db->prepare('DELETE FROM portfolio_videos WHERE portfolio_id = ?')->execute([$portfolioId]);
            $insert = $this->db->prepare('INSERT INTO portfolio_videos (portfolio_id, video_url, sort_order) VALUES (?, ?, ?)');
            foreach ($items as $item) {
                $insert->execute([$portfolioId, $item['url'], $item['sort_order']]);
            }
            $this->db->prepare('UPDATE portfolio_entries SET video_url = ? WHERE id = ?')
                ->execute([$items[0]['url'] ?? null, $portfolioId]);
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Extract embeddable URL from a YouTube or Vimeo watch URL. */
    public static function embedUrl(string $url): string
    {
        if (preg_match('~(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
        }
        return $url;
    }

    /** Return YouTube hqdefault thumbnail URL, or empty string. */
    public static function youtubeThumbnail(string $url): string
    {
        if (preg_match('~(?:youtube\.com/watch\?.*v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
        }
        return '';
    }

    /**
     * The hand-typed order wins: entries are grouped by their sort_order, the groups
     * follow the number (0 = not numbered, which goes last), and only inside one group
     * - entries sharing a number - are photos and videos mixed so the grid does not
     * fall into one long block of photos followed by one of videos.
     *
     * @param list<array<string,mixed>> $entries each entry needs 'content_type' and 'sort_order'
     * @return list<array<string,mixed>>
     */
    public static function interleaveByContentType(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[(int)($entry['sort_order'] ?? 0)][] = $entry;
        }
        ksort($groups, SORT_NUMERIC);
        // 0 means "not numbered", so that group belongs at the end, behind everything
        // the editor did number - the same rule the SQL ordering uses.
        if (isset($groups[0]) && count($groups) > 1) {
            $unnumbered = $groups[0];
            unset($groups[0]);
            $groups[0] = $unnumbered;
        }

        $result = [];
        foreach ($groups as $group) {
            array_push($result, ...self::interleaveTypes($group));
        }

        return $result;
    }

    /**
     * A handful of photos followed by a video, repeating. The photo:video group size
     * adapts to whichever ratio the actual counts are, so galleries stay mixed no
     * matter how many photos or videos currently exist. When videos outnumber photos,
     * the surplus trails at the end. A one-sided list (no photos, or no videos) is
     * returned unchanged - there is nothing to interleave.
     *
     * Numbers are ignored between the two types; the order inside each type is kept.
     * The homepage sampler calls this directly, while the /nase-prace listing goes
     * through interleaveByContentType(), which keeps the strict number order.
     *
     * @param list<array<string,mixed>> $entries
     * @return list<array<string,mixed>>
     */
    public static function interleaveTypes(array $entries): array
    {
        $photos = [];
        $videos = [];
        foreach ($entries as $entry) {
            if (($entry['content_type'] ?? '') === 'video') {
                $videos[] = $entry;
            } else {
                $photos[] = $entry;
            }
        }
        if ($photos === [] || $videos === []) {
            return $entries;
        }

        $groupSize = max(1, (int)round(count($photos) / count($videos)));
        $result = [];
        $videoIndex = 0;
        foreach (array_chunk($photos, $groupSize) as $chunk) {
            array_push($result, ...$chunk);
            if ($videoIndex < count($videos)) {
                $result[] = $videos[$videoIndex++];
            }
        }
        while ($videoIndex < count($videos)) {
            $result[] = $videos[$videoIndex++];
        }
        return $result;
    }
}
