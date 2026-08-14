<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * `invoices` + `invoice_items` (see migrations/013, 014). One invoice has
 * many line items (product/quantity/price); editing an invoice replaces its
 * items wholesale (DELETE + INSERT) rather than diffing — same approach
 * Organization::save() uses for bank_ibans.
 *
 * `unit_price`/`line_total` on each item are a snapshot taken at save time,
 * not a live join against products.unit_price — a later price change must
 * not alter an already-issued invoice.
 */
final class Invoice
{
    public const STATUSES = ['draft', 'final', 'due', 'paid'];

    /** "{prefix} {issue_date} {0004}" — the one place this format is written, every view calls this instead of re-formatting. */
    public static function number(array $row, string $prefix): string
    {
        return sprintf('%s %s %04d', $prefix, $row['issue_date'], (int) $row['id']);
    }

    /**
     * @param int|null $createdBy when given, only that user's own invoices
     *   (orders.php's table — each user sees only what they created).
     *   $invoicesByCustomer on /invoices stays unfiltered on purpose: a
     *   customer's full invoice history is relevant there regardless of who
     *   created each one.
     * @return array<int, array<string, mixed>> newest first, with the customer's
     *   name/tax id and the creator's name (orders.php's table) joined in.
     *   creator_name is NULL for invoices predating created_by (migrations/021).
     */
    public static function all(?int $createdBy = null): array
    {
        $sql = 'SELECT i.*, c.customer_name, c.customer_taxid, u.name AS creator_name
                  FROM invoices i
                  JOIN customers c ON c.id = i.customer_id
                  LEFT JOIN users u ON u.id = i.created_by';
        $args = [];

        if ($createdBy !== null) {
            $sql .= ' WHERE i.created_by = ?';
            $args[] = $createdBy;
        }

        $sql .= ' ORDER BY i.id DESC';

        return Db::all($sql, $args);
    }

    /** One invoice plus the customer fields the print/view page's "Bill To" needs, or null if it doesn't exist. */
    public static function find(int $id): ?array
    {
        $rows = Db::all(
            'SELECT i.*, c.customer_name, c.customer_taxid, c.customer_contact,
                    c.customer_phone, c.customer_email, c.customer_address
               FROM invoices i
               JOIN customers c ON c.id = i.customer_id
              WHERE i.id = ?',
            [$id]
        );

        return $rows[0] ?? null;
    }

    /** @return array<int, array<string,mixed>> one invoice's line items, product name joined in */
    public static function itemsFor(int $id): array
    {
        return Db::all(
            'SELECT ii.*, p.name AS product_name
               FROM invoice_items ii
               JOIN products p ON p.id = ii.product_id
              WHERE ii.invoice_id = ?
              ORDER BY ii.id',
            [$id]
        );
    }

