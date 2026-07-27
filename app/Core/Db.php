<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Single shared PDO connection, configured from .env (DB_HOST/DB_NAME/DB_USER/DB_PASS).
 *
 * OSPanel note: DB_HOST is the module name ("MySQL-8.4"), which OSPanel puts in the
 * Windows hosts file — no port needed.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                (string) env('DB_HOST', 'localhost'),
                (string) env('DB_NAME', 'dashboard')
            );

            self::$pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$pdo;
    }

    /** SELECT helper: Db::all('SELECT * FROM customers WHERE ruler = ?', [$id]) */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll();
    }
}
