<?php

namespace App\Services;

use PDO;

final class DemoStateFingerprintService
{
    public const VERSION = 'demo-state-v1';

    private PDO $db;
    private string $basePath;

    public function __construct(PDO $db, ?string $basePath = null)
    {
        $this->db = $db;
        $this->basePath = rtrim($basePath ?: dirname(__DIR__, 2), '/\\');
    }

    public function fingerprint(): string
    {
        return self::fingerprintPayload($this->snapshot());
    }

    public function report(): array
    {
        $snapshot = $this->snapshot();
        return [
            'version' => self::VERSION,
            'fingerprint' => self::fingerprintPayload($snapshot),
            'snapshot' => $snapshot,
        ];
    }

    public function snapshot(): array
    {
        $site = $this->one('SELECT * FROM sites ORDER BY id ASC LIMIT 1');
        if (!$site) {
            throw new \RuntimeException('Fingerprint nelze vytvorit: tabulka sites nema zadny web.');
        }

        $siteId = (int)$site['id'];
        $domain = rtrim((string)($site['domain'] ?? ''), '/');

        $mediaRows = $this->all(
            'SELECT id, original_filename, storage_path, alt_text, title, width, height, mime_type
             FROM media WHERE site_id = ? ORDER BY original_filename, id',
            [$siteId]
        );
        $mediaNames = [];
        $replacements = [];
        $media = [];
        foreach ($mediaRows as $row) {
            $id = (int)$row['id'];
            $name = (string)$row['original_filename'];
            $path = str_replace('\\', '/', (string)$row['storage_path']);
            $mediaNames[$id] = $name;
            $this->addMediaPathReplacements($replacements, $path, 'media://' . $name);

            $absolutePath = $this->basePath . '/' . ltrim($path, '/');
            if (!is_file($absolutePath)) {
                throw new \RuntimeException('Fingerprint nalezl chybejici medium: ' . $path);
            }

            $media[] = [
                'original_filename' => $name,
                'alt_text' => $row['alt_text'],
                'title' => $row['title'],
                'width' => $row['width'],
                'height' => $row['height'],
                'mime_type' => $row['mime_type'],
                'sha256' => hash_file('sha256', $absolutePath),
            ];
        }

        $variantRows = $this->all(
            'SELECT media_id, format, width, height, path FROM media_variants ORDER BY media_id, format, width, height, id'
        );
        $variants = [];
        foreach ($variantRows as $row) {
            $mediaId = (int)$row['media_id'];
            if (!isset($mediaNames[$mediaId])) {
                throw new \RuntimeException('Fingerprint nalezl variantu bez media zaznamu: ' . $mediaId);
            }
            $logicalPath = 'media://' . $mediaNames[$mediaId] . '#' . (string)$row['format'];
            $this->addMediaPathReplacements($replacements, (string)$row['path'], $logicalPath);
            $variants[] = [
                'media' => $mediaNames[$mediaId],
                'format' => $row['format'],
                'width' => $row['width'],
                'height' => $row['height'],
            ];
        }

        uksort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $snapshot = [
            'profile' => [
                'version' => self::VERSION,
                'expected_theme' => DemoContentSeeder::DEFAULT_THEME_KEY,
                'database_theme' => trim((string)($site['active_theme_key'] ?? '')),
                'state_file_theme' => (new ThemeTemplateRegistry(
                    $this->basePath . '/Templates',
                    $this->basePath . '/storage/active-theme-key.txt'
                ))->getPersistedActiveThemeKey(),
            ],
            'site' => $this->pick($site, [
                'name', 'default_language', 'layout_html', 'layout_css', 'layout_js',
                'layout_nav_home', 'layout_nav_sub', 'media_image_quality', 'video_overlay_opacity',
                'cookie_consent_enabled', 'cookie_consent_analytics_enabled',
                'cookie_consent_marketing_enabled',
            ]),
            'templates' => $this->all(
                'SELECT name, html_source, css_source, js_source FROM templates WHERE site_id = ? ORDER BY name, id',
                [$siteId]
            ),
            'pages' => $this->all(
                'SELECT p.title, p.slug, p.url_path, p.language, p.status, t.name AS template
                 FROM pages p LEFT JOIN templates t ON t.id = p.template_id
                 WHERE p.site_id = ? ORDER BY p.url_path, p.slug',
                [$siteId]
            ),
            'content_blocks' => $this->all(
                'SELECT p.url_path AS page, b.block_key, b.block_type, b.label, b.content,
                        b.autofill_disabled, b.sort_order
                 FROM content_blocks b JOIN pages p ON p.id = b.page_id
                 WHERE p.site_id = ? ORDER BY p.url_path, b.sort_order, b.block_key',
                [$siteId]
            ),
            'media' => $media,
            'media_variants' => $variants,
            'seo' => $this->all(
                'SELECT p.url_path AS page, s.meta_title, s.meta_description, s.meta_keywords,
                        s.canonical_url, s.robots_index, s.robots_follow, s.og_title, s.og_description,
                        om.original_filename AS og_image, s.twitter_title, s.twitter_description,
                        tm.original_filename AS twitter_image, s.schema_type, s.schema_json
                 FROM seo_settings s JOIN pages p ON p.id = s.page_id
                 LEFT JOIN media om ON om.id = s.og_image_id
                 LEFT JOIN media tm ON tm.id = s.twitter_image_id
                 WHERE p.site_id = ? ORDER BY p.url_path',
                [$siteId]
            ),
            'blog_posts' => $this->all(
                'SELECT b.title, b.slug, b.excerpt, b.content, m.original_filename AS featured_image,
                        b.status, b.meta_title, b.meta_description
                 FROM blog_posts b LEFT JOIN media m ON m.id = b.featured_image_id
                 WHERE b.site_id = ? ORDER BY b.slug',
                [$siteId]
            ),
            'portfolio_entries' => $this->all(
                'SELECT title, slug, description, video_url, status, sort_order, meta_title, meta_description
                 FROM portfolio_entries WHERE site_id = ? ORDER BY sort_order, slug',
                [$siteId]
            ),
            'portfolio_media' => $this->all(
                'SELECT p.slug AS portfolio, m.original_filename AS media, pm.sort_order
                 FROM portfolio_media pm
                 JOIN portfolio_entries p ON p.id = pm.portfolio_id
                 JOIN media m ON m.id = pm.media_id
                 WHERE p.site_id = ? ORDER BY p.slug, pm.sort_order, m.original_filename',
                [$siteId]
            ),
            'blog_media' => $this->all(
                'SELECT b.slug AS blog_post, m.original_filename AS media, bm.sort_order
                 FROM blog_media bm
                 JOIN blog_posts b ON b.id = bm.blog_post_id
                 JOIN media m ON m.id = bm.media_id
                 WHERE b.site_id = ? ORDER BY b.slug, bm.sort_order, m.original_filename',
                [$siteId]
            ),
            'faq_items' => $this->all(
                'SELECT question, answer, sort_order, is_active FROM faq_items WHERE site_id = ? ORDER BY sort_order, id',
                [$siteId]
            ),
            'service_items' => $this->all(
                'SELECT title, description, image_url, icon_url, button_text, button_url, sort_order, is_active
                 FROM service_items WHERE site_id = ? ORDER BY sort_order, id',
                [$siteId]
            ),
            'contact_info' => $this->all(
                'SELECT phone_1, phone_2, phone_3, email, address_1, address_2, maps_url,
                        social_1_type, social_1_url, social_2_type, social_2_url,
                        social_3_type, social_3_url, social_4_type, social_4_url,
                        social_5_type, social_5_url, form_email
                 FROM contact_info WHERE site_id = ? ORDER BY id',
                [$siteId]
            ),
            'reviews' => $this->all(
                'SELECT author_name, context_label, rating, review_text, sort_order, is_active
                 FROM reviews WHERE site_id = ? ORDER BY sort_order, id',
                [$siteId]
            ),
        ];

        return self::canonicalize($this->normalizeValue($snapshot, $domain, $replacements));
    }

