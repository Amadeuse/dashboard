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

    /** @var list<string> module codes whose strings are already merged in */
    private static array $loadedModules = [];

    /**
     * Merges app/Modules/<Code>/lang/<lang>.php into the string table (4.113).
     *
     * A module keeps its own text, which is the whole point: the first module
     * system put module strings in app/lang/*.php, so removing a module left
     * its text behind in core and shipping one meant editing a core file.
     * Core strings win on a key collision — a module can add vocabulary, never
     * silently redefine core wording. Falls back to the default language when
     * the module hasn't been translated into the current one, and does nothing
     * at all when it ships no lang/ directory.
     */
    public static function loadModule(string $code): void
    {
        if (in_array($code, self::$loadedModules, true) || !ModuleRegistry::isValidCode($code)) {
            return;
        }
        self::$loadedModules[] = $code;

        $dir  = APP_PATH . '/Modules/' . $code . '/lang/';
        $file = $dir . self::$current . '.php';
        if (!is_file($file)) {
            $file = $dir . self::fallback() . '.php';
        }
        if (!is_file($file)) {
            return;
        }

        $strings = require $file;
        if (is_array($strings)) {
            self::$strings += $strings;   // '+' keeps existing core keys
        }
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
