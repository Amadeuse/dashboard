<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Core\Db;

/** `product_types` lookup table — managed from the modal on the products page. */
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
