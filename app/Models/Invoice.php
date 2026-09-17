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
    public const DOCUMENT_STATES = ['draft', 'final'];

    public const DISCOUNT_TYPES = ['percent', 'amount'];

    /**
     * The invoice's final amount from its line subtotal and discount (4.135).
     * One formula, used by save() when writing `total` and by every view that
     * shows the summary block — so the form's live JS, the preview, the PDF
     * and the stored figure can never disagree by a rounding.
     *
     * Prices are VAT-inclusive, so this is the whole story: the discount
     * comes off the total, and the informational VAT line is derived from
     * whatever is left. Never below zero — an amount larger than the subtotal
     * is clamped, not refused (validate() already rejects it; this is the
     * last line of defence for a stored row edited by hand).
     */
    public static function applyDiscount(float $subtotal, string $type, float $value): float
    {
        $off = $type === 'percent' ? $subtotal * $value / 100 : $value;

        return round(max(0.0, $subtotal - $off), 2);
    }

    /** The subtotal before discount — Σ quantity × price over the given items. */
    public static function subtotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) $item['quantity'] * (float) $item['unit_price'];
        }

        return round($sum, 2);
    }

    /**
     * "{prefix} {issue_date} {0004}" — the one place this format is written,
     * every view calls this instead of re-formatting. The number itself is
     * $row['sequence_number'] (migrations/032) — a per-tenant counter set
     * once at creation (see save()'s $editingId === null branch), not the
     * row's own id (one global AUTO_INCREMENT shared by every tenant in the
     * whole table, meaningless as a "your Nth invoice" count). Falls back to
     * id only for the legacy sliver of rows sequence_number couldn't be
     * backfilled for — an unresolvable tenant (no created_by, predates
     * migrations/021) — same edge case itemsFor()/orders.php already treat
     * as "no better data available", not a regression.
     */
    public static function number(array $row, string $prefix): string
    {
        $n = $row['sequence_number'] ?? $row['id'];

        return sprintf('%s %s %04d', $prefix, $row['issue_date'], (int) $n);
    }

    /**
     * What a brand-new invoice's number *would* be right now — invoices.php's
     * card-header shows this before anything is actually saved, purely a
     * preview. No FOR UPDATE (that's save()'s own, real-insert-only lock) —
     * a display value can tolerate a stale read; the real number, decided at
     * actual save time, is still race-safe on its own.
     *
     * @param list<int> $tenantMemberIds
     */
    public static function previewNextSequenceNumber(array $tenantMemberIds, int $startNumber): int
    {
        if ($tenantMemberIds === []) {
            return $startNumber;
        }

        $ph  = implode(',', array_fill(0, count($tenantMemberIds), '?'));
        $max = Db::all("SELECT MAX(sequence_number) AS m FROM invoices WHERE created_by IN ($ph)", $tenantMemberIds)[0]['m'] ?? null;

        return $max !== null ? max((int) $max + 1, $startNumber) : $startNumber;
    }

    /**
     * Sending an invoice to a customer means it's no longer a work-in-progress
     * draft — InvoiceController::sendEmail() calls this once the email is
     * actually delivered (not on a failed send). A no-op if already final
     * (the WHERE just avoids an unnecessary write, not a guard against
     * anything unsafe).
     */
    public static function markFinal(int $id): void
    {
        Db::conn()
            ->prepare("UPDATE invoices SET document_state = 'final' WHERE id = ? AND document_state = 'draft'")
            ->execute([$id]);
    }

    /**
     * @param list<int>|null $createdByIds when given, only invoices created by
     *   one of these user ids — orders.php's table passes the whole tenant
     *   (User::tenantMemberIds($ruler): the admin + every sub-user), so a
     *   sub-user's invoices show up for the admin too, not just their own
     *   (was a single user id, `created_by = ?`, until 4.36 — a sub-user's
     *   invoices were invisible on /orders to anyone but that sub-user,
     *   while the dashboard already counted them for the whole tenant).
     *   $invoicesByCustomer on /invoices stays unfiltered (null) on purpose:
     *   a customer's full invoice history is relevant there regardless of
     *   who created each one.
     * @param array{0:string,1:string}|null $dateRange when given, only
     *   invoices with issue_date in [from, to] (both 'Y-m-d', inclusive) —
     *   orders.php's own "მიმდინარე თვე"/"მიმდინარე წელი"/"დროის
     *   მონაკვეთი" period filter (4.100), independent of $createdByIds.
     * @return array<int, array<string, mixed>> newest first, with the customer's
     *   name/tax id/email and the creator's name/color (orders.php's table,
     *   and its "მეილზე გაგზავნა" prefill / creator-color dot, 4.87) joined
     *   in. creator_name/creator_color are NULL for invoices predating
     *   created_by (migrations/021).
     */
    public static function all(?array $createdByIds = null, ?array $dateRange = null): array
    {
        $sql = 'SELECT i.*, c.customer_name, c.customer_taxid, c.customer_email, u.name AS creator_name, u.color AS creator_color
                  FROM invoices i
                  JOIN customers c ON c.id = i.customer_id
                  LEFT JOIN users u ON u.id = i.created_by';
        $args  = [];
        $where = [];

        if ($createdByIds !== null) {
            $ph = implode(',', array_fill(0, max(count($createdByIds), 1), '?'));
            $where[] = "i.created_by IN ($ph)";
            $args = array_merge($args, $createdByIds);
        }

        if ($dateRange !== null) {
            $where[] = 'i.issue_date BETWEEN ? AND ?';
            $args = array_merge($args, $dateRange);
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY i.id DESC';

        return Db::all($sql, $args);
    }

    /**
     * One invoice plus the customer fields the print/view page's "Bill To"
     * needs, or null if it doesn't exist. creator_name (the issuing tenant
     * member — pdf/invoice.php's header shows them as the org side's own
     * "საკონტაქტო") is NULL for invoices predating created_by (migrations/021).
     */
    public static function find(int $id): ?array
    {
        $rows = Db::all(
            'SELECT i.*, c.customer_name, c.customer_taxid, c.customer_contact,
                    c.customer_phone, c.customer_email, c.customer_address,
                    u.name AS creator_name
               FROM invoices i
               JOIN customers c ON c.id = i.customer_id
               LEFT JOIN users u ON u.id = i.created_by
              WHERE i.id = ?',
            [$id]
        );

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string,mixed>> one invoice's line items,
     *   product name and unit name joined in. unit_name is NULL for a line
     *   item saved before unit_id existed (migrations/030) — views treat
     *   that the same as "no unit", they don't fall back to the product's
     *   current unit (see save()'s docblock on why this is a snapshot).
     */
    public static function itemsFor(int $id): array
    {
        return Db::all(
            'SELECT ii.*, p.name AS product_name, un.name AS unit_name
               FROM invoice_items ii
               JOIN products p ON p.id = ii.product_id
               LEFT JOIN units un ON un.id = ii.unit_id
              WHERE ii.invoice_id = ?
              ORDER BY ii.id',
            [$id]
        );
    }

    /**
     * @param array{customer_id:string,document_state:string,show_vat:int,is_recurring:int,notes:string,items:list<array{product_id:string,unit_id:string,quantity:string,unit_price:string}>} $clean
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
     *
     * A random view_token is generated on every INSERT (never on UPDATE, never
     * regenerated) — InvoiceController::show()'s no-login "share this invoice
     * with the customer" path checks it, see handoff.md.
     *
     * @param list<int>|null $tenantMemberIds only needed when $editingId is
     *   null — see sequence_number below. Ignored on an edit (an existing
     *   invoice keeps whatever number it was given at creation, forever).
     * @param int|null $startNumber organization.invoice_start_number, same
     *   "only for a new row" rule as $tenantMemberIds.
     *
     * sequence_number (migrations/032) is this tenant's own "1, 2, 3, ..."
     * counter — number()'s actual display value, not the row's shared-table
     * id. Computed here, inside the transaction, as `MAX(...) FOR UPDATE`:
     * two concurrent "new invoice" submits from the same tenant lock against
     * each other on that SELECT, so neither can read a stale max and hand
     * out a duplicate — the same race protection the edit branch's own
     * FOR UPDATE gives $expectedUpdatedAt, just for a different column.
     * Never below the tenant's current max + 1, even if $startNumber was
     * just lowered — a business/accounting number must never repeat.
     */
    public static function save(
        array $clean,
        ?int $editingId,
        ?string $expectedUpdatedAt = null,
        ?int $createdBy = null,
        ?array $tenantMemberIds = null,
        ?int $startNumber = null,
    ): ?int {
        $total = self::applyDiscount(self::subtotal($clean['items']), $clean['discount_type'], (float) $clean['discount_value']);

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

            $conn->prepare('UPDATE invoices SET customer_id = ?, total = ?, discount_type = ?, discount_value = ?, document_state = ?, show_vat = ?, is_recurring = ?, notes = ? WHERE id = ?')
                ->execute([
                    (int) $clean['customer_id'], $total, $clean['discount_type'], $clean['discount_value'], $clean['document_state'],
                    $clean['show_vat'], $clean['is_recurring'], $clean['notes'], $editingId,
                ]);
            $conn->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$editingId]);
            $invoiceId = $editingId;
        } else {
            $memberIds = $tenantMemberIds ?: [$createdBy];
            $ph  = implode(',', array_fill(0, count($memberIds), '?'));
            $max = $conn->prepare("SELECT MAX(sequence_number) AS m FROM invoices WHERE created_by IN ($ph) FOR UPDATE");
            $max->execute($memberIds);
            $maxSeq = $max->fetchColumn();
            $sequenceNumber = $maxSeq !== false && $maxSeq !== null ? max((int) $maxSeq + 1, (int) $startNumber) : (int) $startNumber;

            $conn->prepare(
                'INSERT INTO invoices (sequence_number, customer_id, issue_date, total, discount_type, discount_value, document_state, show_vat, is_recurring, notes, created_by, view_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $sequenceNumber, (int) $clean['customer_id'], date('Y-m-d'), $total, $clean['discount_type'], $clean['discount_value'],
                $clean['document_state'], $clean['show_vat'], $clean['is_recurring'], $clean['notes'], $createdBy,
                bin2hex(random_bytes(32)),
            ]);
            $invoiceId = (int) $conn->lastInsertId();
        }

        $insertItem = $conn->prepare(
            'INSERT INTO invoice_items (invoice_id, product_id, unit_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($clean['items'] as $item) {
            $lineTotal = (float) $item['quantity'] * (float) $item['unit_price'];
            // The normal form submission always has a real unit_id here —
            // Invoice::validate() already rejects a missing/invalid one.
            // NULL/'' only ever reaches this point via duplicate() copying
            // a legacy pre-migrations/030 item that never had one — casting
            // that to (int) 0 (not a real unit) used to fail this column's
            // own FK constraint outright, so it's preserved as a genuine
            // NULL instead, exactly like the source row it was copied from.
            $unitId = $item['unit_id'] !== null && $item['unit_id'] !== '' ? (int) $item['unit_id'] : null;
            $insertItem->execute([
                $invoiceId, (int) $item['product_id'], $unitId, $item['quantity'], $item['unit_price'], $lineTotal,
            ]);
        }

        $conn->commit();

        return $invoiceId;
    }

    /**
     * @return array{0: array<string,mixed>, 1: array<string,string>} [clean, errors]
     *   clean = ['customer_id','document_state','show_vat','is_recurring','notes',
     *            'items' => list of ['product_id','unit_id','quantity','unit_price']]
     *   notes is free text, no validation — an empty textarea just stores ''.
     *
     * $ruler (4.134): every referenced row — the customer, each line's product
     * and unit — has to be this tenant's. Existence alone was checked before,
     * so a posted customer_id belonging to another tenant was accepted, and
     * the invoice then JOINed and displayed that customer's name, tax id,
     * phone, email and address on every page and PDF: a cross-tenant read
     * through a foreign key. Units are the one table with shared rows
     * (ruler NULL, migrations/039), so those pass for everyone.
     */
    public static function validate(array $input, int $ruler): array
    {
        $documentState = (string) ($input['document_state'] ?? '');
        $documentState = in_array($documentState, self::DOCUMENT_STATES, true) ? $documentState : self::DOCUMENT_STATES[0];

        $clean = [
            'customer_id'    => trim((string) ($input['customer_id'] ?? '')),
            'document_state' => $documentState,
            // A checkbox: absent when unchecked. The form renders it checked by
            // default, so a fresh invoice shows VAT unless the user turns it off.
            'show_vat'       => isset($input['show_vat']) ? 1 : 0,
            'is_recurring'   => isset($input['is_recurring']) ? 1 : 0,
            'notes'          => trim((string) ($input['notes'] ?? '')),
            'discount_type'  => in_array($input['discount_type'] ?? '', self::DISCOUNT_TYPES, true) ? $input['discount_type'] : 'percent',
            'discount_value' => trim((string) ($input['discount_value'] ?? '')),
        ];
        $errors = [];

        // Empty means none. Otherwise a non-negative number; a percent no more
        // than 100. An amount is checked against the subtotal once the items
        // are known, below.
        if ($clean['discount_value'] === '') {
            $clean['discount_value'] = '0';
        } elseif (!is_numeric($clean['discount_value']) || (float) $clean['discount_value'] < 0
            || ($clean['discount_type'] === 'percent' && (float) $clean['discount_value'] > 100)) {
            $errors['discount_value'] = terr('inv.err_discount_invalid');
        }

        if (!ctype_digit($clean['customer_id']) || self::missing('customers', (int) $clean['customer_id'], $ruler)) {
            $errors['customer_id'] = terr('inv.err_customer_required');
        }

        $items = [];
        $rawProducts   = (array) ($input['item_product_id'] ?? []);
        $rawUnits      = (array) ($input['item_unit_id'] ?? []);
        $rawQuantities = (array) ($input['item_quantity'] ?? []);
        $rawPrices     = (array) ($input['item_unit_price'] ?? []);

        foreach ($rawProducts as $i => $productId) {
            $productId = trim((string) $productId);
            $unitId    = trim((string) ($rawUnits[$i] ?? ''));
            $quantity  = trim((string) ($rawQuantities[$i] ?? ''));
            $price     = trim((string) ($rawPrices[$i] ?? ''));

            if ($productId === '' && $quantity === '' && $price === '') {
                continue; // the trailing empty row users can always type into
            }

            if (!ctype_digit($productId) || self::missing('products', (int) $productId, $ruler)) {
                $errors['items_' . $i] = terr('inv.err_product_required');
                continue;
            }

            if (!ctype_digit($unitId) || self::missing('units', (int) $unitId, $ruler, sharedAllowed: true)) {
                $errors['items_' . $i] = terr('prod.err_unit_required');
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

            $items[] = ['product_id' => $productId, 'unit_id' => $unitId, 'quantity' => $quantity, 'unit_price' => $price];
        }

        if ($items === [] && !isset($errors['items_0'])) {
            $errors['items'] = terr('inv.err_items_required');
        }

        if ($clean['discount_type'] === 'amount' && !isset($errors['discount_value'])
            && (float) $clean['discount_value'] > self::subtotal($items)) {
            $errors['discount_value'] = terr('inv.err_discount_exceeds');
        }

        $clean['items'] = $items;

        return [$clean, $errors];
    }

    /**
     * True when no row with this id belongs to the tenant. $table is always a
     * literal at the call sites above, never input. $sharedAllowed is for
     * units, whose NULL-ruler rows are everyone's.
     */
    private static function missing(string $table, int $id, int $ruler, bool $sharedAllowed = false): bool
    {
        $where = $sharedAllowed ? '(ruler IS NULL OR ruler = ?)' : 'ruler = ?';

        return Db::all("SELECT 1 FROM `$table` WHERE id = ? AND $where LIMIT 1", [$id, $ruler]) === [];
    }
}
