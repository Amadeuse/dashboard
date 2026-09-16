<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Analytics;
use App\Models\Organization;

/**
 * /analytics/overview — "ანალიტიკა > მიმოხილვა" in the sidebar (menu.json).
 * A free date-range slice across the whole tenant, unlike the dashboard's
 * own chart (always the current calendar year) or CustomerReport.php (one
 * customer's whole history) — see Analytics.php's own docblock for the
 * shared Auth::invoiceScopeUserIds() scoping rule this, they, and the
 * dashboard all follow identically.
 */
final class AnalyticsController extends Controller
{
    public function overview(): void
    {
        $ruler = Auth::tenantId();
        $org   = Organization::get($ruler);

        $userIds = Auth::invoiceScopeUserIds();

        // Same period control /orders has (4.100–4.108): all / month / year /
        // custom range. "All" here has to become a real from–to because the
        // Analytics model's queries take one, so it runs from the scope's
        // earliest invoice to today.
        [$from, $to, $period] = $this->resolveRange($userIds);

        // The trend chart's bucket follows the period rather than being a
        // second control (4.122 — the user's call: with the period fixed to
        // all/month/year/range, choosing day/week/month on top of it stopped
        // meaning anything; "this month by month" is one bar). A month is
        // read by day, a year by month, and a free range by how long it is.
        $granularity = self::granularityFor($period, $from, $to);

        $this->view('analytics-overview', [
            'title'        => t('nav.analytics_overview') . ' · ' . app_name(),
            'currency'     => (string) $org['currency'],
            'from'         => $from,
            'to'           => $to,
            'period'       => $period,                      // '' | 'month' | 'year' | 'range'
            'granularity'  => $granularity,
            'summary'      => Analytics::summary($userIds, $from, $to),
            'trend'        => Analytics::revenueTrend($userIds, $from, $to, $granularity),
            'topCustomers' => Analytics::topCustomers($userIds, $from, $to),
            'topProducts'  => Analytics::topProducts($userIds, $from, $to),
        ]);
    }

    /**
     * Which period the page shows, mirroring InvoiceController::resolveOrdersRange():
     *   ?period=month  → this calendar month
     *   ?period=year   → this calendar year
     *   ?from=&to=     → that range (swapped into order when reversed)
     *   nothing        → everything: the scope's earliest invoice to today
     *
     * Returns [from, to, period] with period one of '' (all), 'month',
     * 'year', 'range' — what the toolbar highlights.
     *
     * @param  list<int> $userIds
     * @return array{0:string,1:string,2:string}
     */
    private function resolveRange(array $userIds): array
    {
        $period = (string) ($_GET['period'] ?? '');
        if ($period === 'month') {
            return [date('Y-m-01'), date('Y-m-t'), 'month'];
        }
        if ($period === 'year') {
            return [date('Y-01-01'), date('Y-12-31'), 'year'];
        }

        $from = (string) ($_GET['from'] ?? '');
        $to   = (string) ($_GET['to'] ?? '');
        if (self::isValidDate($from) && self::isValidDate($to)) {
            return $from <= $to ? [$from, $to, 'range'] : [$to, $from, 'range'];
        }

        return [Analytics::earliestDate($userIds) ?? date('Y-m-d'), date('Y-m-d'), ''];
    }

    /**
     * Day for a month; month for a year; for a free range (or "all"), day up
     * to ~a month, week up to ~half a year, month beyond — the same thresholds
     * most analytics tools auto-pick, so the chart stays readable whether the
     * span is nine days or three years.
     */
    private static function granularityFor(string $period, string $from, string $to): string
    {
        if ($period === 'month') {
            return 'daily';
        }
        if ($period === 'year') {
            return 'monthly';
        }

        $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;

        return $days <= 31 ? 'daily' : ($days <= 182 ? 'weekly' : 'monthly');
    }

    private static function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
