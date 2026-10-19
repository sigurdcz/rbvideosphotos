<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

foreach (['', 'copy@example.test, second@example.test', '"><script>alert(1)</script>'] as $copy) {
    $site = ['id' => 1, 'name' => 'Fixture'];
    $contact = ['form_email' => 'primary@example.test', 'form_email_cc' => $copy];
    $media = []; $apiKey = null; $_csrfToken = 'fixture'; $_user = ['role' => 'admin'];
    ob_start();
    require dirname(__DIR__) . '/admin/views/settings/index.php';
    $html = ob_get_clean();
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $input = $xpath->query('//textarea[@name="form_email_cc"]')->item(0);
    if (!$input || $input->textContent !== $copy || $input->hasAttribute('required')
        || $input->getAttribute('rows') !== '4' || $input->getAttribute('maxlength') !== '60000'
        || str_contains($html, '<script>alert(1)</script>')) {
        throw new RuntimeException('Optional copy textarea must preserve values, escape content and allow empty submission');
    }
    if ($xpath->query('//input[@name="form_email" and @value="primary@example.test"]')->length !== 1) {
        throw new RuntimeException('Primary recipient must remain separate');
    }
}
echo "PASS contact copy settings view: multiline textarea, empty, configured, escaped, optional and separate primary recipient\n";
