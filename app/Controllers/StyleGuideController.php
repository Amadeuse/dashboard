<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class StyleGuideController extends Controller
{
    public function index(): void
    {
        $this->view('style-guide', [
            'title'  => t('page.style_guide') . ' · ' . app_name(),
            'colors' => [
                ['name' => 'primary',   'hex' => '#6366f1'],
                ['name' => 'secondary', 'hex' => '#6c757d'],
                ['name' => 'success',   'hex' => '#198754'],
                ['name' => 'danger',    'hex' => '#dc3545'],
                ['name' => 'warning',   'hex' => '#ffc107'],
                ['name' => 'info',      'hex' => '#0dcaf0'],
                ['name' => 'dark',      'hex' => '#0f1425'],
                ['name' => 'light',     'hex' => '#f8f9fa'],
            ],
        ]);
    }
}
