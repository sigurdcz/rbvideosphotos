<?php
/** Tiny inline icon set for the mailbox UI (stroke icons, inherit currentColor). */
if (!function_exists('mailIcon')) {
    function mailIcon(string $name, int $size = 16): string
    {
        $paths = [
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
            'clip' => '<path d="M21 12.5l-8.5 8.5a5.5 5.5 0 0 1-7.8-7.8l9-9a3.7 3.7 0 0 1 5.2 5.2l-9 9a1.8 1.8 0 0 1-2.6-2.6l8.3-8.3"/>',
            'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>',
            'back' => '<path d="M15 6l-6 6 6 6"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
            'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
            'alert' => '<path d="M12 3l10 18H2L12 3zM12 10v5M12 18h.01"/>',
            'draft' => '<path d="M4 20h4L19 9l-4-4L4 16v4zM13 7l4 4"/>',
        ];
        return '<svg class="mail-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }
}
