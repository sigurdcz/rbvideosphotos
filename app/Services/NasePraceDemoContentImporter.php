<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MediaRepository;
use App\Repositories\PortfolioRepository;
use PDO;
use RuntimeException;

final class NasePraceDemoContentImporter
{
    private const CONTENT_MARKER = '# Schválený redakční text';

    /** @var list<array{article:string,directory:string,date_exception?:string}> */
    private const MANIFEST = [
        ['article' => '01-svatba-arboretum-bila-lhota-namest-na-hane.md', 'directory' => 'Helena a Tomáš Hufovi 17.9.22'],
        ['article' => '02-svatba-penzion-fojtstvi-olomouc.md', 'directory' => 'Nikola a Jirka Kaňkovští  20.5.2023'],
        ['article' => '03-svatba-hrad-sternberk-paseka.md', 'directory' => 'Markéta a Petr Pláškovi 17.6.23'],
        ['article' => '04-podzimni-svatba-u-robinsona.md', 'directory' => 'Ester a Tomáš Krůpovi 14.10.23'],
        ['article' => '05-svatba-troubelice.md', 'directory' => 'Marie a Miroslav 1.6.2024'],
        ['article' => '06-svatba-rybnik-sobacov.md', 'directory' => 'Michaela a Jan Jungligovi  27.7.2024'],
        ['article' => '07-svatba-la-futerra.md', 'directory' => 'Iveta a René Gruntovi  17.8.2024'],
        ['article' => '08-svatba-strelnice-jivova-lucie-miroslav.md', 'directory' => 'Lucie a Miroslav Provazovi 7.6.25'],
        ['article' => '09-svatba-best-hospudka-ve-stodole.md', 'directory' => 'Pája a Marek Krylovi 26.9.2025'],
        ['article' => '10-svatba-jivova-simona-michal.md', 'directory' => 'Simona a Michal Novákovi 6.9.25'],
        [
            'article' => '11-svatba-u-robinsona-martina-jaroslav.md',
            'directory' => 'Martina a Jaroslav Žáčkovi 2.8.2025',
            'date_exception' => 'YAML uvádí 2025-09-02, adresář 2.8.2025; vazba je schválena podle unikátních jmen Martina a Jaroslav Žáčkovi.',
        ],
        ['article' => '12-svatba-ruzove-udoli.md', 'directory' => 'Ivanka a Lubomír Nevěřilovi 6.6.2026'],
    ];

