<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Google OAuth2 "Sign in with Google" — the plain Authorization Code flow
 * over two HTTP calls (token exchange + userinfo), no google/apiclient
 * dependency (this app has no Composer). Config: GOOGLE_CLIENT_ID,
 * GOOGLE_CLIENT_SECRET in .env — the user creates these in Google Cloud
 * Console (OAuth consent screen + Web application credentials), which
 * needs their own Google account and isn't something this code can do.
 */
final class GoogleOAuth
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public static function authorizeUrl(string $state): string
    {
        $params = [
            'client_id'     => (string) env('GOOGLE_CLIENT_ID', ''),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    public static function redirectUri(): string
    {
        $scheme = ($_SERVER['HTTPS'] ?? '') === 'on' ? 'https' : 'http';

        return "$scheme://{$_SERVER['HTTP_HOST']}/auth/google/callback";
    }

    /** @return ?array{sub:string,email:string,name:string} */
    public static function exchangeCode(string $code): ?array
    {
        $token = self::post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => (string) env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string) env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        if (!isset($token['access_token'])) {
            return null;
        }

        $context = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer {$token['access_token']}\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents(self::USERINFO_URL, false, $context);
        $user = $body !== false ? json_decode($body, true) : null;

        return isset($user['sub'], $user['email']) ? [
            'sub'   => (string) $user['sub'],
            'email' => (string) $user['email'],
            'name'  => (string) ($user['name'] ?? $user['email']),
        ] : null;
    }

    private static function post(string $url, array $params): ?array
    {
        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content'       => http_build_query($params),
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);

        return $body !== false ? json_decode($body, true) : null;
    }
}
