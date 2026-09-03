<?php
/**
 * @var string $currency organization.currency ('GEL' or 'USD')
 * @var string $from     the filter's active start date ('Y-m-d')
 * @var string $to       the filter's active end date ('Y-m-d')
 * @var string $granularity 'daily'|'weekly'|'monthly' — the trend chart's bucket size
 * @var array  $summary  Analytics::summary() — count/total/average/finalRate
 * @var array  $trend    Analytics::revenueTrend() — {labels, data}
 * @var array  $topCustomers Analytics::topCustomers() — up to 5, {name,count,total}
 * @var array  $topProducts  Analytics::topProducts() — up to 5, {name,quantity,revenue}
 *
 * "ანალიტიკა > მიმოხილვა" (menu.json) — a free date-range slice across the
 * whole tenant (Auth::invoiceScopeUserIds()-scoped, same rule the dashboard
 * and CustomerReport.php already follow), unlike the dashboard's own chart
 * (always the current calendar year). User's own explicit request (4.92 in
 * handoff.md) — the filter, the trend chart, and the two "top" cards.
 */
$granularityLabel = static fn(string $bucket): string => match ($granularity) {
    'daily'   => ds_date($bucket),
    'monthly' => t('month.' . (int) substr($bucket, 5, 2)) . ' ' . substr($bucket, 0, 4),
    default   => $bucket, // weekly: 'YYYY-Www' ISO week, shown as-is
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('nav.analytics_overview') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= t('nav.analytics_overview') ?></h1>
  </div>
</div>

<!-- Filter -->
<div class="card ds-card mb-3">
  <div class="card-body">
    <form method="get" action="/analytics/overview" class="row g-3 align-items-end">
      <div class="col-sm-6 col-md-3">
        <label for="af-from" class="form-label small mb-1"><?= t('analytics.filter_from') ?></label>
        <input type="date" class="form-control" id="af-from" name="from" value="<?= e($from) ?>" max="<?= e($to) ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label for="af-to" class="form-label small mb-1"><?= t('analytics.filter_to') ?></label>
        <input type="date" class="form-control" id="af-to" name="to" value="<?= e($to) ?>" max="<?= e(date('Y-m-d')) ?>">
      </div>
      <div class="col-sm-6 col-md-3">
        <label for="af-granularity" class="form-label small mb-1"><?= t('analytics.filter_granularity') ?></label>
        <select class="form-select" id="af-granularity" name="granularity">
          <?php foreach (['daily', 'weekly', 'monthly'] as $g): ?>
            <option value="<?= $g ?>" <?= $granularity === $g ? 'selected' : '' ?>><?= t('analytics.granularity_' . $g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-3">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i><?= t('analytics.filter_apply') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Headline stats -->
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3">
    <div class="card ds-card h-100">
      <div class="card-body d-flex gap-3">
        <div class="ds-icon-tile bg-warning-subtle text-warning-emphasis"><i class="bi bi-receipt"></i></div>
        <div>
          <div class="text-secondary small"><?= t('cust.report_total_invoices') ?></div>
          <div class="h4 fw-bold mb-0"><?= number_format($summary['count']) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card ds-card h-100">
      <div class="card-body d-flex gap-3">
        <div class="ds-icon-tile bg-success-subtle text-success-emphasis"><i class="bi bi-cash-stack"></i></div>
        <div>
          <div class="text-secondary small"><?= t('cust.report_total_amount') ?></div>
          <div class="h4 fw-bold mb-0"><?= e(money($summary['total'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card ds-card h-100">
      <div class="card-body d-flex gap-3">
        <div class="ds-icon-tile bg-info-subtle text-info-emphasis"><i class="bi bi-graph-up"></i></div>
        <div>
          <div class="text-secondary small"><?= t('cust.report_avg_invoice') ?></div>
          <div class="h4 fw-bold mb-0"><?= e(money($summary['average'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card ds-card h-100">
      <div class="card-body d-flex gap-3">
        <div class="ds-icon-tile bg-primary-subtle text-primary"><i class="bi bi-check2-circle"></i></div>
        <div>
          <div class="text-secondary small"><?= t('analytics.stat_final_rate') ?></div>
          <div class="h4 fw-bold mb-0"><?= number_format($summary['finalRate'], 1) ?>%</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Revenue trend -->
  <div class="col-lg-8">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('analytics.chart_title') ?></h2>
        <?php if ($trend['labels'] === []): ?>
          <div class="text-center text-secondary py-5">
            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
            <?= t('analytics.empty') ?>
          </div>
        <?php else: ?>
          <canvas id="analyticsTrendChart" height="90"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top customers + top products -->
  <div class="col-lg-4 d-flex flex-column gap-3">
    <div class="card ds-card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('analytics.top_customers') ?></h2>
        <?php if ($topCustomers === []): ?>
          <div class="text-secondary small"><?= t('analytics.empty') ?></div>
        <?php else: ?>
          <?php foreach ($topCustomers as $i => $c): ?>
            <div class="d-flex justify-content-between align-items-center <?= $i < count($topCustomers) - 1 ? 'mb-2 pb-2 border-bottom' : '' ?>">
              <div>
                <div class="fw-semibold"><?= e($c['name']) ?></div>
                <div class="text-secondary small"><?= t('analytics.customer_invoices', (string) $c['count']) ?></div>
              </div>
              <div class="fw-semibold text-nowrap"><?= e(money($c['total'], $currency)) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card ds-card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('cust.report_top_products') ?></h2>
        <?php if ($topProducts === []): ?>
          <div class="text-secondary small"><?= t('analytics.empty') ?></div>
        <?php else: ?>
          <?php foreach ($topProducts as $i => $p): ?>
            <div class="d-flex justify-content-between align-items-center <?= $i < count($topProducts) - 1 ? 'mb-2 pb-2 border-bottom' : '' ?>">
              <div>
                <div class="fw-semibold"><?= e($p['name']) ?></div>
                <div class="text-secondary small"><?= rtrim(rtrim(number_format($p['quantity'], 3), '0'), '.') ?> <?= mb_strtolower(t('cust.report_product_qty')) ?></div>
              </div>
              <div class="fw-semibold text-nowrap"><?= e(money($p['revenue'], $currency)) ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
if ($trend['labels'] !== []) {
    $jsLabels = json_encode(array_map($granularityLabel, $trend['labels']), JSON_UNESCAPED_UNICODE);
    $jsData   = json_encode($trend['data']);

    $scripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('analyticsTrendChart'), {
    type: 'line',
    data: {
      labels: $jsLabels,
      datasets: [{
        data: $jsData,
        borderColor: '#4f46e5',
        backgroundColor: 'rgba(79,70,229,.1)',
        fill: true,
        tension: .3,
        pointRadius: 3,
      }],
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: 'rgba(148,163,184,.15)' }, beginAtZero: true },
      },
    }
  });
</script>
HTML;
}
?>
