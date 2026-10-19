<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\EmailRecipientList;

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$check(EmailRecipientList::parse('') === [], 'Empty input must produce no recipients.');
$check(
    EmailRecipientList::parse("one@example.test, two@example.test\nthree@example.test\tfour@example.test")
        === ['one@example.test', 'two@example.test', 'three@example.test', 'four@example.test'],
    'Comma, spaces, newlines and tabs must separate recipients.'
);
$check(
    EmailRecipientList::normalize('ONE@example.test one@example.test, two@example.test', 'two@example.test') === 'ONE@example.test',
    'Duplicates and the primary recipient must be removed case-insensitively.'
);

foreach (['valid@example.test, invalid', "valid@example.test\r\nBcc: injected@example.test"] as $invalid) {
    try {
        EmailRecipientList::parse($invalid);
        throw new LogicException('Expected invalid list rejection.');
    } catch (InvalidArgumentException $e) {
        $check(true, 'Invalid list rejected.');
    }
}

echo "PASS: {$checks} email recipient list assertions.\n";
