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

    /**
     * Overrides one value after load() — for the module test harness in the
     * modules.loc project, which points DB_NAME at a scratch database so a
     * module's tests can never touch real data. Must be called
     * before anything opens a connection, since Db holds one PDO for the
     * process. Not for application code: config belongs in .env.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$vars[$key] = $value;
    }
}
