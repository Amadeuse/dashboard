<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard', [
            'title'    => t('page.dashboard') . ' · ' . app_name(),
            'stats'    => Dashboard::stats(),
            'revenue'  => Dashboard::revenueSeries(),
            'traffic'  => Dashboard::traffic(),
            'orders'   => Dashboard::orders(),
            'activity' => Dashboard::activity(),
            'goal'     => Dashboard::goal(),
        ]);
    }
}
