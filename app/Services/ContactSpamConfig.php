<?php

declare(strict_types=1);

namespace App\Services;

final class ContactSpamConfig
{
    public const VERSION = 1;

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'version' => self::VERSION,
            'enabled' => true,
            'notify_recipients' => false,
            'threshold' => 7,
            'weights' => [
                'known_phrase' => 8,
                'url_in_name' => 7,
                'promotion_terms' => 6,
                'call_to_action' => 3,
                'link_in_message' => 2,
                'contact_junk' => 2,
            ],
            'known_phrases' => [
                'thank you very much for your inquiry we appreciate you',
                'pxqrrbvideosphotos.czpxqr',
                'предлагаем кассовые чеки',
                'отчетные закрывающие документы',
            ],
            'promotion_terms' => [
                'casino', 'jackpot', 'bonus', 'reel', 'spin', 'cash', 'vault', 'wins', 'win', 'gambling', 'betting',
                'казино', 'играть', 'dragonmoney',
            ],
            'calls_to_action' => ['play now', 'click here', 'visit now', 'no luck? no problem'],
            'allowed_domains' => [],
        ];
    }

    /** @return array<string,mixed> */
    public static function fromStored(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return self::defaults();
        }
        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Uložená konfigurace antispamu není platný JSON.', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Uložená konfigurace antispamu musí být objekt.');
        }
        return self::validate($decoded);
    }

    /** @return array<string,mixed> */
    public static function fromPost(array $post): array
    {
        return self::validate([
            'version' => self::VERSION,
            'enabled' => isset($post['contact_spam_enabled']),
            'notify_recipients' => isset($post['contact_spam_notify_recipients']),
            'threshold' => $post['contact_spam_threshold'] ?? null,
            'weights' => [
                'known_phrase' => $post['contact_spam_weight_known_phrase'] ?? null,
                'url_in_name' => $post['contact_spam_weight_url_in_name'] ?? null,
                'promotion_terms' => $post['contact_spam_weight_promotion_terms'] ?? null,
                'call_to_action' => $post['contact_spam_weight_call_to_action'] ?? null,
                'link_in_message' => $post['contact_spam_weight_link_in_message'] ?? null,
                'contact_junk' => $post['contact_spam_weight_contact_junk'] ?? null,
            ],
            'known_phrases' => self::parseList((string)($post['contact_spam_known_phrases'] ?? '')),
            'promotion_terms' => self::parseList((string)($post['contact_spam_promotion_terms'] ?? '')),
            'calls_to_action' => self::parseList((string)($post['contact_spam_calls_to_action'] ?? '')),
            'allowed_domains' => self::parseList((string)($post['contact_spam_allowed_domains'] ?? '')),
        ]);
    }

    public static function encode(array $config): string
    {
        return json_encode(self::validate($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function listText(array $values): string
    {
        return implode("\n", array_map('strval', $values));
    }

    /** @return array<string,mixed> */
    private static function validate(array $config): array
    {
        if ((int)($config['version'] ?? 0) !== self::VERSION) {
            throw new \InvalidArgumentException('Konfigurace antispamu má nepodporovanou verzi.');
        }

        $threshold = self::boundedInt($config['threshold'] ?? null, 'Práh', 1, 100);
        $weightKeys = array_keys(self::defaults()['weights']);
        $weights = [];
        foreach ($weightKeys as $key) {
            $weights[$key] = self::boundedInt($config['weights'][$key] ?? null, 'Bodová váha', 0, 100);
        }

        $known = self::validateList($config['known_phrases'] ?? null, 'Známé spamové fráze', false);
        $promotion = self::validateList($config['promotion_terms'] ?? null, 'Reklamní slova', false);
        $calls = self::validateList($config['calls_to_action'] ?? null, 'Reklamní výzvy', false);
        $domains = self::validateList($config['allowed_domains'] ?? null, 'Povolené domény', true, true);

        $enabled = $config['enabled'] ?? null;
        if (!is_bool($enabled)) {
            throw new \InvalidArgumentException('Přepínač antispamu musí být boolean.');
        }
        // Version 1 configurations saved before spam notifications existed do
        // not contain this optional flag. Missing therefore means disabled.
        $notifyRecipients = $config['notify_recipients'] ?? false;
        if (!is_bool($notifyRecipients)) {
            throw new \InvalidArgumentException('Přepínač upozornění na spam musí být boolean.');
        }

        return [
            'version' => self::VERSION,
            'enabled' => $enabled,
            'notify_recipients' => $notifyRecipients,
            'threshold' => $threshold,
            'weights' => $weights,
            'known_phrases' => $known,
            'promotion_terms' => $promotion,
            'calls_to_action' => $calls,
            'allowed_domains' => $domains,
        ];
    }

    private static function boundedInt(mixed $value, string $label, int $min, int $max): int
    {
        $raw = is_string($value) ? trim($value) : $value;
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException($label . ' musí být celé číslo.');
        }
        $number = (int)$raw;
        if ($number < $min || $number > $max) {
            throw new \InvalidArgumentException(sprintf('%s musí být v rozsahu %d až %d.', $label, $min, $max));
        }
        return $number;
    }

    /** @return list<string> */
    private static function parseList(string $text): array
    {
        return preg_split('/[\r\n,]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @return list<string> */
    private static function validateList(mixed $values, string $label, bool $mayBeEmpty, bool $domains = false): array
    {
        if (!is_array($values)) {
            throw new \InvalidArgumentException($label . ' musí být seznam.');
        }
        if (count($values) > 100) {
            throw new \InvalidArgumentException($label . ' smí obsahovat nejvýše 100 položek.');
        }
        $result = [];
        foreach ($values as $value) {
            $item = self::normalize((string)$value);
            if ($item === '') continue;
            if (mb_strlen($item) > 200) {
                throw new \InvalidArgumentException($label . ' obsahuje příliš dlouhou položku.');
            }
            if ($domains) {
                $item = preg_replace('~^https?://~i', '', $item);
                $item = trim((string)$item, "./ \t\n\r\0\x0B");
                if (preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $item) !== 1) {
                    throw new \InvalidArgumentException('Povolená doména není platná: ' . $item);
                }
            }
            $result[$item] = true;
        }
        $normalized = array_keys($result);
        if (!$mayBeEmpty && $normalized === []) {
            throw new \InvalidArgumentException($label . ' nesmí být prázdné.');
        }
        return $normalized;
    }

    private static function normalize(string $value): string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lower = function_exists('mb_strtolower') ? mb_strtolower($decoded, 'UTF-8') : strtolower($decoded);
        return trim((string)preg_replace('/\s+/u', ' ', $lower));
    }
}
