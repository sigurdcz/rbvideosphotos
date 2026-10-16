<?php

declare(strict_types=1);

function register_cookie_consent_presentation_tests(callable $test, callable $assert): void
{
    $root = dirname(__DIR__);

    $test('unset cookie consent uses a non-blocking summary bar', function () use ($assert, $root): void {
        $partial = (string)file_get_contents($root . '/app/Views/partials/cookie-consent.phtml');
        $css = (string)file_get_contents($root . '/public/assets/cookie-consent.css');
        $script = (string)file_get_contents($root . '/public/assets/cookie-consent.js');

        $assert(str_contains($css, '.cookie-consent-host{position:fixed;inset:0;z-index:99998;pointer-events:none}'), 'Consent host must not intercept the usable page.');
        $assert(str_contains($css, 'right:20px;bottom:20px;left:auto;top:auto'), 'Desktop summary must be a bottom-right bar.');
        $assert(str_contains($css, 'backdrop-filter:none') && !str_contains($css, 'blur('), 'Consent presentation must not blur the page.');
        $assert(str_contains($script, "backdrop.hidden=selectedMode!=='settings'")
            && str_contains($script, "backdrop.style.display=selectedMode==='settings'?'block':'none'"), 'Backdrop must exist only in explicit settings mode.');
        $assert(str_contains($script, "if(!pref()){\n      show('summary');"), 'Unset consent must open only the summary bar.');
        $assert(str_contains($partial, 'role="region" aria-live="polite"') && !str_contains($partial, 'aria-modal'), 'Initial summary must be a region and must not carry aria-modal, which is only valid on a dialog.');
    });

    $test('cookie settings is explicit, accessible and retry-safe', function () use ($assert, $root): void {
        $script = (string)file_get_contents($root . '/public/assets/cookie-consent.js');
        $css = (string)file_get_contents($root . '/public/assets/cookie-consent.css');
        $assert(str_contains($script, "function openSettings(){ show('settings'); }")
            && str_contains($script, "box.setAttribute('role',selectedMode==='settings'?'dialog':'region')")
            && str_contains($script, "if(selectedMode==='settings'){box.setAttribute('aria-modal','true');}")
            && str_contains($script, "else{box.removeAttribute('aria-modal');}"), 'Settings must switch to modal semantics, and the region banner must not carry aria-modal at all.');
        $assert(str_contains($script, "if(pref()) hide();\n      else show('summary');"), 'Closing settings before a decision must restore the summary bar.');
        $assert(str_contains($css, '@media(max-width:719px)')
            && str_contains($css, 'width:calc(100vw - 24px)')
            && str_contains($css, 'max-height:calc(100vh - 24px)'), 'Mobile summary and settings must stay inside the viewport.');
    });

    $test('cookie consent summary is enabled for existing and newly seeded sites', function () use ($assert, $root): void {
        $migration = (string)file_get_contents($root . '/database/migrations/064_enable_cookie_consent_summary.sql');
        $seed = (string)file_get_contents($root . '/database/seeds/seed.php');
        $assert(str_contains($migration, 'SET cookie_consent_enabled = 1') && str_contains($migration, 'WHERE cookie_consent_enabled = 0'));
        $assert(str_contains($seed, 'cookie_consent_enabled=1'));
        $assert(str_contains($seed, 'ThemeTemplateRegistry::DEFAULT_THEME_KEY, 1]'));
    });
}
