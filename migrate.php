<?php

declare(strict_types=1);

/**
 * Migration runner:  php migrate.php
 *
 * Runs every migrations/*.sql that is not yet in the `migrations` table, in
 * filename order. Applied files are never re-run — to change a table, add a
 * new numbered file instead of editing an old one.
 */

require __DIR__ . '/app/bootstrap.php';

$pdo = App\Core\Db::conn();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
    name VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

$done = $pdo->query('SELECT name FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $name = basename($file);
    if (in_array($name, $done, true)) {
        continue;
    }

    $pdo->exec(file_get_contents($file));
    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
    echo "applied  $name\n";
}

echo "up to date\n";
