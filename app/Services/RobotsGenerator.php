<?php

namespace App\Services;

/**
 * Generuje robots.txt vcetne vyslovneho povoleni znamych AI/LLM crawleru
 * (AEO/GEO — viditelnost v ChatGPT, Claude, Perplexity, Google AI apod.).
 */
class RobotsGenerator
{
    /**
     * Skupiny AI crawleru podle poskytovatele. User-agenty odpovidaji
     * oficialne zverejnenym identifikatorum jednotlivych AI spolecnosti.
     */
    private const AI_CRAWLER_GROUPS = [
        'OpenAI (ChatGPT)' => ['GPTBot', 'ChatGPT-User', 'OAI-SearchBot'],
        'Anthropic (Claude)' => ['ClaudeBot', 'Claude-User', 'Claude-SearchBot', 'anthropic-ai'],
        'Perplexity' => ['PerplexityBot', 'Perplexity-User'],
        'Google AI (Gemini / AI Overviews)' => ['Google-Extended'],
        'Meta AI' => ['Meta-ExternalAgent', 'meta-externalfetcher'],
        'Amazon AI' => ['Amazonbot'],
        'Apple AI' => ['Applebot-Extended'],
        'Common Crawl (pouziva se k treninku vice LLM)' => ['CCBot'],
        'Cohere' => ['cohere-ai'],
    ];

    public function generate(string $domain): string
    {
        $domain = rtrim($domain, '/');

        $lines = [
            '# Standardni crawlery',
            'User-agent: *',
            'Allow: /',
            '',
            '# AI / LLM crawlery — vyslovne povoleno pro AI Overviews, ChatGPT, Claude,',
            '# Perplexity a dalsi generativni vyhledavace (AEO/GEO viditelnost).',
        ];

        foreach (self::AI_CRAWLER_GROUPS as $label => $agents) {
            $lines[] = '';
            $lines[] = '# ' . $label;
            foreach ($agents as $agent) {
                $lines[] = 'User-agent: ' . $agent;
                $lines[] = 'Allow: /';
            }
        }

        $lines[] = '';
        $lines[] = '# Strojove citelny souhrn webu pro AI agenty';
        $lines[] = '# Llms: ' . $domain . '/llms.txt';
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $domain . '/sitemap.xml';

        return implode("\n", $lines) . "\n";
    }
}
