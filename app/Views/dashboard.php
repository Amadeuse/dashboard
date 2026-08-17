<?php
/**
 * @var array  $user    Auth::user() — for the greeting
 * @var array  $stats   Dashboard::stats() — 4 cards: customers/products/invoices/revenue
 * @var array  $revenue Dashboard::revenueByUser() — {months: ['2026-03', ...], series: [{userId,label,color,data}]}
 * @var array  $recent  Dashboard::recentInvoices() — last few invoices, any tenant member
 * @var string $invoicePrefix organization.invoice_prefix (or "INV"), for Invoice::number()
 * @var string $currency organization.currency ('GEL' or 'USD')
 *
 * Real, tenant-scoped data (App\Core\Auth::tenantId()) — see handoff.md 4.35.
 * Replaces the earlier hardcoded sample dashboard (fake traffic sources,
 * fake team activity feed, fake monthly goal — none of those concepts exist
 * anywhere else in this app, so they were removed rather than wired to
 * something real that doesn't exist).
 */
$statusBadgeClass = [
    'draft' => 'bg-secondary-subtle text-secondary-emphasis',
    'final' => 'bg-info-subtle text-info-emphasis',
    'due'   => 'bg-warning-subtle text-warning-emphasis',
    'paid'  => 'bg-success-subtle text-success-emphasis',
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.dashboard') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= t('dash.greeting', e($user['name'] ?? '')) ?> 👋</h1>
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i> <?= t('dash.export') ?></button>
    <button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> <?= t('dash.new_report') ?></button>
  </div>
</div>

<?php if ($notice !== null): ?>
  <div class="alert alert-warning fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= e($notice) ?>
  </div>
<?php endif; ?>

<!-- Stat cards -->
<div class="row g-3 mb-3">
  <?php foreach ($stats as $s):
    $toneClass = $s['tone'] === 'primary'
      ? 'bg-primary-subtle text-primary'
      : "bg-{$s['tone']}-subtle text-{$s['tone']}-emphasis";
  ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card ds-card h-100">
      <div class="card-body d-flex gap-3">
        <div class="ds-icon-tile <?= $toneClass ?>"><i class="bi <?= $s['icon'] ?>"></i></div>
        <div>
          <div class="text-secondary small"><?= t($s['key']) ?></div>
          <div class="h4 fw-bold mb-0"><?= $s['value'] ?><?= $s['key'] === 'stat.revenue' ? ' ' . e(currency_symbol($currency)) : '' ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Revenue, per team member -->
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('chart.revenue') ?></h2>
        <canvas id="revenueChart" height="90"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Recent invoices -->
<div class="row g-3">
  <div class="col-12">
    <div class="card ds-card h-100">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h2 class="h6 fw-bold mb-0"><?= t('orders.title') ?></h2>
        <a href="/orders" class="small text-decoration-none"><?= t('orders.view_all') ?></a>
      </div>
      <?php if ($recent === []): ?>
        <div class="card-body text-center text-secondary py-5">
          <i class="bi bi-receipt d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
          <?= t('inv.empty') ?>
        </div>
      <?php else: ?>
      <div class="card-body">
      <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary">
              <th><?= t('inv.number') ?></th>
              <th><?= t('orders.customer') ?></th>
              <th><?= t('inv.creator') ?></th>
              <th><?= t('orders.date') ?></th>
              <th><?= t('orders.amount') ?> (<?= e(currency_symbol($currency)) ?>)</th>
              <th><?= t('orders.status') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $inv): ?>
            <tr>
              <td class="fw-semibold"><?= e(\App\Models\Invoice::number($inv, $invoicePrefix)) ?></td>
              <td><?= e($inv['customer_name']) ?></td>
              <td class="text-secondary"><?= e($inv['creator_name'] ?? '—') ?></td>
              <td class="text-secondary" data-order="<?= e($inv['issue_date']) ?>"><?= ds_date($inv['issue_date']) ?></td>
              <td class="fw-semibold" data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
              <td>
                <span class="badge rounded-pill <?= $statusBadgeClass[$inv['status']] ?? $statusBadgeClass['draft'] ?>">
                  <?= t('inv.status_' . $inv['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      </div><!-- /.ds-table -->
      </div><!-- /.card-body -->
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$jsMonths = json_encode(array_map(
    static fn(string $ym): string => t('month.' . (int) substr($ym, 5, 2)),
    $revenue['months']
), JSON_UNESCAPED_UNICODE);
$jsDatasets = json_encode(array_map(static fn(array $s): array => [
    'label'           => $s['label'],
    'data'            => $s['data'],
    'backgroundColor' => $s['color'],
    'borderRadius'    => 4,
], $revenue['series']), JSON_UNESCAPED_UNICODE);

$scripts = ds_table_script() . <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: $jsMonths,
      datasets: $jsDatasets
    },
    options: {
      plugins: { legend: { position: 'bottom' } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: 'rgba(148,163,184,.15)' }, beginAtZero: true },
      },
    }
  });
</script>
HTML;
?>
