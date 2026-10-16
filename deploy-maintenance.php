<?php

declare(strict_types=1);

// Safe repository placeholder. The production workflow replaces this file in
// the deploy artifact with a tokenized one-time implementation.
http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
echo 'Not found.';
