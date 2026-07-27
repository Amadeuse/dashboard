<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/** `units` lookup table — managed from the modal on the products page. */
final class Unit
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT * FROM units ORDER BY name');
    }

    public static function create(string $name): int
    {
        $pdo = Db::conn();
        $pdo->prepare('INSERT INTO units (name) VALUES (?)')->execute([$name]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        Db::conn()->prepare('UPDATE units SET name = ? WHERE id = ?')->execute([$name, $id]);
    }
}
