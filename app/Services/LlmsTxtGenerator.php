<?php

namespace App\Services;

/**
 * Generates /llms.txt as a compact machine-readable map of the public site.
 */
class LlmsTxtGenerator
{
    public function generate(array $site, array $pages): string
    {
        $domain = rtrim((string)($site['domain'] ?? ''), '/');
        $siteName = (string)($site['name'] ?? 'Web');
        $description = trim((string)($site['meta_description'] ?? $site['description'] ?? ''));
        $pageMap = $this->indexPagesByPath($pages);

        $lines = [];
        $lines[] = '# ' . $siteName;
        $lines[] = '';
        $lines[] = $description !== '' ? $description : ('Web prezentace ' . $siteName . '.');
        $lines[] = '';
        $lines[] = '## Hlavni ucel webu';
        $lines[] = '';
        $lines[] = 'Web slouzi k predstaveni nabidky, ukazek prace, cenovych informaci a kontaktniho formulare.';
        $lines[] = '';
        $lines[] = '## Dulezite stranky';
        $lines[] = '';
        foreach ($this->importantPageLines($domain, $pageMap) as $entry) {
            $lines[] = $entry;
        }
        $lines[] = '';
        $lines[] = '## Nabizene sluzby';
        $lines[] = '';
        $lines[] = '- Viz hlavni stranku a cenik pro aktualni prehled sluzeb a balicku.';
        $lines[] = '';
        $lines[] = '## Lokality';
        $lines[] = '';
        $lines[] = '- Viz kontaktni stranku a obsah jednotlivych stranek; generator nedoplni lokality, ktere nejsou explicitne uvedene v obsahu.';
        $lines[] = '';
        $lines[] = '## Ceny';
        $lines[] = '';
        $lines[] = isset($pageMap['cenik'])
            ? '- Ceny a balicky jsou na strance Cenik: ' . $this->buildUrl($domain, 'cenik')
            : '- TODO: doplnte samostatnou stranku s cenikem nebo jasny cenovy blok na webu.';
        $lines[] = '';
        $lines[] = '## Kontakt';
        $lines[] = '';
        $lines[] = $this->isPublicRouteAvailable($pageMap, 'kontakt')
            ? '- Kontaktní formulář a kontaktni informace: ' . $this->buildUrl($domain, 'kontakt')
            : '- TODO: doplnte samostatnou kontaktni stranku s formularem.';
        $lines[] = '';
        $lines[] = '## Pro AI agenty';
        $lines[] = '';
        $lines[] = '- Hlavni nabidka a vysvetleni sluzeb jsou na homepage a na obsahovych podstrankach.';
        $lines[] = isset($pageMap['cenik'])
            ? '- Ceny a balicky hledejte na strance Cenik.'
            : '- Ceny nejsou v samostatnem ceniku; overte homepage a obsahove stranky.';
        $lines[] = $this->isPublicRouteAvailable($pageMap, 'kontakt')
            ? '- Poptavka se odesila pres formular na strance Kontakt.'
            : '- Kontaktní formulář neni dostupny na samostatne strance.';
        $lines[] = isset($pageMap['blog'])
            ? '- Blog obsahuje poradenske a informacni clanky: ' . $this->buildUrl($domain, 'blog')
            : '- Blogova sekce neni dostupna jako samostatna stranka.';
        $lines[] = $this->isPublicRouteAvailable($pageMap, 'nase-prace')
            ? '- Portfolio a ukazky prace jsou na strance Portfolio: ' . $this->buildUrl($domain, 'nase-prace')
            : '- Portfolio neni dostupne jako samostatna stranka.';
        $lines[] = isset($pageMap['faq'])
            ? '- Casto kladene dotazy jsou na strance FAQ: ' . $this->buildUrl($domain, 'faq')
            : '- FAQ sekce neni dostupna jako samostatna stranka.';
        $lines[] = '';
        $lines[] = '## Dalsi indexovatelne stranky';
        $lines[] = '';

        usort($pages, static function (array $a, array $b): int {
            $ap = trim((string)($a['url_path'] ?? ''), '/');
            $bp = trim((string)($b['url_path'] ?? ''), '/');
            if ($ap === '' && $bp !== '') {
                return -1;
            }
            if ($ap !== '' && $bp === '') {
                return 1;
            }
            return strcmp($ap, $bp);
        });

        foreach ($pages as $page) {
            $path = trim((string)($page['url_path'] ?? ''), '/');
            $url = $this->buildUrl($domain, $path);
            $title = (string)($page['title'] ?? ($path !== '' ? $path : $url));
            $meta = trim((string)($page['meta_description'] ?? $page['description'] ?? ''));
            $entry = '- [' . $title . '](' . $url . ')';
            if ($meta !== '') {
                $entry .= ': ' . $meta;
            }
            $lines[] = $entry;
        }

        $lines[] = '';
        $lines[] = '## Posledni aktualizace';
        $lines[] = '';
        $lines[] = '- ' . date('Y-m-d');
        $lines[] = '';
        $lines[] = '## Technicke discovery soubory';
        $lines[] = '';
        $lines[] = '- [Sitemap](' . $domain . '/sitemap.xml): XML sitemap vsech indexovatelnych URL.';
        $lines[] = '- [Robots](' . $domain . '/robots.txt): pravidla pro crawlery.';

        return implode("\n", $lines) . "\n";
    }

    private function indexPagesByPath(array $pages): array
    {
        $map = [];
        foreach ($pages as $page) {
            $map[trim((string)($page['url_path'] ?? ''), '/')] = $page;
        }

        return $map;
    }

    private function importantPageLines(string $domain, array $pageMap): array
    {
        $labels = [
            '' => 'Hlavni stranka',
            'cenik' => 'Cenik',
            'nase-prace' => 'Portfolio',
            'blog' => 'Blog',
            'faq' => 'FAQ',
            'kontakt' => 'Kontakt',
        ];

        $lines = [];
        foreach ($labels as $path => $label) {
            if ($this->isPublicRouteAvailable($pageMap, $path)) {
                $lines[] = '- ' . $label . ': ' . $this->buildUrl($domain, $path);
                continue;
            }

            $lines[] = '- ' . $label . ': TODO doplnte nebo publikujte tuto stranku, pokud ma byt soucasti webu.';
        }

        return $lines;
    }

    private function buildUrl(string $domain, string $path): string
    {
        if ($path === '') {
            return $domain . '/';
        }

        return $domain . '/' . ltrim($path, '/');
    }

    private function isPublicRouteAvailable(array $pageMap, string $path): bool
    {
        if ($path === '' || isset($pageMap[$path])) {
            return true;
        }

        return in_array($path, ['blog', 'faq', 'kontakt', 'nase-prace'], true);
    }
}
