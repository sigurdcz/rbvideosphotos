<?php

declare(strict_types=1);

use App\Services\HeroVideoMediaLookup;
use App\Services\Render\HeroMediaRenderer;
use App\Services\VideoMediaInfo;

/**
 * Video in the media library: what the browser reports is clamped, the admin can pick a
 * video for each hero width, and an empty hero field stays empty (the render side is in
 * ResponsiveHeroVideoTest).
 */
function register_hero_video_media_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);
    $read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);

    $test('video metadata from the browser is clamped, never trusted', function () use ($assert): void {
        $assert(
            VideoMediaInfo::sanitize(['width' => 1920, 'height' => 1080, 'duration' => 12.34])
                === ['width' => 1920, 'height' => 1080, 'duration' => 12.3],
            'Plausible metadata must pass through.'
        );
        foreach ([null, 'x', [], ['width' => 0, 'height' => 5], ['width' => -1, 'height' => 5], ['width' => 99999, 'height' => 5], ['width' => '1<script>', 'height' => 5]] as $hostile) {
            $info = VideoMediaInfo::sanitize($hostile);
            $assert($info['width'] === 0 && $info['height'] === 0, 'Implausible dimensions must collapse to unknown.');
        }
        $assert(VideoMediaInfo::sanitize(['width' => 10, 'height' => 10, 'duration' => 'NaN'])['duration'] === 0.0, 'A non-numeric duration is unknown.');
        $assert(VideoMediaInfo::sanitize(['width' => 10, 'height' => 10, 'duration' => 1e9])['duration'] === 0.0, 'An absurd duration is unknown.');
    });

    $test('video quality is named and heavy hero videos are flagged', function () use ($assert): void {
        $assert(VideoMediaInfo::qualityLabel(3840, 2160) === '4K', '3840x2160 is 4K.');
        $assert(VideoMediaInfo::qualityLabel(1920, 1080) === 'Full HD', '1920x1080 is Full HD.');
        $assert(VideoMediaInfo::qualityLabel(1080, 1920) === 'Full HD', 'Orientation must not change the class.');
        $assert(VideoMediaInfo::qualityLabel(1280, 720) === 'HD', '1280x720 is HD.');
        $assert(VideoMediaInfo::qualityLabel(640, 360) === 'SD', '640x360 is SD.');
        $assert(VideoMediaInfo::qualityLabel(0, 0) === '', 'Unknown size has no class.');

        $good = VideoMediaInfo::describe(1920, 1080, 8 * 1048576);
        $assert($good['warnings'] === [] && str_contains($good['label'], '1920×1080'), 'A light Full HD video raises no warning.');
        $heavy = VideoMediaInfo::describe(3840, 2160, 80 * 1048576);
        $assert(count($heavy['warnings']) === 2, 'A big 4K video must be flagged for resolution and size.');
        $assert(VideoMediaInfo::describe(0, 0, 100)['warnings'] !== [], 'Unknown resolution must be reported.');
    });

    $test('the media library keeps video thumbnails and dimensions, and the controller wires them', function () use ($assert, $read): void {
        $controller = $read('app/Controllers/MediaController.php');
        $assert(str_contains($controller, "'video_meta_' . \$index") && str_contains($controller, "'video_thumb_' . \$index"), 'The upload must read the per-file browser metadata and thumbnail.');
        $assert(str_contains($controller, 'is_uploaded_file'), 'The thumbnail must be a real uploaded file.');
        $assert(str_contains($controller, 'attachVideoDetails('), 'The upload must hand the details to the ingestion service.');
        $assert(str_contains($controller, "in_array(\$kind, ['all', 'image', 'video'], true)"), 'The picker kind must be whitelisted.');

        $service = $read('app/Services/MediaIngestionService.php');
        $assert(str_contains($service, 'IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP'), 'The thumbnail type must be whitelisted.');
        $assert(str_contains($service, 'createResizedWithinBudget($thumbTmpPath'), 'The thumbnail must be re-encoded, not stored as sent.');
        $assert(str_contains($read('app/Repositories/MediaRepository.php'), "mime_type IN ('video/mp4', 'video/webm', 'video/quicktime')"), 'The picker must list only videos the hero can play.');

        $media = $read('admin/views/media/index.php');
        $assert(str_contains($media, 'function probeVideo(') && str_contains($media, "'video_thumb_' + i"), 'The Media page must read the video in the browser.');
    });

    $test('the hero video fields offer the Media picker locked to videos', function () use ($assert, $read): void {
        $form = $read('admin/views/pages/form.php');
        // One shared renderer, used by the hero card AND by the generic block list (which is the
        // only path the homepage editor takes); neither may fall back to a bare URL input.
        $assert(substr_count($form, '$renderVideoField(') >= 2, 'Both block renderers must use the shared video field.');
        $assert(!str_contains($form, 'placeholder="https://... nebo /media/..."'), 'No video field may be a bare URL input without the Media button.');
        $assert(str_contains($read('admin/assets/admin.js'), "kind: 'video', lockKind: 'video'"), 'The Media button must open the picker on the Video tab only.');
        $assert(!str_contains($form, '<input type="url" name="block_content[]" value="<?= $h($value) ?>" placeholder="https://... nebo /media/...">'), 'A local /media/ path must not be rejected by type=url.');

        $settings = $read('admin/views/settings/index.php');
        $assert(substr_count($settings, 'data-hero-video-media') === 3, 'The three default hero video fields in Settings need a Media button each.');
        $assert(!str_contains($settings, '<input type="url" name="default_hero_video_url'), 'Settings video fields must accept a local /media/ path.');
        $assert(str_contains($read('admin/assets/admin.js'), "closest('[data-hero-video-media]')"), 'One global handler must serve every video field.');

        $layout = $read('admin/views/layouts/main.php');
        $assert(str_contains($layout, 'data-picker-tab="video"'), 'The picker needs a Video tab.');
        $js = $read('admin/assets/admin.js');
        $assert(str_contains($js, 'options.lockKind') && str_contains($js, ', kind})'), 'The picker script must send and lock the kind.');
    });

    $test('media files are served with their real type and byte ranges', function () use ($assert, $read): void {
        // iOS Safari will not play a video from a server that cannot answer a Range request, and
        // without one the whole file was sent (and the page load waited for it) on every visit.
        $index = $read('public/index.php');
        $assert(str_contains($index, "'mp4', 'm4v' => 'video/mp4'") && str_contains($index, "'webm' => 'video/webm'") && str_contains($index, "'mov' => 'video/quicktime'"), 'Video types must not fall back to octet-stream.');
        $assert(str_contains($index, "header('Accept-Ranges: bytes')"), 'The server must announce byte ranges.');
        $assert(str_contains($index, 'http_response_code(206)') && str_contains($index, "header('Content-Range: bytes '"), 'A range must be answered with 206 and Content-Range.');
        $assert(str_contains($index, 'http_response_code(416)'), 'An unsatisfiable range must be answered with 416.');
        $assert(str_contains($index, '$ifRange === $lastModifiedHttp'), 'If-Range may carry the Last-Modified date (Safari); it must still get a 206, not the whole file.');
        $assert(str_contains($index, 'header(\'Content-Length: \' . $length)'), 'The length must be sent so the browser can plan its buffering.');
        $assert(str_contains($index, 'fread($handle'), 'A large file must be streamed in chunks, not held in memory.');
    });

    $test('the plain hero video does not hold the page back or resize it late', function () use ($assert, $read): void {
        $partial = $read('app/Views/partials/hero-video.phtml');
        $assert(str_contains($partial, 'preload="metadata"') && !str_contains($partial, 'preload="auto"'), 'preload=auto fetched the whole file and delayed the load event.');

        $ratios = ['mobile' => '480 / 480', 'desktop' => '1920 / 1080'];
        $html = HeroMediaRenderer::buildHeroVideoHtml('/media/d.mp4', '/media/poster.png', true, '', '/media/m.webm', $ratios, '/media/poster_md.webp');
        $assert(str_contains($html, '<style data-hero-video-ratio>'), 'Known dimensions must reserve the video box.');
        $assert(str_contains($html, '@media (max-width:700px){video.theme-hero-video--native{aspect-ratio:480 / 480}}'), 'The phone gets its own ratio.');
        $assert(str_contains($html, '@media (min-width:1050.02px){video.theme-hero-video--native{aspect-ratio:1920 / 1080}}'), 'The desktop gets its own ratio.');
        $assert(!str_contains($html, '(min-width:700.02px) and (max-width:1050px){video'), 'A width without a video gets no ratio rule.');
        $assert(str_contains($html, 'poster="/media/poster_md.webp"') && !str_contains($html, 'poster="/media/poster.png"'), 'The plain video must use the light poster.');

        $embed = HeroMediaRenderer::buildHeroVideoHtml('https://vimeo.com/123', '/media/poster.png', true, '', '', $ratios, '/media/poster_md.webp');
        $assert(str_contains($embed, 'src="/media/poster.png"') && !str_contains($embed, 'poster_md.webp') && !str_contains($embed, 'data-hero-video-ratio'), 'An embed keeps the original poster image (it gets srcset later) and needs no ratio.');

        $hostile = HeroMediaRenderer::buildHeroVideoHtml('/media/d.mp4', '', false, '', '', ['desktop' => '1px;}</style><script>alert(1)</script>'], '');
        $assert(!str_contains($hostile, '<script>') && !str_contains($hostile, 'data-hero-video-ratio'), 'A malformed ratio must never reach the page.');
    });

    $test('library lookups map only local /media/ URLs and reject traversal', function () use ($assert, $read): void {
        $assert(HeroVideoMediaLookup::storagePath('/media/2026/10/x.mp4') === 'storage/media/2026/10/x.mp4', 'A local path maps to its storage path.');
        $assert(HeroVideoMediaLookup::storagePath('https://staging.example.cz/media/2026/10/x.mp4') === 'storage/media/2026/10/x.mp4', 'An absolute URL on a site maps by its path.');
        foreach (['', 'https://vimeo.com/1', '/media/../../.env', '/other/x.mp4', '/media/a/../b.mp4'] as $bad) {
            $assert(HeroVideoMediaLookup::storagePath($bad) === '', 'Must not map: ' . $bad);
        }
        $assert(HeroVideoMediaLookup::versionedUrl('https://vimeo.com/1') === 'https://vimeo.com/1', 'An external URL is left alone.');
        $assert(HeroVideoMediaLookup::versionedUrl('/media/does/not/exist.mp4') === '/media/does/not/exist.mp4', 'A file that is not on disk gets no version.');
        $assert(HeroVideoMediaLookup::versionedUrl('/media/x.mp4?rev=1') === '/media/x.mp4?rev=1', 'An existing query is kept.');

        // A version parameter turned a local video into a YouTube iframe ("video is not available").
        foreach (['/media/2026/10/x.mp4?v=1792607358', '/media/2026/10/x.webm?rev=1792607358', 'https://staging.example.cz/media/x.mp4?v=abcdefghij'] as $local) {
            $assert(HeroMediaRenderer::buildVideoEmbedUrl($local) === '', 'A local file must never become an embed: ' . $local);
            $html = HeroMediaRenderer::buildHeroVideoHtml('', '', true, '', $local);
            $assert(str_contains($html, '<source media="(max-width: 700px)" src="') && !str_contains($html, 'data-embed-url') && !str_contains($html, 'youtube'), 'A versioned local video must render as a plain video: ' . $local);
        }
        $assert(str_contains(HeroMediaRenderer::buildVideoEmbedUrl('https://www.youtube.com/watch?v=5ZlVVcIu10U'), 'embed/5ZlVVcIu10U'), 'A real YouTube watch link still works.');
        $assert(str_contains(HeroMediaRenderer::buildVideoEmbedUrl('https://m.youtube.com/watch?feature=share&v=5ZlVVcIu10U'), 'embed/5ZlVVcIu10U'), 'The id may follow another parameter.');
        $assert(str_contains(HeroMediaRenderer::buildVideoEmbedUrl('https://youtu.be/5ZlVVcIu10U'), 'embed/5ZlVVcIu10U'), 'A short link still works.');
        $assert(HeroMediaRenderer::buildVideoEmbedUrl('https://evil.example/watch?v=5ZlVVcIu10U') === '', 'Only YouTube hosts yield a YouTube embed.');
        $assert(str_contains($read('public/index.php'), 'HeroVideoMediaLookup::versionedUrl(') && str_contains($read('app/Services/PublishService.php'), 'HeroVideoMediaLookup::versionedUrl('), 'Live and published pages must version local video URLs.');
        $assert(str_contains($read('public/assets/frontend-runtime.js'), 'video.play && video.play()'), 'The plain hero video must be started by hand when the browser does not.');
        $assert(str_contains($read('public/index.php'), 'HeroVideoMediaLookup::ratios($siteId, $urls)') && str_contains($read('app/Services/PublishService.php'), 'HeroVideoMediaLookup::lightPoster($siteId, $posterUrl)'), 'Live and published pages must both use the lookups.');
    });
}
