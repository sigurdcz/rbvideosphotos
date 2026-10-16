<?php

declare(strict_types=1);

function register_about_page_seed_persistence_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('base seed recreates the About page after Armagedon', function () use ($assert, $root): void {
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');

        $assert(str_contains($seed, "seedUpsertTemplate(\$pdo, \$siteId, 'O nás'"));
        $assert(str_contains($seed, "seedUpsertPage(\$pdo, \$siteId, \$tplAbout"));
        $assert(str_contains($seed, "'O nás',             'o-nas',          'o-nas'"));
        $assert(str_contains($seed, "seedBlock(\$pdo, \$pidAbout, 'hero_h1'"));
        $assert(str_contains($seed, "'Fotograf a kameraman z Olomouce.'"));
        $assert(str_contains($seed, "'AboutPage'"));
        $assert(str_contains($seed, "'/o-nas'"));
        $assert(str_contains($seed, "\$seedMedia['weare2.png']['id']"));
        $assert(str_contains($seed, "seedBlock(\$pdo, \$pidAbout, 'hero_bg'"));
        $assert(str_contains($seed, "seedBlock(\$pdo, \$pidAbout, 'about_photographer_image'"));
        $assert(str_contains($seed, "seedBlock(\$pdo, \$pidAbout, 'about_cameraman_image'"));
        $assert(str_contains($seed, "'aboutuszdenek.jpg'") && str_contains($seed, "'https://vimeo.com/1228181473'"));
        $assert(str_contains($seed, "\$seedMedia['fallback tomáš.png']['url']"), 'Tomas video fallback must use the supplied Media Library asset.');
        $assert(!str_contains($seed, "'about_cameraman_image', 'image', 'Tomáš Šincl - kameraman', (string)(\$seedMedia['black.png']"), 'Tomas video fallback must not use black.png.');
        $assert(str_contains($seed, 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat.'));
        $assert(str_contains($seed, "\$seedOwnOutputBuffer = php_sapi_name() === 'cli' && ob_get_level() === 0;"));
        $assert(str_contains($seed, 'ob_end_flush();'));

        $demoSeeder = (string)file_get_contents($root . '/app/Services/DemoContentSeeder.php');
        $migration = (string)file_get_contents($root . '/database/migrations/062_shorten_about_hero_heading.sql');
        $videoMigration = (string)file_get_contents($root . '/database/migrations/088_update_tomas_sincl_about_video.sql');
        $fallbackMigration = (string)file_get_contents($root . '/database/migrations/101_replace_about_cameraman_video_fallback.sql');
        $quoteMigration = (string)file_get_contents($root . '/database/migrations/090_refine_about_photographer_quote.sql');
        $assert(str_contains($demoSeeder, "'o-nas' => ['Fotograf a kameraman z Olomouce.'"));
        $assert(str_contains($migration, "b.block_key IN ('hero_h1', 'hero_heading')"));
        $assert(str_contains($migration, 'Fotograf a kameraman z Olomouce.'));
        $assert(str_contains($videoMigration, "cb.block_key = 'about_cameraman_video'") && str_contains($videoMigration, 'https://vimeo.com/1228181473'));
        $assert(str_contains($fallbackMigration, 'fallback tomáš.png') && str_contains($fallbackMigration, 'WHERE 1 = 0'));
        $assert(str_contains($demoSeeder, "ABOUT_CAMERAMAN_FALLBACK_ASSET = 'fallback tomáš.png'"));
        $assert(str_contains($demoSeeder, '$this->media->ingestUploadedFile('), 'Demo assets must use the normal Media Library ingestion service.');
        $assert(str_contains($quoteMigration, "cb.block_key = 'about_photographer_quote'") && str_contains($quoteMigration, 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat.'));
    });

    $test('About photographer card copy is identical in seed, controller, templates and migration 096', function () use ($assert, $root): void {
        $quote = 'Zachycuji emoce a přirozenost tak, abyste se k nim mohli vracet i po letech a znovu je prožívat. Věnuji se focení od roku 2015.';
        $files = [
            'database/seeds/seed.php',
            'app/Controllers/PageController.php',
            'Templates/golden-bash/views/pages/o-nas.phtml',
            'Templates/golden-bash-diagonal/views/pages/o-nas.phtml',
        ];
        foreach ($files as $file) {
            $src = (string)file_get_contents($root . '/' . $file);
            $assert(str_contains($src, $quote), $file . ' must carry the new photographer description.');
            $assert(str_contains($src, 'Stovky spokojených klientů'), $file . ' must carry the new first bullet.');
            $assert(!str_contains($src, 'Umělecké a přirozené fotografie'), $file . ' must not keep the old first bullet.');
        }
        $migration = (string)file_get_contents($root . '/database/migrations/096_update_about_photographer_card_copy.sql');
        $assert(str_contains($migration, $quote) && str_contains($migration, "cb.block_key = 'about_photographer_point_1'") && str_contains($migration, 'Stovky spokojených klientů'));
        $assert(substr_count($migration, 'AND cb.content = ') === 2, 'Migration 096 must not overwrite admin edits.');
    });

    $test('About "Ukázky práce" buttons link to the matching gallery section (photographer -> photos, cameraman -> videos)', function () use ($assert, $root): void {
        foreach (['golden-bash', 'golden-bash-diagonal'] as $theme) {
            $src = (string)file_get_contents($root . '/Templates/' . $theme . '/views/pages/o-nas.phtml');
            foreach (['about_photographer_cta_primary' => 'fotogalerie', 'about_cameraman_cta_primary' => 'videogalerie'] as $key => $hash) {
                $assert(preg_match("/inlineAttr\('" . $key . "'\) \?> href=\"\/nase-prace#" . $hash . "\"/", $src) === 1, $theme . ': ' . $key . ' must link to /nase-prace#' . $hash . '.');
            }
            $assert(str_contains($src, "inlineAttr('about_hero_cta_secondary') ?> href=\"/nase-prace\""), $theme . ': hero secondary button stays on /nase-prace.');
        }
        $listing = (string)file_get_contents($root . '/app/Views/archetypes/portfolio-listing.phtml');
        $assert(str_contains($listing, "'id' => 'videogalerie'"), 'Portfolio listing must expose the #videogalerie anchor.');
        $assert(str_contains($listing, "'id' => 'fotogalerie'"), 'Portfolio listing must expose the #fotogalerie anchor.');
    });

    $test('About team cards: photographer has the same photo + video media contract as the cameraman', function () use ($assert, $root): void {
        $people = [
            'photographer' => ['image' => 'photographerImage', 'embed' => 'photographerVideoEmbed'],
            'cameraman' => ['image' => 'cameramanImage', 'embed' => 'cameramanVideoEmbed'],
        ];
        $sources = [];
        foreach (['golden-bash', 'golden-bash-diagonal'] as $theme) {
            $src = (string)file_get_contents($root . '/Templates/' . $theme . '/views/pages/o-nas.phtml');
            $sources[] = $src;
            foreach ($people as $person => $vars) {
                $image = '$' . $vars['image'];
                $embed = '$' . $vars['embed'];
                $assert(str_contains($src, $image . " = trim(\$blockMap['about_" . $person . "_image'] ?? '');"), $theme . ': ' . $person . ' photo must come from about_' . $person . '_image.');
                $assert(str_contains($src, "\$blockMap['about_" . $person . "_video']"), $theme . ': ' . $person . ' video must come from about_' . $person . '_video.');
                // Empty video block = plain portrait, exactly as before; filled = facade with poster.
                $assert(str_contains($src, '<article class="gb-about-person<?= ' . $embed . " !== '' ? ' gb-about-person--video' : '' ?>\">"), $theme . ': ' . $person . ' card must only switch to the video variant when a video is set.');
                $assert(str_contains($src, "data-hero-video-facade<?= !empty(\$videoAutoplay) ? ' data-hero-video-autoplay' : '' ?> data-embed-url=\"<?= \$this->e(" . $embed . ') ?>"'), $theme . ': ' . $person . ' video facade must honour the autoplay setting like the other card.');
                $assert(str_contains($src, '<img class="gb-about-person__photo-poster" src="<?= $this->e(' . $image . ") ?>\" alt=\"<?= \$this->e(\$t('about_" . $person . "_photo_alt'"), $theme . ': ' . $person . ' video must use the portrait as poster with the editable alt.');
                $assert(str_contains($src, '<img class="gb-about-person__photo" src="<?= $this->e(' . $image . ") ?>\" alt=\"<?= \$this->e(\$t('about_" . $person . "_photo_alt'"), $theme . ': ' . $person . ' card without video must keep the plain portrait.');
                $assert(str_contains($src, "\$t('about_" . $person . "_video_label'"), $theme . ': ' . $person . ' play button must use the editable label.');
            }
        }
        $assert($sources[0] === $sources[1], 'Both Golden Bash o-nas templates must stay identical.');

        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $controller = (string)file_get_contents($root . '/app/Controllers/PageController.php');
        $mediaMigration = (string)file_get_contents($root . '/database/migrations/037_about_team_media_blocks.sql');
        $videoMigration = (string)file_get_contents($root . '/database/migrations/068_add_about_team_video_blocks.sql');
        foreach (array_keys($people) as $person) {
            $assert(preg_match("/seedBlock\(\\\$pdo, \\\$pidAbout, 'about_" . $person . "_image', 'image'/", $seed) === 1, 'seed.php must create about_' . $person . '_image as an image block.');
            $assert(preg_match("/seedBlock\(\\\$pdo, \\\$pidAbout, 'about_" . $person . "_video', 'video'/", $seed) === 1, 'seed.php must create about_' . $person . '_video as a video block.');
            $assert(str_contains($mediaMigration, "about_" . $person . "_image"), 'Migration 037 binds about_' . $person . '_image.');
            $assert(str_contains($videoMigration, "about_" . $person . "_video"), 'Migration 068 creates about_' . $person . '_video.');
            $assert(preg_match("/\['about_" . $person . "_video', 'video', '[^']+', '', \d+\]/", $controller) === 1, 'PageController must materialise a missing about_' . $person . '_video row empty (never with media).');        }
        $assert(str_contains($controller, 'array_merge(self::ABOUT_COPY_BLOCKS, self::ABOUT_TEAM_VIDEO_BLOCKS)'), 'Opening the o-nas page in admin must also ensure the team video rows.');
        // Migration 068 must not overwrite an admin-set video on re-run.
        $assert(!preg_match('/ON DUPLICATE KEY UPDATE[\s\S]*`content`/', $videoMigration), 'Migration 068 must not overwrite video content on duplicate.');
    });

}
