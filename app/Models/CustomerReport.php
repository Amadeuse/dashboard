<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * One customer's whole invoice history, sliced a few ways for
 * customers.php's "რეპორტი" link (CustomerController::report(), 4.69 in
 * handoff.md). Same tenant-scoping convention as Dashboard.php — invoices
 * via `created_by IN (tenantMemberIds)`, not the customer's own `ruler`
 * directly, since a customer can (rarely, but legally) be invoiced by more
 * than one tenant member.
 */
final class CustomerReport
{
    /** @return array<int, array<string,mixed>> this customer's invoices, newest number first, creator name joined in. */
    public static function invoices(int $customerId, array $tenantMemberIds): array
    {
        $ph = self::placeholders($tenantMemberIds);

        return Db::all(
            "SELECT i.*, u.name AS creator_name
               FROM invoices i
               LEFT JOIN users u ON u.id = i.created_by
              WHERE i.customer_id = ? AND i.created_by IN ($ph)
              ORDER BY i.sequence_number DESC, i.id DESC",
            array_merge([$customerId], $tenantMemberIds)
        );
    }

    /** @return array{count:int, total:float, average:float, firstDate:?string, lastDate:?string} headline numbers for the top of the report. */
    public static function summary(int $customerId, array $tenantMemberIds): array
    {
        $ph  = self::placeholders($tenantMemberIds);
        $row = Db::all(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total, COALESCE(AVG(total), 0) AS avg,
                    MIN(issue_date) AS first_date, MAX(issue_date) AS last_date
               FROM invoices
              WHERE customer_id = ? AND created_by IN ($ph)",
            array_merge([$customerId], $tenantMemberIds)
        )[0];

        return [
            'count'     => (int) $row['cnt'],
            'total'     => (float) $row['total'],
            'average'   => (float) $row['avg'],
            'firstDate' => $row['first_date'],
            'lastDate'  => $row['last_date'],
        ];
    }

    /** @return array<string, array{count:int, total:float}> document_state => totals — 'draft'/'final' always both present, 0 when this customer has none of that state. */
    public static function statusTotals(int $customerId, array $tenantMemberIds): array
    {
        $ph   = self::placeholders($tenantMemberIds);
        $rows = Db::all(
            "SELECT document_state, COUNT(*) AS cnt, SUM(total) AS total
               FROM invoices
              WHERE customer_id = ? AND created_by IN ($ph)
              GROUP BY document_state",
            array_merge([$customerId], $tenantMemberIds)
        );

        $out = array_fill_keys(Invoice::DOCUMENT_STATES, ['count' => 0, 'total' => 0.0]);
        foreach ($rows as $row) {
            $out[$row['document_state']] = ['count' => (int) $row['cnt'], 'total' => (float) $row['total']];
        }

        return $out;
    }

    /**
     * Invoiced total per calendar month this customer has at least one
     * invoice in, one series per document_state (draft/final) — same
     * {months, series} shape as Dashboard::revenueByUser() (grouped bars,
     * one colour per series, there per tenant member, here per status), so
     * the view's chart code is a straight copy of that one. Whole
     * relationship, not just the current year (see revenueByUser()'s own
     * docblock for why that one *is* year-scoped — a quick dashboard
     * glance vs. this report's full history). Months sparse on purpose: no
     * zero-padding for months with nothing in them.
     *
     * @return array{months: list<string>, series: list<array{status:string,label:string,color:string,data:list<float>}>} months as raw 'YYYY-MM', oldest first
     */
    public static function monthlyTotals(int $customerId, array $tenantMemberIds): array
    {
        $ph   = self::placeholders($tenantMemberIds);
        $rows = Db::all(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month, document_state, SUM(total) AS total
               FROM invoices
              WHERE customer_id = ? AND created_by IN ($ph)
              GROUP BY month, document_state
              ORDER BY month ASC",
            array_merge([$customerId], $tenantMemberIds)
        );

        $months = [];
        $totals = []; // [month][status] => total
        foreach ($rows as $row) {
            $months[$row['month']] = true;
            $totals[$row['month']][$row['document_state']] = (float) $row['total'];
        }
        $months = array_keys($months);

        $colors = ['draft' => '#94a3b8', 'final' => '#4f46e5'];
        $series = [];
        foreach (Invoice::DOCUMENT_STATES as $status) {
            $series[] = [
                'status' => $status,
                'label'  => t('inv.status_' . $status),
                'color'  => $colors[$status],
                'data'   => array_map(static fn(string $m): float => round($totals[$m][$status] ?? 0.0, 2), $months),
            ];
        }

        return ['months' => $months, 'series' => $series];
    }

    /** @return array<int, array{name:string, quantity:float, revenue:float}> up to $limit products, highest revenue first. */
    public static function topProducts(int $customerId, array $tenantMemberIds, int $limit = 5): array
    {
        $ph   = self::placeholders($tenantMemberIds);
        $rows = Db::all(
            "SELECT p.name, SUM(ii.quantity) AS qty, SUM(ii.line_total) AS revenue
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
               JOIN products p ON p.id = ii.product_id
              WHERE i.customer_id = ? AND i.created_by IN ($ph)
              GROUP BY ii.product_id, p.name
              ORDER BY revenue DESC
              LIMIT " . max(1, $limit),
            array_merge([$customerId], $tenantMemberIds)
        );

        return array_map(static fn(array $r): array => [
            'name'     => $r['name'],
            'quantity' => (float) $r['qty'],
            'revenue'  => (float) $r['revenue'],
        ], $rows);
    }

    private static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, max(count($items), 1), '?'));
    }
}
