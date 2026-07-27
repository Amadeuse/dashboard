<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `products` table (see migrations/004_create_products.sql).
 *
 * `image` holds only the stored filename (public/assets/uploads/products/) —
 * the controller resolves the upload and passes the filename in like any
 * other field, same shape as customer_taxid in Customer::create()/update().
 */
final class Product
{
    /** Editable columns, in form order. Everything else is DB-managed. */
    public const FIELDS = [
        'name', 'product_type_id', 'unit_id', 'unit_price', 'remaining_qty', 'image',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Db::all(
            'SELECT p.*, pt.name AS product_type_name, u.name AS unit_name
               FROM products p
               JOIN product_types pt ON pt.id = p.product_type_id
               JOIN units u          ON u.id = p.unit_id
              ORDER BY p.id DESC'
        );
    }

    public static function create(array $data): void
    {
        $cols = implode(', ', self::FIELDS);
        $ph   = implode(', ', array_fill(0, count(self::FIELDS), '?'));

        Db::conn()
            ->prepare("INSERT INTO products ($cols) VALUES ($ph)")
            ->execute(self::args($data));
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
            'name'          => trim((string) ($input['name'] ?? '')),
            'product_type_id' => trim((string) ($input['product_type_id'] ?? '')),
            'unit_id'         => trim((string) ($input['unit_id'] ?? '')),
            'unit_price'      => trim((string) ($input['unit_price'] ?? '')),
            'remaining_qty'   => trim((string) ($input['remaining_qty'] ?? '')),
        ];

        $errors = [];

        if ($clean['name'] === '') {
            $errors['name'] = t('prod.err_name_required');
        } elseif (mb_strlen($clean['name']) > 255) {
            $errors['name'] = t('cust.err_too_long', 255);
        }

        if (!ctype_digit($clean['product_type_id']) || self::lookupMissing('product_types', (int) $clean['product_type_id'])) {
            $errors['product_type_id'] = t('prod.err_type_required');
        }

        if (!ctype_digit($clean['unit_id']) || self::lookupMissing('units', (int) $clean['unit_id'])) {
            $errors['unit_id'] = t('prod.err_unit_required');
        }

        if (!is_numeric($clean['unit_price']) || (float) $clean['unit_price'] < 0) {
            $errors['unit_price'] = t('prod.err_price');
        }

        if (!is_numeric($clean['remaining_qty']) || (float) $clean['remaining_qty'] < 0) {
            $errors['remaining_qty'] = t('prod.err_qty');
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
            (int) $data['product_type_id'],
            (int) $data['unit_id'],
            (float) $data['unit_price'],
            (float) $data['remaining_qty'],
            $data['image'] !== '' ? $data['image'] : null,
        ];
    }
}