    private PDO $db;
    private MediaIngestionService $ingestion;
    private MediaRepository $media;
    private PortfolioRepository $portfolio;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ingestion = new MediaIngestionService();
        $this->media = new MediaRepository();
        $this->portfolio = new PortfolioRepository();
    }

    /** @return array{articles:int,media:int,warnings:list<string>} */
    public function import(int $siteId): array
    {
        $root = dirname(__DIR__, 2) . '/Data pro clanky/RB_Videos_Photos_NASE_PRACE/nase-prace-md';
        if (!is_dir($root)) {
            throw new RuntimeException('Chybí zdroj demo obsahu Naše práce: ' . $root);
        }

        $this->removeLegacyDemoEntries($siteId);

        $records = [];
        $warnings = [];
        foreach (self::MANIFEST as $item) {
            $record = $this->parseArticle($root . '/' . $item['article']);
            $record['image_directory'] = $root . '/' . $item['directory'];
            $record['date_exception'] = $item['date_exception'] ?? null;
            $this->validateRecord($record);
            if ($record['date_exception']) {
                $warnings[] = $item['article'] . ': ' . $record['date_exception'];
            }
            $records[] = $record;
        }

        usort($records, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
        $mediaCount = 0;
        foreach ($records as $index => $record) {
            $portfolioId = $this->upsertEntry($siteId, $record, $index + 1);
            $mediaIds = [];
            $images = glob($record['image_directory'] . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
            sort($images, SORT_NATURAL | SORT_FLAG_CASE);
            if ($images === []) {
                throw new RuntimeException('Ke článku nejsou přiřazeny fotografie: ' . $record['slug']);
            }
            foreach ($images as $imageIndex => $imagePath) {
                $mediaIds[] = $this->ingestImage($siteId, $record, $imagePath, $imageIndex + 1);
                $mediaCount++;
            }
            $this->portfolio->setMedia($portfolioId, $mediaIds);
        }

        return ['articles' => count($records), 'media' => $mediaCount, 'warnings' => $warnings];
    }

    private function removeLegacyDemoEntries(int $siteId): void
    {
        $legacySlugs = [
            'svatebni-foto-video-olomouc',
            'svatebni-fotograf-brno',
            'rodinne-portretni-foceni',
            'reklamni-foto-video-olomouc',
            'portretni-foceni-olomouc',
        ];
        $stmt = $this->db->prepare('SELECT id FROM portfolio_entries WHERE site_id = ? AND slug = ? LIMIT 1');
        foreach ($legacySlugs as $slug) {
            $stmt->execute([$siteId, $slug]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                $this->portfolio->delete($id);
            }
        }
    }

    /** @return array<string,mixed> */
    private function parseArticle(string $path): array
    {
        $source = is_file($path) ? file_get_contents($path) : false;
        if ($source === false) {
            throw new RuntimeException('Nelze načíst zdrojový článek: ' . $path);
        }
        if (!preg_match('/\A---\R(.*?)\R---\R/su', $source, $frontMatterMatch)) {
            throw new RuntimeException('Neplatná YAML hlavička: ' . basename($path));
        }
        $front = $frontMatterMatch[1];
        $markerPosition = strpos($source, self::CONTENT_MARKER);
        if ($markerPosition === false) {
            throw new RuntimeException('Chybí schválený redakční text: ' . basename($path));
        }
        $markdown = trim(substr($source, $markerPosition + strlen(self::CONTENT_MARKER)));

        return [
            'source' => basename($path),
            'slug' => $this->yamlScalar($front, 'slug'),
            'title' => $this->yamlScalar($front, 'title'),
            'target_path' => $this->yamlScalar($front, 'target_path'),
            'meta_title' => $this->yamlNestedScalar($front, 'seo', 'meta_title'),
            'meta_description' => $this->yamlNestedScalar($front, 'seo', 'meta_description'),
            'couple' => $this->yamlNestedScalar($front, 'event', 'couple'),
            'date' => trim($this->yamlNestedScalar($front, 'event', 'date_iso'), "'\"") ,
            'video_url' => $this->yamlNestedScalar($front, 'event', 'video_url'),
            'description' => $this->markdownToHtml($markdown),
        ];
    }

    private function yamlScalar(string $yaml, string $key): string
    {
        if (!preg_match('/^' . preg_quote($key, '/') . ':\s*(.+)$/mu', $yaml, $match)) {
            throw new RuntimeException('V YAML chybí pole ' . $key);
        }
        return trim($match[1], " \t'\"");
    }

    private function yamlNestedScalar(string $yaml, string $section, string $key): string
    {
        if (!preg_match('/^' . preg_quote($section, '/') . ':\R((?: {2}.*(?:\R|$))+)/mu', $yaml, $sectionMatch)) {
            throw new RuntimeException('V YAML chybí sekce ' . $section);
        }
        $block = $sectionMatch[1];
        if (!preg_match('/^  ' . preg_quote($key, '/') . ':\s*(.*)((?:\R {4}[^\r\n]+)*)/mu', $block, $match)) {
            throw new RuntimeException('V YAML chybí pole ' . $section . '.' . $key);
        }
        $value = trim($match[1]);
        if ($match[2] !== '') {
            $value .= ' ' . trim((string)preg_replace('/\R {4}/u', ' ', $match[2]));
        }
        return trim($value, " \t'\"");
    }

    /** @param array<string,mixed> $record */
    private function validateRecord(array $record): void
    {
        $expectedPath = '/nase-prace/' . $record['slug'] . '/';
        if ($record['target_path'] !== $expectedPath) {
            throw new RuntimeException('Nesouhlasí target_path u ' . $record['source']);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $record['date'])) {
            throw new RuntimeException('Neplatné datum u ' . $record['source']);
        }
        if (!is_dir($record['image_directory'])) {
            throw new RuntimeException('Chybí přiřazená složka fotografií pro ' . $record['source']);
        }
    }

    /** @param array<string,mixed> $record */
    private function upsertEntry(int $siteId, array $record, int $sortOrder): int
    {
        $stmt = $this->db->prepare('SELECT id FROM portfolio_entries WHERE site_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$siteId, $record['slug']]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        $data = [
            'site_id' => $siteId,
            'title' => $record['title'],
            'slug' => $record['slug'],
            'description' => $record['description'],
            'video_url' => $record['video_url'] !== '' ? $record['video_url'] : null,
            'status' => 'published',
            'published_at' => $record['date'] . ' 12:00:00',
            'sort_order' => $sortOrder,
            'meta_title' => $record['meta_title'],
            'meta_description' => $record['meta_description'],
        ];
        if ($id > 0) {
            $this->portfolio->update($id, $data);
            return $id;
        }
        return $this->portfolio->create($data);
    }

    /** @param array<string,mixed> $record */
    private function ingestImage(int $siteId, array $record, string $sourcePath, int $position): int
    {
        $originalFilename = sprintf('nase-prace-%s-%02d.%s', $record['slug'], $position, strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)));
        $stmt = $this->db->prepare('SELECT id, storage_path FROM media WHERE site_id = ? AND original_filename = ? LIMIT 1');
        $stmt->execute([$siteId, $originalFilename]);
        $existing = $stmt->fetch();
        if ($existing) {
            $this->assertThumbExists((int)$existing['id'], (string)$existing['storage_path']);
            return (int)$existing['id'];
        }

        $alt = $position === 1
            ? $record['title'] . ' – svatební fotografie RB Videos & Photos'
            : $record['title'] . ' – fotografie ' . $position;
        $result = $this->ingestion->ingestUploadedFile($siteId, $sourcePath, $originalFilename, $alt, $record['title'], false);
        $mediaId = (int)($result['media_id'] ?? 0);
        if ($mediaId <= 0) {
            throw new RuntimeException('Media Library nevrátila ID pro ' . $sourcePath);
        }
        $this->assertThumbExists($mediaId, (string)($result['storage_path'] ?? ''));
        return $mediaId;
    }

    private function assertThumbExists(int $mediaId, string $storagePath): void
    {
        $absoluteOriginal = dirname(__DIR__, 2) . '/' . ltrim($storagePath, '/');
        if (!is_file($absoluteOriginal)) {
            throw new RuntimeException('Chybí originální soubor média #' . $mediaId);
        }
        $thumb = null;
        foreach ($this->media->variantsByMediaId($mediaId) as $variant) {
            if (($variant['format'] ?? '') === 'thumb') {
                $thumb = (string)$variant['path'];
                break;
            }
        }
        if ($thumb === null || !is_file(dirname(__DIR__, 2) . '/' . ltrim($thumb, '/'))) {
            throw new RuntimeException('Chybí thumbnail derivát média #' . $mediaId);
        }
    }

    private function markdownToHtml(string $markdown): string
    {
        $lines = preg_split('/\R/u', $markdown) ?: [];
        if (isset($lines[0]) && str_starts_with(trim($lines[0]), '# ')) {
            array_shift($lines); // H1 vykresluje hero detailu.
        }
        $html = [];
        $paragraph = [];
        $flush = static function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode(' ', $paragraph) . '</p>';
                $paragraph = [];
            }
        };
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $line, $heading)) {
                $flush();
                $level = strlen($heading[1]);
                $html[] = '<h' . $level . '>' . $this->inlineMarkdown($heading[2]) . '</h' . $level . '>';
                continue;
            }
            $paragraph[] = $this->inlineMarkdown($line);
        }
        $flush();
        return implode("\n", $html);
    }

    private function inlineMarkdown(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = (string)preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/u', static function (array $match): string {
            return '<a href="' . $match[2] . '">' . $match[1] . '</a>';
        }, $escaped);
        return (string)preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped);
    }
}
