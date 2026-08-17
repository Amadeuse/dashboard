<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * All of this is tenant-scoped (App\Core\Auth::tenantId()) — customers/
 * products via their own `ruler` column, invoices via `created_by IN
 * (User::tenantMemberIds($ruler))` since invoices are scoped per-user (who
 * issued it), not per-tenant directly (see 4.25.7/4.30/4.36 in handoff.md).
 * A sub-user's own invoices count toward their admin's dashboard — that's
 * the whole point of "the team's numbers together".
 */
final class Dashboard
{
    /** @return array<int, array{key:string,value:string,icon:string,tone:string}> */
    public static function stats(int $ruler): array
    {
        $userIds = User::tenantMemberIds($ruler);
        $ph      = self::placeholders($userIds);

        $customers = (int) (Db::all('SELECT COUNT(*) AS c FROM customers WHERE ruler = ?', [$ruler])[0]['c'] ?? 0);
        $products  = (int) (Db::all('SELECT COUNT(*) AS c FROM products WHERE ruler = ?', [$ruler])[0]['c'] ?? 0);
        $invoices  = (int) (Db::all("SELECT COUNT(*) AS c FROM invoices WHERE created_by IN ($ph)", $userIds)[0]['c'] ?? 0);
        $revenue   = (float) (Db::all("SELECT COALESCE(SUM(total), 0) AS s FROM invoices WHERE created_by IN ($ph)", $userIds)[0]['s'] ?? 0);

        return [
            ['key' => 'stat.customers', 'value' => number_format($customers),  'icon' => 'bi-people-fill', 'tone' => 'primary'],
            ['key' => 'stat.products',  'value' => number_format($products),   'icon' => 'bi-box-seam',    'tone' => 'info'],
            ['key' => 'stat.invoices',  'value' => number_format($invoices),   'icon' => 'bi-receipt',     'tone' => 'warning'],
            ['key' => 'stat.revenue',   'value' => number_format($revenue, 2), 'icon' => 'bi-cash-stack',  'tone' => 'success'],
        ];
    }

    /**
     * The current calendar year's (Jan-Dec) invoice revenue, one series per
     * tenant member (the admin + every sub-user) — grouped bars, one colour
     * per person, so the chart shows who's issuing how much side by side,
     * not just a single blended line. Months are raw 'YYYY-MM' keys; the
     * view (not this model, see Dashboard::activity()'s old convention)
     * turns them into localized labels via t('month.N').
     *
     * @return array{months: list<string>, series: list<array{userId:int,label:string,color:string,data:list<float>}>}
     */
    public static function revenueByUser(int $ruler): array
    {
        $members = Db::all(
            'SELECT id, name FROM users WHERE id = ? OR created_by = ? ORDER BY (id != ?), name',
            [$ruler, $ruler, $ruler]
        );
        $memberIds = array_map('intval', array_column($members, 'id'));

        $months = self::currentYearMonths();
        $ph     = self::placeholders($memberIds);
        $rows   = Db::all(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month, created_by, SUM(total) AS total
               FROM invoices
              WHERE created_by IN ($ph) AND YEAR(issue_date) = YEAR(CURDATE())
              GROUP BY month, created_by",
            $memberIds
        );

        $totals = []; // [month][userId] => total
        foreach ($rows as $row) {
            $totals[$row['month']][(int) $row['created_by']] = (float) $row['total'];
        }

        $palette = ['#4f46e5', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7', '#ec4899', '#84cc16'];
        $series  = [];
        foreach ($members as $i => $member) {
            $userId    = (int) $member['id'];
            $series[]  = [
                'userId' => $userId,
                'label'  => $member['name'],
                'color'  => $palette[$i % count($palette)],
                'data'   => array_map(static fn(string $m): float => round($totals[$m][$userId] ?? 0.0, 2), $months),
            ];
        }

        return ['months' => $months, 'series' => $series];
    }

    /** @return array<int, array<string,mixed>> the tenant's most recent invoices (any member), customer/creator names joined in. */
    public static function recentInvoices(int $ruler, int $limit = 6): array
    {
        $userIds = User::tenantMemberIds($ruler);
        $ph      = self::placeholders($userIds);

        return Db::all(
            "SELECT i.*, c.customer_name, u.name AS creator_name
               FROM invoices i
               JOIN customers c ON c.id = i.customer_id
               LEFT JOIN users u ON u.id = i.created_by
              WHERE i.created_by IN ($ph)
              ORDER BY i.id DESC
              LIMIT " . max(1, $limit),
            $userIds
        );
    }

    private static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, max(count($items), 1), '?'));
    }

    /** @return list<string> this calendar year's 12 months as 'YYYY-MM', January through December. */
    private static function currentYearMonths(): array
    {
        $year = date('Y');

        return array_map(static fn(int $m): string => sprintf('%s-%02d', $year, $m), range(1, 12));
    }
}
