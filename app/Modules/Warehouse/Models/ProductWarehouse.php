<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Core\Db;

/**
 * `product_warehouse` — 1:1 with `products` (product_id is both PK and FK,
 * see migrations/001_create_product_warehouse.sql). Its own page
 * (/warehouse) picks an existing product from a dropdown rather than
 * creating one — Products already owns "create a product".
 */
final class ProductWarehouse
{
    /** @return array<int, array<string, mixed>> newest-first by product name */
    public static function all(): array
    {
        return Db::all(
            'SELECT pw.*, p.name AS product_name, p.unit_price, pt.name AS product_type_name
               FROM product_warehouse pw
               JOIN products p      ON p.id = pw.product_id
               JOIN product_types pt ON pt.id = pw.product_type_id
              ORDER BY p.name'
        );
    }

    public static function upsert(int $productId, array $data): void
    {
        Db::conn()->prepare(
            'INSERT INTO product_warehouse (product_id, product_type_id, remaining_qty, image)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE product_type_id = VALUES(product_type_id),
                                     remaining_qty   = VALUES(remaining_qty),
                                     image           = VALUES(image)'
        )->execute([
            $productId,
            $data['product_type_id'],
            $data['remaining_qty'],
            $data['image'] !== '' ? $data['image'] : null,
        ]);
    }

    /**
     * @return array{0: array<string,string>, 1: array<string,string>} [clean input, errors]
     * `image` isn't part of this — it's a file, handled separately (WarehouseController).
     */
    public static function validate(array $input): array
    {
        $clean = [
            'product_id'      => trim((string) ($input['product_id'] ?? '')),
            'product_type_id' => trim((string) ($input['product_type_id'] ?? '')),
            'remaining_qty'   => trim((string) ($input['remaining_qty'] ?? '')),
        ];

        $errors = [];

        if (!ctype_digit($clean['product_id']) || self::productMissing((int) $clean['product_id'])) {
            $errors['product_id'] = terr('warehouse.err_product_required');
        }

        if (!ctype_digit($clean['product_type_id']) || self::typeMissing((int) $clean['product_type_id'])) {
            $errors['product_type_id'] = terr('prod.err_type_required');
        }

        if (!is_numeric($clean['remaining_qty']) || (float) $clean['remaining_qty'] < 0) {
            $errors['remaining_qty'] = terr('prod.err_qty');
        }

        return [$clean, $errors];
    }

    private static function productMissing(int $id): bool
    {
        return Db::all('SELECT 1 FROM products WHERE id = ? LIMIT 1', [$id]) === [];
    }

    private static function typeMissing(int $id): bool
    {
        return Db::all('SELECT 1 FROM product_types WHERE id = ? LIMIT 1', [$id]) === [];
    }
}
