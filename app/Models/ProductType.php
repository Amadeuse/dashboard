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
 */
final class ProductType
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT * FROM product_types ORDER BY name');
    }

    public static function create(string $name): int
    {
        $pdo = Db::conn();
        $pdo->prepare('INSERT INTO product_types (name) VALUES (?)')->execute([$name]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $name): void
    {
        Db::conn()->prepare('UPDATE product_types SET name = ? WHERE id = ?')->execute([$name, $id]);
    }
}
