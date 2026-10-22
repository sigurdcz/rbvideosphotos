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
    private const BLOCK_SCORE = 7;

    /** @return array{isSpam: bool, score: int, reasons: list<string>} */
    public function classify(string $name, string $contact, string $message): array
    {
        $nameText = $this->normalize($name);
        $contactText = $this->normalize($contact);
        $messageText = $this->normalize($message);
        $all = trim($nameText . ' ' . $messageText);
        $score = 0;
        $reasons = [];

        if (str_contains($all, 'thank you very much for your inquiry we appreciate you')) {
            $this->add($score, $reasons, 8, 'known-bot-boilerplate');
        }

        if ($this->containsUrl($nameText)) {
            $this->add($score, $reasons, 7, 'url-in-name');
        }

        $promotionTerms = preg_match_all(
            '/\b(?:casino|jackpot|bonus|reel|spin|cash|vault|wins?|gambling|betting)\b/u',
            $all
        );
        if (is_int($promotionTerms) && $promotionTerms >= 2) {
            $this->add($score, $reasons, 6, 'gambling-promotion');
        }

        if (preg_match('/\b(?:play\s+now|click\s+here|visit\s+now|no\s+luck\?\s+no\s+problem)\b/u', $all) === 1) {
            $this->add($score, $reasons, 3, 'promotional-call-to-action');
        }

        if ($this->containsUrl($messageText)) {
            $this->add($score, $reasons, 2, 'link-in-message');
        }

        // Bot payloads often put an email first and append random tokens to the
        // contact field. This is only a weak signal and cannot block by itself.
        if (preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+\s+\S+/u', $contactText) === 1) {
            $this->add($score, $reasons, 2, 'contact-field-junk');
        }

        return ['isSpam' => $score >= self::BLOCK_SCORE, 'score' => $score, 'reasons' => $reasons];
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

    /** @param list<string> $reasons */
    private function add(int &$score, array &$reasons, int $points, string $reason): void
    {
        $score += $points;
        $reasons[] = $reason;
    }
}
