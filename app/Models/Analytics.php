<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * Org-wide analytics for /analytics/overview (AnalyticsController, 4.92 in
 * handoff.md) — a free date-range slice across every customer/product, not
 * one customer's history the way CustomerReport.php is. Same tenant-scoping
 * convention as Dashboard.php/CustomerReport.php: invoices via `created_by
 * IN ($userIds)`, where $userIds is always the caller's Auth::
 * invoiceScopeUserIds() (whole team for the root admin, one person's own
 * for a sub-user or SuperUser browsing as one — 4.86/4.88), never computed
 * in here.
 */
final class Analytics
{
    /** First issue_date in scope, or null with no invoices — the "all time" lower bound. */
    public static function earliestDate(array $userIds): ?string
    {
        if ($userIds === []) {
            return null;
        }
        $ph  = self::placeholders($userIds);
        $min = Db::all("SELECT MIN(issue_date) AS d FROM invoices WHERE created_by IN ($ph)", $userIds)[0]['d'] ?? null;

        return $min !== null ? (string) $min : null;
    }

    /** @return array{count:int, total:float, average:float, finalRate:float} finalRate is 0-100, 0 when there are no invoices at all. */
    public static function summary(array $userIds, string $from, string $to): array
    {
        $ph  = self::placeholders($userIds);
        $row = Db::all(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS total, COALESCE(AVG(total), 0) AS avg,
                    COALESCE(SUM(document_state = 'final'), 0) AS final_cnt
               FROM invoices
              WHERE created_by IN ($ph) AND issue_date BETWEEN ? AND ?",
            [...$userIds, $from, $to]
        )[0];

        $count = (int) $row['cnt'];

        return [
            'count'     => $count,
            'total'     => (float) $row['total'],
            'average'   => (float) $row['avg'],
            'finalRate' => $count > 0 ? (float) $row['final_cnt'] / $count * 100 : 0.0,
        ];
    }

    /**
     * One point per bucket — day / ISO week / month — for EVERY bucket in the
     * range, oldest first, zero where nothing was invoiced (4.123).
     *
     * It used to be sparse (only buckets with invoices), which is what made
     * the chart look different every time: a month with one invoiced day was
     * a single dot, the x-axis jumped over empty days, and its spacing meant
     * nothing. A continuous, evenly divided axis needs the empty buckets in
     * the series, and that is a fact about the range, not the data — so it
     * is built here, where the range is known.
     *
     * Each point carries the bucket's real start and end dates so the view
     * can label it in words ("31 აგვ – 6 სექ") instead of the storage key
     * ('2026-W36'). Keys match MySQL's own DATE_FORMAT for the join: %Y-%m-%d,
     * %x-W%v (ISO year-week, Monday-first — PHP's 'o-\WW'), %Y-%m.
     *
     * Each point also carries the invoice count, so the "how many / how big"
     * chart (4.125) rides on the same buckets and the same query — average
     * is total/count, done in the view, zero where count is zero.
     *
     * @return list<array{key:string, start:string, end:string, total:float, count:int}>
     */
    public static function revenueTrend(array $userIds, string $from, string $to, string $granularity): array
    {
        $format = match ($granularity) {
            'weekly'  => '%x-W%v',
            'monthly' => '%Y-%m',
            default   => '%Y-%m-%d',
        };
        $ph     = self::placeholders($userIds);
        $byKey = [];
        foreach (Db::all(
            "SELECT DATE_FORMAT(issue_date, '$format') AS bucket, SUM(total) AS total, COUNT(*) AS cnt
               FROM invoices
              WHERE created_by IN ($ph) AND issue_date BETWEEN ? AND ?
              GROUP BY bucket",
            [...$userIds, $from, $to]
        ) as $r) {
            $byKey[$r['bucket']] = [round((float) $r['total'], 2), (int) $r['cnt']];
        }

        $out = [];
        foreach (self::buckets($from, $to, $granularity) as [$key, $start, $end]) {
            [$total, $count] = $byKey[$key] ?? [0.0, 0];
            $out[] = ['key' => $key, 'start' => $start, 'end' => $end, 'total' => $total, 'count' => $count];
        }

        return $out;
    }

    /**
     * Every bucket touching [$from, $to]: [key, start, end] triples. A week
     * or month that only partly overlaps the range is still one whole
     * bucket — its label says Mon–Sun, its total counts only days inside
     * the range (the query's BETWEEN does that).
     *
     * @return list<array{0:string,1:string,2:string}>
     */
    private static function buckets(string $from, string $to, string $granularity): array
    {
        $out    = [];
        $cursor = new \DateTimeImmutable($from);
        $last   = new \DateTimeImmutable($to);

        switch ($granularity) {
            case 'monthly':
                $cursor = $cursor->modify('first day of this month');
                while ($cursor <= $last) {
                    $out[] = [$cursor->format('Y-m'), $cursor->format('Y-m-d'), $cursor->format('Y-m-t')];
                    $cursor = $cursor->modify('first day of next month');
                }
                break;

            case 'weekly':
                $cursor = $cursor->modify('monday this week');
                while ($cursor <= $last) {
                    $out[] = [$cursor->format('o-\WW'), $cursor->format('Y-m-d'), $cursor->modify('+6 days')->format('Y-m-d')];
                    $cursor = $cursor->modify('+7 days');
                }
                break;

            default:
                while ($cursor <= $last) {
                    $d     = $cursor->format('Y-m-d');
                    $out[] = [$d, $d, $d];
                    $cursor = $cursor->modify('+1 day');
                }
        }

        return $out;
    }

    /** @return array<int, array{name:string, count:int, total:float}> up to $limit customers, highest total first. */
    public static function topCustomers(array $userIds, string $from, string $to, int $limit = 5): array
    {
        $ph   = self::placeholders($userIds);
        $rows = Db::all(
            "SELECT c.customer_name AS name, COUNT(*) AS cnt, SUM(i.total) AS total
               FROM invoices i
               JOIN customers c ON c.id = i.customer_id
              WHERE i.created_by IN ($ph) AND i.issue_date BETWEEN ? AND ?
              GROUP BY i.customer_id, c.customer_name
              ORDER BY total DESC
              LIMIT " . max(1, $limit),
            [...$userIds, $from, $to]
        );

        return array_map(static fn(array $r): array => [
            'name'  => $r['name'],
            'count' => (int) $r['cnt'],
            'total' => (float) $r['total'],
        ], $rows);
    }

    /** @return array<int, array{name:string, quantity:float, revenue:float}> up to $limit products, highest revenue first — same shape as CustomerReport::topProducts(), org-wide instead of one customer. */
    public static function topProducts(array $userIds, string $from, string $to, int $limit = 5): array
    {
        $ph   = self::placeholders($userIds);
        $rows = Db::all(
            "SELECT p.name, SUM(ii.quantity) AS qty, SUM(ii.line_total) AS revenue
               FROM invoice_items ii
               JOIN invoices i ON i.id = ii.invoice_id
               JOIN products p ON p.id = ii.product_id
              WHERE i.created_by IN ($ph) AND i.issue_date BETWEEN ? AND ?
              GROUP BY ii.product_id, p.name
              ORDER BY revenue DESC
              LIMIT " . max(1, $limit),
            [...$userIds, $from, $to]
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
