<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\ContactSubmissionStatus;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach (['1', 'saved', 'error', 'invalid', 'invalid-package', 'csrf', 'rate-limit'] as $allowed) {
    $assert(ContactSubmissionStatus::normalize($allowed) === $allowed, "Allowed state {$allowed} must survive normalization.");
}

foreach ([null, '', 'SUCCESS', '<script>alert(1)</script>', '1\" onmouseover=\"alert(1)'] as $rejected) {
    $assert(ContactSubmissionStatus::normalize($rejected) === null, 'Unknown or hostile state must be discarded.');
}

$renderer = new class {
    public function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(?string $state): string
    {
        $form = ['sentState' => ContactSubmissionStatus::normalize($state)];
        ob_start();
        include dirname(__DIR__) . '/app/Views/partials/contact-status.phtml';
        return (string) ob_get_clean();
    }
};

$success = $renderer->render('1');
$assert(str_contains($success, 'kt-success--success'), 'Sent state must render the strong success variant.');
$assert(str_contains($success, 'check_circle'), 'Sent state must render the success icon.');

$warning = $renderer->render('saved');
$assert(str_contains($warning, 'kt-success--warning'), 'Saved-only state must render the warning variant.');

$error = $renderer->render('error');
$assert(str_contains($error, 'kt-success--error'), 'Storage failure must render the error variant.');

$hostile = '<img src=x onerror=alert(1)>';
$hostileOutput = $renderer->render($hostile);
$assert($hostileOutput === '', 'A hostile URL state must render no notice.');
$assert(!str_contains($hostileOutput, $hostile) && !str_contains($hostileOutput, 'onerror'), 'Hostile URL content must never reach HTML.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Contact submission status tests passed (18 checks).\n";
