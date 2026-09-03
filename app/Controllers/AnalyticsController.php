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
    private const GRANULARITIES = ['daily', 'weekly', 'monthly'];

    public function overview(): void
    {
        $ruler = Auth::tenantId();
        $org   = Organization::get($ruler);

        [$from, $to] = $this->resolveRange();
        $granularity = in_array($_GET['granularity'] ?? '', self::GRANULARITIES, true) ? $_GET['granularity'] : 'daily';

        $userIds = Auth::invoiceScopeUserIds();

        $this->view('analytics-overview', [
            'title'        => t('nav.analytics_overview') . ' · ' . app_name(),
            'currency'     => (string) $org['currency'],
            'from'         => $from,
            'to'           => $to,
            'granularity'  => $granularity,
            'summary'      => Analytics::summary($userIds, $from, $to),
            'trend'        => Analytics::revenueTrend($userIds, $from, $to, $granularity),
            'topCustomers' => Analytics::topCustomers($userIds, $from, $to),
            'topProducts'  => Analytics::topProducts($userIds, $from, $to),
        ]);
    }

    /**
     * ?from=&to= (both plain 'Y-m-d', from the filter form's native
     * <input type="date">) — default to the last 30 days when absent or
     * not a real date, swapped into order when reversed. No further
     * clamping (a range spanning years is a perfectly valid ask here).
     *
     * @return array{0:string,1:string}
     */
    private function resolveRange(): array
    {
        $default = static fn(): array => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];

        $from = (string) ($_GET['from'] ?? '');
        $to   = (string) ($_GET['to'] ?? '');

        if (!self::isValidDate($from) || !self::isValidDate($to)) {
            return $default();
        }

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private static function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
