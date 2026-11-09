<?php

declare(strict_types=1);

use App\Services\PortfolioVideoSchema;

function register_portfolio_video_schema_tests(callable $test, callable $assert): void
{
    $test('five Search Console examples have matching primary video thumbnails', static function () use ($assert): void {
        foreach (['su0hKnBnCKw', '5ZlVVcIu10U', 'b9WhNN8VYhc', 'L-zaa5hj-aU', 'DBWKJ46eM4c'] as $id) {
            foreach (['https://youtu.be/' . $id, 'https://www.youtube.com/watch?v=' . $id, 'https://www.youtube.com/embed/' . $id] as $url) {
                $entry = ['title' => 'Svatební video', 'video_url' => $url, 'published_at' => '2023-10-14T12:00:00+02:00'];
                $data = PortfolioVideoSchema::build(['domain' => 'https://example.test'], $entry, 'https://example.test/wedding', '');
                $assert($data['thumbnailUrl'] === 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg');
                $assert($data['embedUrl'] === 'https://www.youtube.com/embed/' . $id);
                $assert($data['name'] === 'Svatební video');
                $assert((new DateTimeImmutable($data['uploadDate']))->getTimestamp() === (new DateTimeImmutable($entry['published_at']))->getTimestamp(), 'Publication instant must survive regardless of the test process timezone.');
            }
        }
    });
    $test('portfolio images remain preferred and become absolute in video schema', static function () use ($assert): void {
        $entry = ['video_url' => 'https://youtu.be/su0hKnBnCKw'];
        foreach ([
            '/media/cover.jpg' => 'https://example.test/media/cover.jpg',
            'https://images.example.test/cover.jpg' => 'https://images.example.test/cover.jpg',
            '//images.example.test/cover.jpg' => 'https://images.example.test/cover.jpg',
        ] as $input => $expected) {
            $data = PortfolioVideoSchema::build(['domain' => 'https://example.test/'], $entry, 'https://example.test/wedding', $input);
            $assert($data['thumbnailUrl'] === $expected, 'Use the chosen portfolio image rather than a site logo.');
        }
        $assert(PortfolioVideoSchema::build([], [], 'https://example.test/photo') === [], 'Photo-only entries must not acquire video markup.');
        $vimeo = PortfolioVideoSchema::build(['domain' => 'https://example.test'], ['video_url' => 'https://vimeo.com/12345'], 'https://example.test/wedding', '/media/vimeo-cover.jpg');
        $assert($vimeo['thumbnailUrl'] === 'https://example.test/media/vimeo-cover.jpg');
        $assert(PortfolioVideoSchema::build([], ['video_url' => 'https://vimeo.com/12345'], 'https://example.test/wedding') === [], 'Missing Vimeo image must not produce incomplete VideoObject or a fake thumbnail.');
    });
    $test('live and standalone portfolio export use the same video schema builder', static function () use ($assert): void {
        foreach (['public/index.php', 'app/Services/PortfolioPublishService.php'] as $file) {
            $assert(str_contains(file_get_contents(dirname(__DIR__) . '/' . $file), 'PortfolioVideoSchema::build('));
        }
    });
    $test('standalone export contains usable video metadata for its displayed first video', static function () use ($assert): void {
        $repo = new class extends \App\Repositories\PortfolioRepository {
            public function __construct() {}
            public function getMedia(int $portfolioId): array { return []; }
            public function getVideos(int $portfolioId): array {
                return [['url' => 'https://youtu.be/su0hKnBnCKw', 'embed_url' => 'https://www.youtube.com/embed/su0hKnBnCKw']];
            }
        };
        $slug = 'schema-test-' . bin2hex(random_bytes(8));
        $directory = dirname(__DIR__) . '/storage/published/nase-prace/' . $slug;
        try {
            (new \App\Services\PortfolioPublishService())->publishEntry([
                'id' => 0, 'slug' => $slug, 'title' => 'Svatební video',
                'video_url' => 'https://youtu.be/DBWKJ46eM4c',
                'published_at' => '2023-10-14T12:00:00+02:00',
            ], $repo, ['name' => 'Test', 'domain' => 'https://example.test']);
            $html = file_get_contents($directory . '/index.html');
            preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
            $videos = [];
            foreach ($matches[1] as $json) {
                $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
                if (($data['@type'] ?? '') === 'VideoObject') $videos[] = $data;
            }
            $assert(count($videos) === 1, 'Export must contain exactly one primary VideoObject.');
            $assert($videos[0]['thumbnailUrl'] === 'https://img.youtube.com/vi/su0hKnBnCKw/hqdefault.jpg');
            $assert(str_contains($html, 'src="' . $videos[0]['embedUrl'] . '"'), 'Metadata must describe the displayed video even if the mirror column differs.');
        } finally {
            if (is_file($directory . '/index.html')) unlink($directory . '/index.html');
            if (is_dir($directory)) rmdir($directory);
        }
    });
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require dirname(__DIR__) . '/vendor/autoload.php';
    \App\Core\Env::load(dirname(__DIR__) . '/.env');
    ob_start();
    date_default_timezone_set('Europe/Prague');
    $failures = 0;
    register_portfolio_video_schema_tests(static function (string $name, callable $run) use (&$failures): void {
        try { $run(); echo "PASS: $name\n"; }
        catch (Throwable $e) { $failures++; echo "FAIL: $name: {$e->getMessage()}\n"; }
    }, static function (bool $condition, string $message = 'Assertion failed'): void {
        if (!$condition) throw new RuntimeException($message);
    });
    exit($failures === 0 ? 0 : 1);
}