    public static function fingerprintPayload(array $payload): string
    {
        $json = json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Fingerprint payload nelze serializovat do JSON.');
        }
        return hash('sha256', $json);
    }

    public static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }
        if (!self::isList($value)) {
            ksort($value, SORT_STRING);
        }
        return $value;
    }

    private static function isList(array $value): bool
    {
        $expected = 0;
        foreach ($value as $key => $_) {
            if ($key !== $expected++) {
                return false;
            }
        }
        return true;
    }

    private function normalizeValue(mixed $value, string $domain, array $replacements): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeValue($item, $domain, $replacements);
            }
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($domain !== '') {
            $value = str_replace($domain, '{APP_URL}', $value);
        }
        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    private function addMediaPathReplacements(array &$replacements, string $path, string $logicalPath): void
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '') {
            return;
        }
        $replacements[$path] = $logicalPath;
        $replacements['/' . $path] = $logicalPath;
        if (str_starts_with($path, 'storage/media/')) {
            $publicPath = substr($path, strlen('storage/media/'));
            $replacements['media/' . $publicPath] = $logicalPath;
            $replacements['/media/' . $publicPath] = $logicalPath;
        }
    }

    private function pick(array $row, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $row[$key] ?? null;
        }
        return $result;
    }

    private function one(string $sql, array $params = []): ?array
    {
        $rows = $this->all($sql, $params);
        return $rows[0] ?? null;
    }

    private function all(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
