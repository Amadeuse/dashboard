<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `customers` table (see migrations/001_create_customers.sql).
 *
 * Invoicing context: customer_taxid is the field that ends up printed on every
 * invoice, so it is validated harder than the rest and checked for duplicates —
 * two customer rows sharing a tax id means invoices that cannot be reconciled.
 * The DB index on it is deliberately non-unique (a walk-in customer may have
 * none), so the guard lives here.
 */
final class Customer
{
    /** Editable columns, in form order. Everything else is DB-managed. */
    public const FIELDS = [
        'customer_name', 'customer_taxid', 'customer_contact',
        'customer_phone', 'customer_email', 'customer_address', 'customer_info',
    ];

    /**
     * Every customer, newest first. Searching, sorting and paging happen in the
     * browser (assets/js/ds-table.js), so the page ships the whole list once.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Db::all('SELECT * FROM customers ORDER BY id DESC');
    }

    public static function create(array $data): void
    {
        $cols = implode(', ', self::FIELDS);
        $ph   = implode(', ', array_fill(0, count(self::FIELDS), '?'));

        $st = Db::conn()->prepare("INSERT INTO customers ($cols) VALUES ($ph)");
        $st->execute(array_map(
            static fn(string $f): ?string => ($data[$f] ?? '') === '' ? null : $data[$f],
            self::FIELDS
        ));
    }

    public static function update(int $id, array $data): void
    {
        $set = implode(', ', array_map(static fn(string $f): string => "$f = ?", self::FIELDS));

        $st    = Db::conn()->prepare("UPDATE customers SET $set WHERE id = ?");
        $args  = array_map(
            static fn(string $f): ?string => ($data[$f] ?? '') === '' ? null : $data[$f],
            self::FIELDS
        );
        $args[] = $id;
        $st->execute($args);
    }

    /**
     * $excludeId lets an edit keep its own tax id — otherwise a customer would
     * collide with itself the moment its own record was checked for duplicates.
     *
     * @return array{0: array<string,string>, 1: array<string,string>} [clean input, errors keyed by field]
     */
    public static function validate(array $input, ?int $excludeId = null): array
    {
        $clean = [];
        foreach (self::FIELDS as $f) {
            $clean[$f] = trim((string) ($input[$f] ?? ''));
        }

        $errors = [];

        if ($clean['customer_name'] === '') {
            $errors['customer_name'] = terr('cust.err_name_required');
        } elseif (mb_strlen($clean['customer_name']) > 255) {
            $errors['customer_name'] = terr('cust.err_too_long', 255);
        }

        // The imported data uses '0' as "no tax id" (271 rows of it). Normalise it to
        // NULL, otherwise the duplicate check below would reject every customer after
        // the first one that has no tax id.
        if ($clean['customer_taxid'] === '0') {
            $clean['customer_taxid'] = '';
        }

        if ($clean['customer_taxid'] !== '') {
            if (mb_strlen($clean['customer_taxid']) > 64) {
                $errors['customer_taxid'] = terr('cust.err_too_long', 64);
            } elseif (self::taxIdTaken($clean['customer_taxid'], $excludeId)) {
                $errors['customer_taxid'] = terr('cust.err_taxid_taken');
            }
        }

        if ($clean['customer_email'] !== '' && !filter_var($clean['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = terr('cust.err_email');
        }

        foreach (['customer_contact', 'customer_phone', 'customer_email', 'customer_address'] as $f) {
            if (mb_strlen($clean[$f]) > 255) {
                $errors[$f] = terr('cust.err_too_long', 255);
            }
        }

        return [$clean, $errors];
    }

    private static function taxIdTaken(string $taxid, ?int $excludeId): bool
    {
        if ($excludeId !== null) {
            return Db::all(
                'SELECT 1 FROM customers WHERE customer_taxid = ? AND id != ? LIMIT 1',
                [$taxid, $excludeId]
            ) !== [];
        }

        return Db::all('SELECT 1 FROM customers WHERE customer_taxid = ? LIMIT 1', [$taxid]) !== [];
    }
}
