<?php

declare(strict_types=1);

$test('inline editing toggle keeps admin chrome while disabling editor behavior', function () use ($assert): void {
    $root = dirname(__DIR__);
    $auth = (string)file_get_contents($root . '/app/Core/Auth.php');
    $controller = (string)file_get_contents($root . '/app/Controllers/InlineController.php');
    $routes = (string)file_get_contents($root . '/admin/index.php');
    $front = (string)file_get_contents($root . '/public/index.php');
    $composer = (string)file_get_contents($root . '/app/Services/Render/PageComposer.php');

    $assert(str_contains($auth, "array_key_exists('inline_editing_enabled', \$_SESSION)"), 'Session flag must default explicitly when absent.');
    $assert(str_contains($auth, 'setInlineEditingEnabled(bool $enabled)'), 'Auth must expose a typed session setter.');
    $assert(str_contains($routes, "'/admin/inline/toggle-mode'"), 'The CSRF-protected toggle route is missing.');
    $assert(str_contains($controller, "Inline editace je vypnuta."), 'Inline write endpoints must reject requests while disabled.');
    $assert(str_contains($front, 'Auth::inlineEditingEnabled() && !$previewMode'), 'Generic page edit mode must honor the session toggle.');
    $assert(str_contains($front, 'Inline editace: zapnuta') && str_contains($front, 'Inline editace: vypnuta'), 'Admin bar must expose both toggle states.');
    $assert(str_contains($front, 'if ($inlineEditing && $pageId !== null)'), 'Editor metadata and script must only load while enabled.');
    $assert(!str_contains($composer, 'if ($editMode) {' . "\n" . '            $html = \\injectCmsEditorChrome'), 'Admin chrome must not be conditional on edit mode.');
});
