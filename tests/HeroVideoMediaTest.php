<?php

declare(strict_types=1);

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
        $assert(substr_count($form, 'data-hero-video-media') >= 2, 'Each hero video field needs a Media button.');
        $assert(str_contains($form, "kind: 'video', lockKind: 'video'"), 'The Media button must open the picker on the Video tab only.');
        $assert(!str_contains($form, '<input type="url" name="block_content[]" value="<?= $h($value) ?>" placeholder="https://... nebo /media/...">'), 'A local /media/ path must not be rejected by type=url.');

        $layout = $read('admin/views/layouts/main.php');
        $assert(str_contains($layout, 'data-picker-tab="video"'), 'The picker needs a Video tab.');
        $js = $read('admin/assets/admin.js');
        $assert(str_contains($js, 'options.lockKind') && str_contains($js, ', kind})'), 'The picker script must send and lock the kind.');
    });
}
