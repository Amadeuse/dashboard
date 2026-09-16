<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow\Models;

use App\Core\ModuleDb;

/**
 * Payment/cancellation state for an invoice, independent of the core
 * invoices.document_state field.
 *
 * All data access goes through ModuleDb, never App\Core\Db — rule 3 of the
 * module concept, and the harness fails a module that references Db
 * directly. select() may read any table (a module can report across
 * invoices/customers freely); execute() is accepted only for
 * `invoice_workflow`, the one table this module declares in uninstall.sql.
 * A typo that pointed a write at `invoices` would throw before reaching
 * MySQL.
 */
final class InvoiceWorkflow
{
    public const STATES = ['unpaid', 'partial', 'paid'];

    private static function db(): ModuleDb
    {
        return ModuleDb::for('InvoiceWorkflow');
    }

    public static function for(int $invoiceId): array
    {
        $row = self::db()->one('SELECT * FROM invoice_workflow WHERE invoice_id = ?', [$invoiceId]);

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
        foreach (self::db()->select("SELECT * FROM invoice_workflow WHERE invoice_id IN ($placeholders)", $ids) as $row) {
            $out[(int) $row['invoice_id']] = $row;
        }

        return $out;
    }

    public static function setPayment(int $invoiceId, string $state, float $paidAmount): void
    {
        if (!in_array($state, self::STATES, true)) {
            return;
        }

        self::db()->execute(
            'INSERT INTO invoice_workflow (invoice_id, payment_state, paid_amount) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE payment_state = VALUES(payment_state), paid_amount = VALUES(paid_amount)',
            [$invoiceId, $state, max(0, $paidAmount)]
        );
    }

    public static function setCancelled(int $invoiceId, bool $cancelled): void
    {
        self::db()->execute(
            'INSERT INTO invoice_workflow (invoice_id, cancelled_at) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE cancelled_at = VALUES(cancelled_at)',
            [$invoiceId, $cancelled ? date('Y-m-d H:i:s') : null]
        );
    }
}
