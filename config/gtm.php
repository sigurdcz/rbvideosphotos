<?php

use App\Core\Env;

$appUrl = Env::get('APP_URL') ?: '';
if ($appUrl === '' && PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

$redirectUri = Env::get('GTM_REDIRECT_URI');
if ($redirectUri === null || $redirectUri === '') {
    $baseUrl = rtrim($appUrl, '/');
    $redirectUri = $baseUrl !== '' ? $baseUrl . '/admin/gtm/oauth/callback' : 'http://localhost:8000/admin/gtm/oauth/callback';
}

return [
    'client_id' => Env::get('GTM_CLIENT_ID') ?: '',
    'client_secret' => Env::get('GTM_CLIENT_SECRET') ?: '',
    'redirect_uri' => $redirectUri,
    'encryption_key' => Env::get('GTM_ENCRYPTION_KEY') ?: '',
    'auth_uri' => Env::get('GTM_AUTH_URI') ?: 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_uri' => Env::get('GTM_TOKEN_URI') ?: 'https://oauth2.googleapis.com/token',
    'api_base_uri' => Env::get('GTM_API_BASE_URI') ?: 'https://tagmanager.googleapis.com/tagmanager/v2',
    'scopes' => [
        'https://www.googleapis.com/auth/tagmanager.readonly',
        'https://www.googleapis.com/auth/tagmanager.edit.containers',
        'https://www.googleapis.com/auth/tagmanager.edit.containerversions',
        'https://www.googleapis.com/auth/tagmanager.publish',
    ],
    'request_timeout' => (int)(Env::get('GTM_REQUEST_TIMEOUT') ?: 20),
    'connect_timeout' => (int)(Env::get('GTM_CONNECT_TIMEOUT') ?: 10),
    'retry_delay_ms' => (int)(Env::get('GTM_RETRY_DELAY_MS') ?: 500),
];
