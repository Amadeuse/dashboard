<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `product_types` lookup table — managed from the modal on the products page.
 * Deliberately near-identical to App\Modules\Warehouse\Models\ProductType —
 * same table, same shape, duplicated rather than shared across the
 * core/module boundary (see handoff.md 4.19's reasoning for LookupController/
 * ProductTypeController, the same call applies here).
 *
 * Multi-tenant (migrations/023): `ruler` (App\Core\Auth::tenantId()) scopes
 * every read/write, so each tenant manages their own type list rather than a
 * shared global one — update() folds `ruler = ?` into its WHERE so a forged
 * id from another tenant can never be renamed, not just hidden from the list.
 */
final class ProductType
{
    /** @return array<int, array<string, mixed>> */
    public static function all(int $ruler): array
    {
        return Db::all('SELECT * FROM product_types WHERE ruler = ? ORDER BY name', [$ruler]);
    }

    public static function create(string $name, int $ruler): int
    {
        $pdo = Db::conn();
        $pdo->prepare('INSERT INTO product_types (name, ruler) VALUES (?, ?)')->execute([$name, $ruler]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name, int $ruler): void
    {
        Db::conn()->prepare('UPDATE product_types SET name = ? WHERE id = ? AND ruler = ?')->execute([$name, $id, $ruler]);
    }
}
