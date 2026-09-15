<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow\Models;

use App\Core\Db;

/**
 * Payment/cancellation state for an invoice, independent of the core
 * invoices.document_state field.
 *
 * A module may use core's own Db/Auth — it is trusted code, not sandboxed
 * (see /help/modules). What it may not do is reach into core *tables* it
 * doesn't own: this one owns `invoice_workflow` and nothing else, joining to
 * `invoices` only through the foreign key its migration declares.
 */
final class InvoiceWorkflow
{
    public const STATES = ['unpaid', 'partial', 'paid'];

    public static function for(int $invoiceId): array
    {
        $row = Db::all('SELECT * FROM invoice_workflow WHERE invoice_id = ?', [$invoiceId])[0] ?? null;

        return $row ?? [
            'invoice_id'    => $invoiceId,
            'payment_state' => 'unpaid',
            'paid_amount'   => '0.00',
            'cancelled_at'  => null,
        ];
    }

    /**
     * One query for a whole page of invoices, keyed by invoice id — this is
     * what the invoice.list.data hook exists for. Doing it per row would put
     * a query behind every line of /orders.
     *
     * @param  list<int> $invoiceIds
     * @return array<int, array<string, mixed>>
     */
    public static function forMany(array $invoiceIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $invoiceIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach (Db::all("SELECT * FROM invoice_workflow WHERE invoice_id IN ($placeholders)", $ids) as $row) {
            $out[(int) $row['invoice_id']] = $row;
        }

        return $out;
    }

    public static function setPayment(int $invoiceId, string $state, float $paidAmount): void
    {
        if (!in_array($state, self::STATES, true)) {
            return;
        }

        Db::conn()->prepare(
            'INSERT INTO invoice_workflow (invoice_id, payment_state, paid_amount) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE payment_state = VALUES(payment_state), paid_amount = VALUES(paid_amount)'
        )->execute([$invoiceId, $state, max(0, $paidAmount)]);
    }

    public static function setCancelled(int $invoiceId, bool $cancelled): void
    {
        Db::conn()->prepare(
            'INSERT INTO invoice_workflow (invoice_id, cancelled_at) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE cancelled_at = VALUES(cancelled_at)'
        )->execute([$invoiceId, $cancelled ? date('Y-m-d H:i:s') : null]);
    }
}
