<?php

declare(strict_types=1);

namespace App\Core;

/**
 * smsoffice.ge HTTP API — GET request, query-string params, no SDK (same
 * "no Composer dependency" stance as Mailer). API doc: a simple GET to
 * /api/v2/send/ with key/destination/sender/content; the response body is a
 * bare number — a positive message id on success, otherwise an error code.
 * Config: SMS_API_KEY, SMS_SENDER in .env.
 */
final class Sms
{
    private const API_URL = 'https://smsoffice.ge/api/v2/send/';

    public static function send(string $phone, string $text): bool
    {
        $key = (string) env('SMS_API_KEY', '');
        $to  = self::normalize($phone);
        if ($key === '' || $to === null) {
            return false;
        }

        $url = self::API_URL . '?' . http_build_query([
            'key'         => $key,
            // The API's "destination" wants bare digits — normalize()'s "+" is
            // the app's canonical stored/display format, not a wire format.
            'destination' => ltrim($to, '+'),
            'sender'      => (string) env('SMS_SENDER', ''),
            'content'     => $text,
            'urgent'      => 'true',
        ]);

        $context  = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $response = @file_get_contents($url, false, $context);

        return $response !== false && ctype_digit(trim($response)) && (int) trim($response) > 0;
    }

    /** Georgian mobile numbers → "+995XXXXXXXXX" (12 digits + leading +), or null if it doesn't look like one. */
    public static function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (!str_starts_with($digits, '995')) {
            $digits = '995' . ltrim($digits, '0');
        }

        return strlen($digits) === 12 ? '+' . $digits : null;
    }
}
