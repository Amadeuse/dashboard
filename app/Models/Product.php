<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `products` table (see migrations/004_create_products.sql, relaxed by 006).
 *
 * Core: name, type, unit, price. `product_type_id` (migrations/019) is a
 * plain core field again — independent of whether the Warehouse module
 * (its own `remaining_qty`/`image` on `product_warehouse`) is enabled.
 * `LEFT JOIN` in all() because rows that existed before migrations/019 have
 * no type yet (NULL) — Product::validate() requires it going forward, but
 * nothing backfills the old rows automatically.
 */
final class Product
{
    /** Editable columns, in form order. Everything else is DB-managed. */
    public const FIELDS = ['name', 'unit_id', 'product_type_id', 'unit_price'];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Db::all(
            'SELECT p.*, u.name AS unit_name, pt.name AS product_type_name
               FROM products p
               JOIN units u ON u.id = p.unit_id
               LEFT JOIN product_types pt ON pt.id = p.product_type_id
              ORDER BY p.id DESC'
        );
    }

    /** @return int the new row's id — `product.saved` needs it for a brand new product. */
    public static function create(array $data): int
    {
        $cols = implode(', ', self::FIELDS);
        $ph   = implode(', ', array_fill(0, count(self::FIELDS), '?'));

        $conn = Db::conn();
        $conn->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute(self::args($data));

        return (int) $conn->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $set  = implode(', ', array_map(static fn(string $f): string => "$f = ?", self::FIELDS));
        $args = self::args($data);
        $args[] = $id;

        Db::conn()->prepare("UPDATE products SET $set WHERE id = ?")->execute($args);
    }

    /** @return array{0: array<string,string>, 1: array<string,string>} [clean input, errors keyed by field] */
    public static function validate(array $input): array
    {
        $clean = [
            'name'            => trim((string) ($input['name'] ?? '')),
            'unit_id'         => trim((string) ($input['unit_id'] ?? '')),
            'product_type_id' => trim((string) ($input['product_type_id'] ?? '')),
            'unit_price'      => trim((string) ($input['unit_price'] ?? '')),
        ];

        $errors = [];

        if ($clean['name'] === '') {
            $errors['name'] = terr('prod.err_name_required');
        } elseif (mb_strlen($clean['name']) > 255) {
            $errors['name'] = terr('cust.err_too_long', 255);
        }

        if (!ctype_digit($clean['unit_id']) || self::lookupMissing('units', (int) $clean['unit_id'])) {
            $errors['unit_id'] = terr('prod.err_unit_required');
        }

        if (!ctype_digit($clean['product_type_id']) || self::lookupMissing('product_types', (int) $clean['product_type_id'])) {
            $errors['product_type_id'] = terr('prod.err_type_required');
        }

        if (!is_numeric($clean['unit_price']) || (float) $clean['unit_price'] < 0) {
            $errors['unit_price'] = terr('prod.err_price');
        }

        return [$clean, $errors];
    }

    private static function lookupMissing(string $table, int $id): bool
    {
        return Db::all("SELECT 1 FROM `$table` WHERE id = ? LIMIT 1", [$id]) === [];
    }

    /** Column order for the prepared statement, matching self::FIELDS. */
    private static function args(array $data): array
    {
        return [
            $data['name'],
            (int) $data['unit_id'],
            (int) $data['product_type_id'],
            (float) $data['unit_price'],
        ];
    }
}
