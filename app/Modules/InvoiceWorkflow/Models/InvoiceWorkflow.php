<?php

declare(strict_types=1);

namespace App\Modules\InvoiceWorkflow\Models;

use App\Core\Db;

/**
 * `invoice_workflow` — 1:1 with `invoices` (invoice_id is both PK and FK,
 * see migrations/001_create_invoice_workflow.sql). No row means the invoice
 * has never been touched by this module — reads fall back to the same
 * defaults a fresh row would have (unpaid, 0.00, not cancelled), so callers
 * never need to special-case "row missing" themselves.
 */
final class InvoiceWorkflow
{
    private const DEFAULT = ['payment_state' => 'unpaid', 'paid_amount' => '0.00', 'cancelled_at' => null];

    /** @param list<int> $invoiceIds @return array<int, array{payment_state:string,paid_amount:string,cancelled_at:?string}> keyed by invoice_id — every requested id present, missing rows filled with the default */
    public static function forMany(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $ph = implode(',', array_fill(0, count($invoiceIds), '?'));
        $rows = Db::all("SELECT * FROM invoice_workflow WHERE invoice_id IN ($ph)", $invoiceIds);

        $byId = array_fill_keys($invoiceIds, self::DEFAULT);
        foreach ($rows as $row) {
            $byId[(int) $row['invoice_id']] = $row;
        }

        return $byId;
    }

    /** @return array{payment_state:string,paid_amount:string,cancelled_at:?string} */
    public static function for(int $invoiceId): array
    {
        $rows = Db::all('SELECT * FROM invoice_workflow WHERE invoice_id = ?', [$invoiceId]);

        return $rows[0] ?? self::DEFAULT;
    }

    public static function setPayment(int $invoiceId, string $paymentState, string $paidAmount): void
    {
        Db::conn()->prepare(
            'INSERT INTO invoice_workflow (invoice_id, payment_state, paid_amount)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE payment_state = VALUES(payment_state), paid_amount = VALUES(paid_amount)'
        )->execute([$invoiceId, $paymentState, $paidAmount]);
    }

    public static function cancel(int $invoiceId): void
    {
        Db::conn()->prepare(
            'INSERT INTO invoice_workflow (invoice_id, cancelled_at) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE cancelled_at = NOW()'
        )->execute([$invoiceId]);
    }

    public static function uncancel(int $invoiceId): void
    {
        Db::conn()->prepare(
            'INSERT INTO invoice_workflow (invoice_id, cancelled_at) VALUES (?, NULL)
             ON DUPLICATE KEY UPDATE cancelled_at = NULL'
        )->execute([$invoiceId]);
    }

    /** @return array{0: array{payment_state:string,paid_amount:string}, 1: array<string,string>} [clean input, errors] */
    public static function validate(array $input): array
    {
        $clean = [
            'payment_state' => trim((string) ($input['payment_state'] ?? '')),
            'paid_amount'   => trim((string) ($input['paid_amount'] ?? '0')),
        ];

        $errors = [];

        if (!in_array($clean['payment_state'], ['unpaid', 'partial', 'paid'], true)) {
            $errors['payment_state'] = terr('workflow.err_payment_state');
        }

        if (!is_numeric($clean['paid_amount']) || (float) $clean['paid_amount'] < 0) {
            $errors['paid_amount'] = terr('workflow.err_paid_amount');
        }

        return [$clean, $errors];
    }

    /** Is $invoiceId owned by one of $memberIds (Auth::tenantId()'s whole team, see User::tenantMemberIds())? */
    public static function invoiceOwnedBy(int $invoiceId, array $memberIds): bool
    {
        if ($memberIds === []) {
            return false;
        }

        $ph = implode(',', array_fill(0, count($memberIds), '?'));

        return Db::all(
            "SELECT 1 FROM invoices WHERE id = ? AND created_by IN ($ph)",
            [$invoiceId, ...$memberIds]
        ) !== [];
    }
}
