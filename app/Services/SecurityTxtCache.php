<?php

namespace App\Services;

use JsonException;
use RuntimeException;

class SecurityTxtCache
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/cache/security_txt';
    }

    public function get(int $siteId): ?array
    {
        $path = $this->path($siteId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read security.txt cache.');
        }
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('security.txt cache contains invalid JSON.', 0, $e);
        }
        if (!is_array($payload) || ($payload['version'] ?? null) !== 1) {
            throw new RuntimeException('security.txt cache has an unsupported format.');
        }

        return $payload;
    }

    public function put(int $siteId, array $payload): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create security.txt cache directory.');
        }

        $payload['version'] = 1;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (@file_put_contents($this->path($siteId), $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write security.txt cache.');
        }
    }

    public function forget(int $siteId): void
    {
        $path = $this->path($siteId);
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Unable to invalidate security.txt cache.');
        }
    }

    private function path(int $siteId): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . 'site-' . $siteId . '.json';
    }
}
