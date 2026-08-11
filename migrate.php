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

foreach (App\Core\Migrator::run(__DIR__ . '/migrations') as $name) {
    echo "applied  $name\n";
}

echo "up to date\n";
