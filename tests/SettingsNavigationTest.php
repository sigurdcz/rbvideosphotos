<?php

declare(strict_types=1);

function register_settings_navigation_tests(callable $test, callable $assert): void
{
    $viewPath = dirname(__DIR__) . '/admin/views/settings/index.php';

    $test('settings page groups the existing fields into accessible tabs', function () use ($viewPath, $assert): void {
        $source = file_get_contents($viewPath);
        $assert(is_string($source), 'Settings view could not be read.');

        foreach (['general', 'appearance', 'communication', 'measurement', 'antispam', 'dev'] as $key) {
            $assert(str_contains($source, "'" . $key . "' =>"), 'Missing settings tab definition: ' . $key);
            $assert(str_contains($source, 'data-settings-panel="' . $key . '"'), 'Missing settings panel: ' . $key);
        }

        $assert(str_contains($source, 'data-settings-tab="<?= $h($settingsTabKey) ?>"'), 'Dynamic tab key binding is missing.');
        $assert(str_contains($source, 'role="tablist"'), 'Settings navigation must expose a tablist.');
        $assert(str_contains($source, 'role="tab"'), 'Settings navigation must expose tabs.');
        $assert(str_contains($source, 'role="tabpanel"'), 'Settings navigation must expose tab panels.');
        $assert(str_contains($source, "event.key === 'ArrowRight'"), 'Tabs must support keyboard navigation.');
        $assert(str_contains($source, "form.addEventListener('invalid'"), 'Invalid fields must reveal their hidden tab.');
        $assert(substr_count($source, 'data-settings-collapsible') >= 4, 'Long advanced sections must be collapsible.');
    });

    $test('settings refactor preserves critical form contracts and tools', function () use ($viewPath, $assert): void {
        $source = file_get_contents($viewPath);
        $assert(is_string($source), 'Settings view could not be read.');

        foreach (['name="name"', 'name="domain"', 'name="form_email"', 'name="default_hero_video_url"', 'name="contact_spam_threshold"'] as $field) {
            $assert(str_contains($source, $field), 'Critical settings field disappeared: ' . $field);
        }

        $assert(str_contains($source, 'action="/admin/settings/update"'), 'Main settings submit endpoint changed.');
        $assert(str_contains($source, 'action="/admin/settings/spam-test"'), 'Spam test endpoint changed.');
        $assert(str_contains($source, 'data-spam-test-modal'), 'Spam test must remain available in a modal.');
        $assert(str_contains($source, 'settings-tool-card'), 'REST API help must remain available as a compact tool.');
        $assert(str_contains($source, 'id="settings-panel-dev"'), 'REST API help must live inside the Dev tab.');
    });
}
