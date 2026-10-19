<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templates = [
    'homepage' => (string) file_get_contents($root . '/app/Views/partials/home-contact.phtml'),
    'contact-page' => (string) file_get_contents($root . '/app/Views/archetypes/kontakt.phtml'),
];

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach ($templates as $name => $template) {
    $marker = 'data-contact-form="' . $name . '"';
    $assert(str_contains($template, $marker), $name . ' contact form marker is missing.');
    $assert(str_contains($template, 'action="/kontakt/odeslat"'), $name . ' contact form action is missing.');

    preg_match_all('/<!--[\s\S]*?-->/', $template, $comments);
    foreach ($comments[0] as $comment) {
        $assert(!str_contains($comment, $marker), $name . ' contact form is wrapped in an HTML comment.');
    }
}

echo "Contact form visibility test passed ({$assertions} assertions).\n";
