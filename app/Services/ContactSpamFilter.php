<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Conservative, deterministic contact-form spam scoring.
 *
 * A URL on its own is never enough to reject a message. Blocking requires a
 * strong bot phrase, an impossible name, or a combination of promotional
 * language and a link/call to action.
 */
final class ContactSpamFilter
{
    /** @param array<string,mixed>|null $config */
    public function __construct(private ?array $config = null)
    {
        $this->config = ContactSpamConfig::fromStored(
            $config === null ? null : ContactSpamConfig::encode($config)
        );
    }

    /** @return array{isSpam: bool, score: int, reasons: list<string>} */
    public function classify(string $name, string $contact, string $message): array
    {
        if (!$this->config['enabled']) {
            return ['isSpam' => false, 'score' => 0, 'reasons' => []];
        }
        $nameText = $this->normalize($name);
        $contactText = $this->normalize($contact);
        $messageText = $this->normalize($message);
        $all = trim($nameText . ' ' . $messageText);
        $score = 0;
        $reasons = [];

        foreach ($this->config['known_phrases'] as $phrase) {
            if (str_contains($all, $phrase)) {
                $this->add($score, $reasons, $this->weight('known_phrase'), 'known-bot-boilerplate');
                break;
            }
        }

        if ($this->containsUrl($nameText)) {
            $this->add($score, $reasons, $this->weight('url_in_name'), 'url-in-name');
        }

        $promotionTerms = $this->countTerms($all, $this->config['promotion_terms']);
        if (is_int($promotionTerms) && $promotionTerms >= 2) {
            $this->add($score, $reasons, $this->weight('promotion_terms'), 'gambling-promotion');
        }

        if ($this->containsPhrase($all, $this->config['calls_to_action'])) {
            $this->add($score, $reasons, $this->weight('call_to_action'), 'promotional-call-to-action');
        }

        if ($this->containsUntrustedUrl($messageText)) {
            $this->add($score, $reasons, $this->weight('link_in_message'), 'link-in-message');
        }

        // Bot payloads often put an email first and append random tokens to the
        // contact field. This is only a weak signal and cannot block by itself.
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+\s+\S+/u', $contactText) === 1) {
            $this->add($score, $reasons, $this->weight('contact_junk'), 'contact-field-junk');
        }

        return ['isSpam' => $score >= $this->config['threshold'], 'score' => $score, 'reasons' => $reasons];
    }

    private function normalize(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lower = function_exists('mb_strtolower') ? mb_strtolower($decoded, 'UTF-8') : strtolower($decoded);
        return trim((string) preg_replace('/\s+/u', ' ', $lower));
    }

    private function containsUrl(string $value): bool
    {
        return preg_match(
            '~(?:https?://|www\.|\b[a-z0-9][a-z0-9.-]+\.(?:com|net|org|io|ru|xyz|top|click|site|online)(?:/|\b))~iu',
            $value
        ) === 1;
    }

    private function containsUntrustedUrl(string $value): bool
    {
        if (!$this->containsUrl($value)) return false;
        preg_match_all('~(?:https?://|www\.)?([a-z0-9][a-z0-9.-]+\.[a-z]{2,63})~iu', $value, $matches);
        foreach (($matches[1] ?? []) as $domain) {
            $domain = strtolower(rtrim((string)$domain, '.'));
            $allowed = false;
            foreach ($this->config['allowed_domains'] as $safe) {
                if ($domain === $safe || str_ends_with($domain, '.' . $safe)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) return true;
        }
        return false;
    }

    /** @param list<string> $phrases */
    private function containsPhrase(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) return true;
        }
        return false;
    }

    /** @param list<string> $terms */
    private function countTerms(string $text, array $terms): int
    {
        $count = 0;
        foreach ($terms as $term) {
            if (preg_match('/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/u', $text) === 1) {
                $count++;
            }
        }
        return $count;
    }

    private function weight(string $key): int
    {
        return (int)$this->config['weights'][$key];
    }

    /** @param list<string> $reasons */
    private function add(int &$score, array &$reasons, int $points, string $reason): void
    {
        $score += $points;
        $reasons[] = $reason;
    }
}
