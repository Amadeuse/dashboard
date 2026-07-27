<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny .env reader built on parse_ini_file() — no dependency needed.
 *
 * INI rules that bite, so quote anything unusual:
 *   DB_PASS="p@ss#word"   # unquoted, everything after # is a comment
 *   NOTE="a=b"            # unquoted, only the first = splits
 * INI_SCANNER_TYPED turns true/false into real bools and digits into ints.
 *
 * Values are kept in this class only — deliberately not pushed into putenv()/$_ENV,
 * which would leak secrets into any subprocess the app spawns.
 */
final class Env
{
    private static array $vars = [];

    /** Missing file is fine: every env() call then falls back to its default. */
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $parsed = parse_ini_file($file, false, INI_SCANNER_TYPED);
        if ($parsed !== false) {
            self::$vars = $parsed;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$vars[$key] ?? $default;
    }
}
