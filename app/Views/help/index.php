<?php
/**
 * Help — the whole system, as a list of what it does and where (4.130).
 *
 * One section per sidebar area, one row per feature: name, what it is for,
 * and a link straight to it. Data-driven from the array below so adding a
 * feature is one entry, not a block of markup — and the SuperUser section
 * follows the same role gate the sidebar uses, so a tenant admin never sees
 * links they can't open.
 *
 * Deliberately a flat list, not a manual: the pages explain themselves once
 * you are on them; what a person needs from a help index is "does the system
 * do X, and where". The module author's guide stays its own page.
 */
$isSuper = (\App\Core\Auth::user()['role'] ?? null) === 'superadmin';

$sections = [
    [
        'title' => 'help.sec_work',
        'icon'  => 'bi-speedometer2',
        'items' => [
            ['bi-grid-1x2',      'help.f_dashboard',   '/'],
            ['bi-graph-up',      'help.f_analytics',   '/analytics/overview'],
        ],
    ],
    [
        'title' => 'help.sec_invoices',
        'icon'  => 'bi-receipt',
        'items' => [
            ['bi-file-earmark-plus', 'help.f_invoice_new',   '/invoices'],
            ['bi-list-ul',           'help.f_orders',        '/orders'],
            ['bi-funnel',            'help.f_orders_filter', '/orders'],
            ['bi-file-earmark-pdf',  'help.f_pdf',           '/orders'],
            ['bi-eye',               'help.f_preview',       '/orders'],
            ['bi-envelope',          'help.f_email',         '/orders'],
            ['bi-link-45deg',        'help.f_share',         '/orders'],
            ['bi-files',             'help.f_duplicate',     '/orders'],
        ],
    ],
    [
        'title' => 'help.sec_data',
        'icon'  => 'bi-database',
        'items' => [
            ['bi-people',      'help.f_customers',       '/customers'],
            ['bi-file-text',   'help.f_customer_report', '/customers'],
            ['bi-box-seam',    'help.f_products',        '/products'],
        ],
    ],
    [
        'title' => 'help.sec_settings',
        'icon'  => 'bi-gear',
        'items' => [
            ['bi-building',     'help.f_organization', '/settings/organization'],
            ['bi-person-badge', 'help.f_users',        '/settings/users'],
            ['bi-puzzle',       'help.f_modules',      '/settings/modules'],
            ['bi-person-circle','help.f_profile',      '/profile'],
            ['bi-sliders',      'help.f_profile_settings', '/profile/settings'],
            ['bi-palette',      'help.f_style_guide',  '/style-guide'],
        ],
    ],
    [
        'title' => 'help.sec_account',
        'icon'  => 'bi-shield-lock',
        'items' => [
            ['bi-box-arrow-in-right', 'help.f_login',    '/login'],
            ['bi-phone',              'help.f_otp',      '/login'],
            ['bi-google',             'help.f_google',   '/login'],
            ['bi-key',                'help.f_password', '/forgot-password'],
        ],
    ],
];

if ($isSuper) {
    $sections[] = [
        'title' => 'help.sec_superuser',
        'icon'  => 'bi-person-gear',
        'items' => [
            ['bi-people-fill',   'help.f_su_tenants',  '/superuser'],
            ['bi-clock-history', 'help.f_su_activity', '/superuser/activity'],
        ],
    ];
}

$sections[] = [
    'title' => 'help.sec_dev',
    'icon'  => 'bi-code-square',
    'items' => [
        ['bi-book', 'help.f_module_guide', '/help/modules'],
    ],
];
?>

<div class="mb-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-1">
      <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><?= e(app_name()) ?></a></li>
      <li class="breadcrumb-item active"><?= t('page.help') ?></li>
    </ol>
  </nav>
  <h1 class="h4 fw-bold mb-1"><?= t('page.help') ?></h1>
  <p class="text-secondary mb-0"><?= t('help.lead') ?></p>
</div>

<div class="row g-3">
  <?php foreach ($sections as $section): ?>
    <div class="col-md-6">
      <div class="card ds-card h-100">
        <div class="card-header">
          <h2 class="h6 fw-bold mb-0"><i class="bi <?= e($section['icon']) ?> me-2 text-secondary"></i><?= t($section['title']) ?></h2>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <?php foreach ($section['items'] as [$icon, $key, $url]): ?>
              <li class="list-group-item d-flex align-items-start gap-3 py-2">
                <i class="bi <?= e($icon) ?> text-primary mt-1 flex-shrink-0"></i>
                <div class="flex-grow-1 min-w-0">
                  <a href="<?= e($url) ?>" class="fw-semibold text-decoration-none stretched-link"><?= t($key) ?></a>
                  <div class="text-secondary small"><?= t($key . '_d') ?></div>
                </div>
                <i class="bi bi-chevron-right text-secondary small mt-1"></i>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
