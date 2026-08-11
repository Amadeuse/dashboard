<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pixabay API — random photo by search query, for the auth pages' visual
 * panel. Swapped in for Unsplash: same idea, but Pixabay's free tier is
 * 100 requests/minute (Unsplash's "Demo" tier was 50/*hour*, too tight for
 * a panel polling every 10-15s). Config: PIXABAY_API_KEY in .env — a free
 * key from pixabay.com/api/docs/, no app review needed.
 *
 * Pixabay's API terms require showing where results are from whenever they're
 * displayed — the photographer name + a link to the image's Pixabay page,
 * both returned here and rendered as a credit line by the caller.
 */
final class Pixabay
{
    private const API_URL = 'https://pixabay.com/api/';

    /** @return ?array{url:string,photographerName:string,photographerUrl:string} */
    public static function randomPhoto(string $query, string $orientation = 'vertical'): ?array
    {
        $key = (string) env('PIXABAY_API_KEY', '');
        if ($key === '') {
            return null;
        }

        $url = self::API_URL . '?' . http_build_query([
            'key'         => $key,
            'q'           => $query,
            'image_type'  => 'photo',
            'orientation' => $orientation,
            'safesearch'  => 'true',
            'per_page'    => 50,
        ]);

        $context = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
        $body    = @file_get_contents($url, false, $context);
        $data    = $body !== false ? json_decode($body, true) : null;

        $hits = $data['hits'] ?? [];
        if ($hits === []) {
            return null;
        }

        // A fresh random pick from the page each call is what gives the
        // rotation its variety — Pixabay has no "random single photo" endpoint.
        $hit = $hits[array_rand($hits)];

        return [
            'url'              => (string) ($hit['largeImageURL'] ?? $hit['webformatURL']),
            'photographerName' => (string) ($hit['user'] ?? 'Pixabay'),
            'photographerUrl'  => (string) ($hit['pageURL'] ?? 'https://pixabay.com'),
        ];
    }
}
