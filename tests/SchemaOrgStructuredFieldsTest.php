<?php

declare(strict_types=1);

use App\Services\SeoRenderer;

function register_schema_org_structured_fields_tests(callable $test, callable $assert): void
{
    $test('empty schema_type renders no page-level JSON-LD script', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => '',
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            null,
            null
        );

        $assert(!str_contains($html, '<script type="application/ld+json">'), 'Empty schema_type must not emit a page-level JSON-LD block.');
    });

    $test('WebPage with contact_auto=1 never emits contact fields', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => 'WebPage',
                'schema_name' => 'Test WebPage',
                'schema_contact_auto' => 1,
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            null,
            null
        );

        $assert(!str_contains($html, '"telephone"'), 'WebPage must never emit telephone.');
        $assert(!str_contains($html, '"email"'), 'WebPage must never emit email.');
        $assert(!str_contains($html, '"address"'), 'WebPage must never emit address.');
    });

    $test('Person with manual contact fields uses the split postal code / city', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => 'Person',
                'schema_name' => 'Test Person',
                'schema_contact_auto' => 0,
                'schema_telephone' => '+420111222333',
                'schema_address_street' => 'Mozartova 1113/65',
                'schema_address_locality' => '779 00 Olomouc',
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            null,
            null
        );

        $openTag = '<script type="application/ld+json">';
        $start = strpos($html, $openTag);
        $assert($start !== false, 'Missing page-level JSON-LD block.');
        $offset = $start + strlen($openTag);
        $end = strpos($html, '</script>', $offset);
        $jsonBlock = trim(substr($html, $offset, $end - $offset));
        $payload = json_decode($jsonBlock, true);
        $assert(is_array($payload), 'Page-level JSON-LD payload did not decode.');
        $assert(($payload['address']['postalCode'] ?? null) === '77900', 'Postal code was not parsed as 77900.');
        $assert(($payload['address']['addressLocality'] ?? null) === 'Olomouc', 'City was not parsed as Olomouc.');
        $assert(($payload['telephone'] ?? null) === '+420111222333', 'Manual telephone was not used.');
    });

    $test('LocalBusiness always carries @id ending in /#organization and areaServed', function () use ($assert): void {
        $html = (new SeoRenderer())->renderHead(
            [
                'meta_title' => 'Test page',
                'robots_index' => 1,
                'robots_follow' => 1,
                'schema_type' => 'LocalBusiness',
                'schema_name' => 'Test business',
                'schema_contact_auto' => 0,
            ],
            ['name' => 'Test site', 'domain' => 'https://example.test'],
            'https://example.test/test',
            null,
            null
        );

        $openTag = '<script type="application/ld+json">';
        $start = strpos($html, $openTag);
        $assert($start !== false, 'Missing page-level JSON-LD block.');
        $offset = $start + strlen($openTag);
        $end = strpos($html, '</script>', $offset);
        $jsonBlock = trim(substr($html, $offset, $end - $offset));
        $payload = json_decode($jsonBlock, true);
        $assert(is_array($payload), 'Page-level JSON-LD payload did not decode.');
        $assert(isset($payload['@id']) && str_ends_with((string)$payload['@id'], '/#organization'), '@id must end with /#organization.');
        $assert(isset($payload['areaServed']), 'areaServed must always be present for LocalBusiness.');
    });
}
