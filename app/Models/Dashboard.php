<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

/**
 * customers/products are tenant-scoped via their own `ruler` column
 * (App\Core\Auth::tenantId()). invoices are scoped per-user (who issued it),
 * not per-tenant directly (see 4.25.7/4.30/4.36 in handoff.md) — the actual
 * `$userIds` each method below receives is Auth::invoiceScopeUserIds(): the
 * whole team when the ROOT admin is looking, but just one person's own
 * invoices for a logged-in sub-user (4.88) or SuperUser browsing as one
 * (4.86) — see that method's docblock for the full rule.
 */
final class Dashboard
{
    /**
     * $userIds is Auth::invoiceScopeUserIds() — the whole tenant for the
     * root admin, just one person's own invoices for a logged-in sub-user
     * or SuperUser browsing as one (see that method's docblock, 4.86/4.88).
     * customers/products stay ruler-scoped regardless (shared org data, not
     * per-individual).
     *
     * @param list<int> $userIds
     * @return array<int, array{key:string,value:string,icon:string,tone:string}>
     */
    public static function stats(int $ruler, array $userIds): array
    {
        $ph = self::placeholders($userIds);

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
     * $userIds (Auth::invoiceScopeUserIds(), see stats()'s own docblock)
     * picks which members get a series at all — the whole team for the root
     * admin, just one bar for a logged-in sub-user or SuperUser browsing as
     * one (4.86/4.88), instead of the whole team's.
     *
     * @param list<int> $userIds
     * @return array{months: list<string>, series: list<array{userId:int,label:string,color:string,data:list<float>}>}
     */
    public static function revenueByUser(int $ruler, array $userIds): array
    {
        $ph      = self::placeholders($userIds);
        $members = Db::all(
            "SELECT id, name, color FROM users WHERE id IN ($ph) ORDER BY (id != ?), name",
            [...$userIds, $ruler]
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

        $series = [];
        foreach ($members as $member) {
            $userId   = (int) $member['id'];
            $series[] = [
                'userId' => $userId,
                'label'  => $member['name'],
                // Each person's own chosen color (User::PALETTE only supplies
                // the registration form's *default* value, see 4.87) — '??' is
                // just a defensive fallback for a NULL migrations/035 somehow
                // missed, never expected to actually trigger post-backfill.
                'color'  => $member['color'] ?? '#94a3b8',
                'data'   => array_map(static fn(string $m): float => round($totals[$m][$userId] ?? 0.0, 2), $months),
            ];
        }

        return ['months' => $months, 'series' => $series];
    }

    /**
     * $userIds is the same Auth::invoiceScopeUserIds() scope stats()/
     * revenueByUser() use (4.86/4.88) — the whole tenant for the root admin,
     * one person's own invoices for a logged-in sub-user or SuperUser
     * browsing as one.
     *
     * @param list<int> $userIds
     * @return array<int, array<string,mixed>> newest first, customer name/email
     *   and creator name/color joined in — customer_email is dashboard.php's
     *   own row-action "მეილზე გაგზავნა" prefill (4.84 in handoff.md);
     *   creator_color is the "შეკვეთის მიმღები" column's dot (4.87).
     */
    public static function recentInvoices(array $userIds, int $limit = 6): array
    {
        $ph = self::placeholders($userIds);

        return Db::all(
            "SELECT i.*, c.customer_name, c.customer_email, u.name AS creator_name, u.color AS creator_color
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
