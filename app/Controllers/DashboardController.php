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

        $this->view('dashboard', [
            'title'         => t('page.dashboard') . ' · ' . app_name(),
            'notice'        => flash('notice'),
            'user'          => Auth::user(),
            'invoicePrefix' => (string) ($org['invoice_prefix'] ?? '') ?: 'INV',
            'currency'      => (string) $org['currency'],
            'stats'         => Dashboard::stats($ruler),
            'revenue'       => Dashboard::revenueByUser($ruler),
            // 20, not the model's default 6 — now a searchable/paginated
            // ds-table (see dashboard.php), needs enough rows for that to be
            // useful. Still "recent", not the full history — /orders (the
            // card's "ყველა ნახვა" link) is the unlimited version.
            'recent'        => Dashboard::recentInvoices($ruler, 20),
        ]);
    }
}
