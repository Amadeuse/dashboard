<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\Organization;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $ruler = Auth::tenantId();
        $org   = Organization::get($ruler);
        // Whole tenant normally; narrowed to one person when SuperUser is
        // browsing as a specific sub-user rather than the tenant root (4.86).
        $scopeUserIds = Auth::invoiceScopeUserIds();

        $this->view('dashboard', [
            'title'         => t('page.dashboard') . ' · ' . app_name(),
            'notice'        => flash('notice'),
            // "მეილზე გაგზავნა" success bounces here from /invoices (4.64,
            // see InvoiceController::sendEmail()) — same flash keys orders.php/
            // invoices.php already read, just consumed here instead this time.
            // Also this table's own row-action modal now (4.84) — same
            // email_errors/email_old reopen-on-failure orders.php's own uses.
            'emailSent'     => flash('email_sent'),
            'emailFailed'   => flash('email_failed'),
            'emailErrors'   => flash('email_errors') ?? [],
            'emailOld'      => flash('email_old') ?? [],
            'user'          => Auth::user(),
            'org'           => $org,
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'currency'      => (string) $org['currency'],
            'appUrl'        => app_url(),
            'stats'         => Dashboard::stats($ruler, $scopeUserIds),
            'revenue'       => Dashboard::revenueByUser($ruler, $scopeUserIds),
            // 20, not the model's default 6 — now a searchable/paginated
            // ds-table (see dashboard.php), needs enough rows for that to be
            // useful. Still "recent", not the full history — /orders (the
            // card's "ყველა ნახვა" link) is the unlimited version.
            'recent'        => Dashboard::recentInvoices($scopeUserIds, 20),
        ]);
    }
}
