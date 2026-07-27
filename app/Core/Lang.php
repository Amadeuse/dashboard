<?php

declare(strict_types=1);

namespace App\Core;

final class Lang
{
    public const AVAILABLE = ['ka', 'en'];

    private static string $current = 'ka';
    private static array $strings  = [];

    /** Default language, overridable via APP_LOCALE in .env. */
    public static function fallback(): string
    {
        $locale = (string) env('APP_LOCALE', 'ka');
        return in_array($locale, self::AVAILABLE, true) ? $locale : 'ka';
    }

    public static function boot(): void
    {
        $fallback  = self::fallback();
        $requested = $_GET['lang'] ?? null;
        $lang      = $requested ?? $_COOKIE['ds_lang'] ?? $fallback;

        // Whitelist before the value ever reaches the filesystem — $_GET is untrusted.
        if (!in_array($lang, self::AVAILABLE, true)) {
            $lang = $fallback;
        }

        if ($requested !== null && $lang === $requested) {
            setcookie('ds_lang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }

        self::$current = $lang;
        self::$strings = require APP_PATH . '/lang/' . $lang . '.php';
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function get(string $key, array $args = []): string
    {
        $s = self::$strings[$key] ?? $key; // missing key shows itself — easy to spot
        return $args ? vsprintf($s, $args) : $s;
    }

    /** '2026-07-24' → '24 ივლ, 2026' (ka) / 'Jul 24, 2026' (en) */
    public static function date(string $iso): string
    {
        [$y, $m, $d] = explode('-', $iso);
        $month = self::get('month.' . (int) $m);
        $day   = (int) $d;

        return self::$current === 'ka' ? "$day $month, $y" : "$month $day, $y";
    }
}
