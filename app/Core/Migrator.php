<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Runs every *.sql in a directory that isn't already in the shared
 * `migrations` table, in filename order. Used by migrate.php for core
 * migrations (no prefix) and by ModuleRegistry::install() for a module's own
 * migrations (prefix = "<Code>/") — one table tracks both, so
 * "has this SQL ever run" has a single source of truth app-wide.
 */
final class Migrator
{
    /** @return list<string> names of the migrations applied just now */
    public static function run(string $dir, string $prefix = ''): array
    {
        $pdo = Db::conn();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            name VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        $applied = [];
        foreach (glob($dir . '/*.sql') as $file) {
            $name = $prefix . basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }

            $pdo->exec(file_get_contents($file));
            $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
            $applied[] = $name;
        }

        return $applied;
    }
}
