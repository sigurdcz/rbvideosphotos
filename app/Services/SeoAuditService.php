<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class SeoAuditService
{
    public function audit(array $page, ?array $seo, string $publishedHtml = ''): array
    {
        $results = [];

        $this->check($results, 'Meta title',        !empty($seo['meta_title']));
        $this->check($results, 'Meta description',  !empty($seo['meta_description']));
        $this->check($results, 'Canonical URL',     !empty($seo['canonical_url']));
        $this->check($results, 'OG tagy',           !empty($seo['og_title']) && !empty($seo['og_description']));
        $this->check($results, 'Twitter Card tagy', !empty($seo['twitter_title']));
        $this->check($results, 'Schema.org JSON-LD', trim((string)($seo['schema_type'] ?? '')) !== '' && trim((string)($seo['schema_name'] ?? '')) !== '');
        $this->check($results, 'Stránka indexovatelná', !empty($seo['robots_index']));
        $this->check($results, 'Slug bez diakritiky', $this->slugOk($page['slug'] ?? ''));

        if ($publishedHtml !== '') {
            $this->auditHtml($results, $publishedHtml);
        }

        return $results;
    }

    private function auditHtml(array &$results, string $html): void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Exactly one H1
        $h1Count = $xpath->query('//h1')->length;
        $this->check($results, 'Právě jeden H1', $h1Count === 1);

        // Images with ALT
        $imgs    = $xpath->query('//img');
        $noAlt   = 0;
        foreach ($imgs as $img) {
            if (trim($img->getAttribute('alt')) === '') $noAlt++;
        }
        $this->checkWarn($results, 'Obrázky mají ALT', $noAlt === 0, $noAlt > 0 ? "{$noAlt} obrázek bez ALT" : '');

        // Internal links not empty
        $links   = $xpath->query('//a[@href]');
        $empty   = 0;
        foreach ($links as $link) {
            if (trim($link->getAttribute('href')) === '' || $link->getAttribute('href') === '#') $empty++;
        }
        $this->checkWarn($results, 'Interní odkazy nejsou prázdné', $empty === 0, $empty > 0 ? "{$empty} prázdný odkaz" : '');
    }

    private function slugOk(string $slug): bool
    {
        // No diacritics, only lowercase letters, digits, hyphens
        return (bool)preg_match('/^[a-z0-9\-]*$/', $slug);
    }

    private function check(array &$results, string $label, bool $ok): void
    {
        $results[] = ['label' => $label, 'status' => $ok ? 'ok' : 'error', 'note' => ''];
    }

    private function checkWarn(array &$results, string $label, bool $ok, string $note = ''): void
    {
        $status    = $ok ? 'ok' : 'warning';
        $results[] = ['label' => $label, 'status' => $status, 'note' => $note];
    }
}