    /**
     * @param array{customer_id:string,status:string,is_zero:int,is_recurring:int,notes:string,items:list<array{product_id:string,quantity:string,unit_price:string}>} $clean
     * @param string|null $expectedUpdatedAt for an edit: the `updated_at` the
     *   form was loaded with (a hidden field — see invoices.php). Ignored
     *   when $editingId is null (a brand new row has nothing to conflict with).
     * @return int|null the invoice id, or null if $editingId was given and
     *   the row's real `updated_at` no longer matches $expectedUpdatedAt —
     *   someone else saved this invoice first, nothing was written, the
     *   transaction rolled back. Optimistic-locking guard, see handoff.md's
     *   multi-user section.
     *
     * issue_date is never taken from input — a new invoice is dated today,
     * and editing one never moves that date (the UPDATE below simply
     * doesn't mention the column). No user-facing date picker means no way
     * to backdate/misdate an invoice by accident.
     *
     * @param int|null $createdBy the logged-in user at creation time (Auth::user()),
     *   only ever written on INSERT — "who created this" doesn't change on edit,
     *   so the UPDATE branch below never touches created_by.
     */
    public static function save(array $clean, ?int $editingId, ?string $expectedUpdatedAt = null, ?int $createdBy = null): ?int
    {
        $total = 0.0;
        foreach ($clean['items'] as $item) {
            $total += (float) $item['quantity'] * (float) $item['unit_price'];
        }

        $conn = Db::conn();
        $conn->beginTransaction();

        if ($editingId !== null) {
            // FOR UPDATE locks the row for the rest of this transaction — no
            // other request can change it between this read and our write, so
            // the comparison below is race-free (not just "probably fine").
            $lock = $conn->prepare('SELECT updated_at FROM invoices WHERE id = ? FOR UPDATE');
            $lock->execute([$editingId]);
            $actualUpdatedAt = $lock->fetchColumn();

            if ($actualUpdatedAt === false || $actualUpdatedAt !== $expectedUpdatedAt) {
                $conn->rollBack();
                return null;
            }

            $conn->prepare('UPDATE invoices SET customer_id = ?, total = ?, status = ?, is_zero = ?, is_recurring = ?, notes = ? WHERE id = ?')
                ->execute([
                    (int) $clean['customer_id'], $total, $clean['status'],
                    $clean['is_zero'], $clean['is_recurring'], $clean['notes'], $editingId,
                ]);
            $conn->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$editingId]);
            $invoiceId = $editingId;
        } else {
            $conn->prepare(
                'INSERT INTO invoices (customer_id, issue_date, total, status, is_zero, is_recurring, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                (int) $clean['customer_id'], date('Y-m-d'), $total,
                $clean['status'], $clean['is_zero'], $clean['is_recurring'], $clean['notes'], $createdBy,
            ]);
            $invoiceId = (int) $conn->lastInsertId();
        }

        $insertItem = $conn->prepare(
            'INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($clean['items'] as $item) {
            $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
            $insertItem->execute([
                $invoiceId, (int) $item['product_id'], $item['quantity'], $item['unit_price'], $lineTotal,
            ]);
        }

        $conn->commit();

        return $invoiceId;
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string,string>} [clean, errors]
     *   clean = ['customer_id','status','is_zero','is_recurring','notes',
     *            'items' => list of ['product_id','quantity','unit_price']]
     *   notes is free text, no validation — an empty textarea just stores ''.
     */
    public static function validate(array $input): array
    {
        $status = (string) ($input['status'] ?? '');
        $clean = [
            'customer_id'  => trim((string) ($input['customer_id'] ?? '')),
            // An unrecognized value (missing field, tampered request) just falls
            // back to 'draft' rather than rejecting the whole submission — status
            // isn't required input, it's a picklist with a sensible default.
            'status'       => in_array($status, self::STATUSES, true) ? $status : self::STATUSES[0],
            'is_zero'      => isset($input['is_zero']) ? 1 : 0,
            'is_recurring' => isset($input['is_recurring']) ? 1 : 0,
            'notes'        => trim((string) ($input['notes'] ?? '')),
        ];
        $errors = [];

        if (!ctype_digit($clean['customer_id']) || self::missing('customers', (int) $clean['customer_id'])) {
            $errors['customer_id'] = terr('inv.err_customer_required');
        }

        $items = [];
        $rawProducts  = (array) ($input['item_product_id'] ?? []);
        $rawQuantities = (array) ($input['item_quantity'] ?? []);
        $rawPrices     = (array) ($input['item_unit_price'] ?? []);

        foreach ($rawProducts as $i => $productId) {
            $productId = trim((string) $productId);
            $quantity  = trim((string) ($rawQuantities[$i] ?? ''));
            $price     = trim((string) ($rawPrices[$i] ?? ''));

            if ($productId === '' && $quantity === '' && $price === '') {
                continue; // the trailing empty row users can always type into
            }

            if (!ctype_digit($productId) || self::missing('products', (int) $productId)) {
                $errors['items_' . $i] = terr('inv.err_product_required');
                continue;
            }

            if (!is_numeric($quantity) || (float) $quantity <= 0) {
                $errors['items_' . $i] = terr('inv.err_quantity_invalid');
                continue;
            }

            if (!is_numeric($price) || (float) $price < 0) {
                $errors['items_' . $i] = terr('prod.err_price');
                continue;
            }

            $items[] = ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $price];
        }

        if ($items === [] && !isset($errors['items_0'])) {
            $errors['items'] = terr('inv.err_items_required');
        }

        $clean['items'] = $items;

        return [$clean, $errors];
    }

    private static function missing(string $table, int $id): bool
    {
        return Db::all("SELECT 1 FROM `$table` WHERE id = ? LIMIT 1", [$id]) === [];
    }
}
