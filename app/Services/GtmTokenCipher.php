<?php

namespace App\Services;

use App\Core\Config;
use RuntimeException;

class GtmTokenCipher
{
    private string $key = '';

    public function __construct()
    {
        $configured = trim((string) Config::get('gtm', 'encryption_key', ''));
        if ($configured !== '') {
            $this->key = hash('sha256', $configured, true);
        }
    }

    public function isConfigured(): bool
    {
        return $this->key !== '';
    }

    public function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        if ($this->key === '') {
            throw new RuntimeException('GTM encryption key is not configured.');
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL extension is required for GTM token encryption.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plainText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Unable to encrypt GTM token.');
        }

        return base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $payload): string
    {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return '';
        }

        if ($this->key === '') {
            throw new RuntimeException('GTM encryption key is not configured.');
        }

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('OpenSSL extension is required for GTM token decryption.');
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Invalid GTM token payload.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plainText = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plainText === false) {
            throw new RuntimeException('Unable to decrypt GTM token.');
        }

        return $plainText;
    }
}
