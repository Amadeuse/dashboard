<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `units` lookup table — managed from the modal on the products page.
 *
 * Tenant-scoped since migrations/039 (4.133), with one twist product_types
 * doesn't have: a NULL ruler is a shared default. The six units seeded in
 * 003 (ცალი, კგ, ...) are NULL — every tenant sees them, nobody can rename
 * them, and the invoices and products already pointing at them keep
 * working. Anything a tenant adds is theirs: visible to them, editable by
 * them, invisible to everyone else.
 */
final class Unit
{
    /** Shared defaults plus this tenant's own. @return array<int, array<string, mixed>> */
    public static function all(int $ruler): array
    {
        return Db::all('SELECT * FROM units WHERE ruler IS NULL OR ruler = ? ORDER BY name', [$ruler]);
    }

    public static function create(string $name, int $ruler): int
    {
        $pdo = Db::conn();
        $pdo->prepare('INSERT INTO units (name, ruler) VALUES (?, ?)')->execute([$name, $ruler]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Renames one of this tenant's own units. Returns the rows changed — 0
     * for a shared default (ruler NULL never matches) or another tenant's
     * id, which the controller turns into a 404 rather than a false success.
     */
    public static function update(int $id, string $name, int $ruler): int
    {
        $st = Db::conn()->prepare('UPDATE units SET name = ? WHERE id = ? AND ruler = ?');
        $st->execute([$name, $id, $ruler]);

        return $st->rowCount();
    }
}
