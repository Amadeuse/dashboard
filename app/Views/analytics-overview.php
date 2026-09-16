<?php
/**
 * @var string $currency organization.currency ('GEL' or 'USD')
 * @var string $from     the filter's active start date ('Y-m-d')
 * @var string $to       the filter's active end date ('Y-m-d')
 * @var string $period   '' (all time) | 'month' | 'year' | 'range' — which toolbar control is active
 * @var string $granularity 'daily'|'weekly'|'monthly' — the trend chart's bucket, derived from the period (not a control)
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
  <div class="d-flex flex-wrap gap-2">
    <?php // The same period control as /orders (4.100–4.108), and nothing
          // else — the user asked for the two pages to match. The trend
          // chart's day/week/month bucket is derived from the period in the
          // controller, not chosen here (4.122). Every control reads its
          // height from --ds-control-height, so the row is one line. ?>
    <div class="btn-group" role="group" aria-label="<?= t('orders.period_filter') ?>">
      <a href="/analytics/overview" class="btn <?= $period === '' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= t('orders.period_all') ?></a>
      <a href="/analytics/overview?period=month" class="btn <?= $period === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= t('orders.period_month') ?></a>
      <a href="/analytics/overview?period=year" class="btn <?= $period === 'year' ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= t('orders.period_year') ?></a>
    </div>
    <form method="get" action="/analytics/overview" class="d-flex align-items-center gap-2">
      <div class="ds-date-range <?= $period === 'range' ? 'ds-date-range-active' : '' ?>"
           data-ds-date-range data-max="<?= e(date('Y-m-d')) ?>">
        <i class="bi bi-calendar3 ds-date-range-icon"></i>
        <input type="text" class="ds-date-range-input" data-ds-date-range-display
               placeholder="<?= t('orders.period_range') ?>" aria-label="<?= t('orders.period_range') ?>">
        <input type="hidden" name="from" value="<?= $period === 'range' ? e($from) : '' ?>" data-ds-date-range-from>
        <input type="hidden" name="to" value="<?= $period === 'range' ? e($to) : '' ?>" data-ds-date-range-to>
      </div>
      <button type="submit" class="btn <?= $period === 'range' ? 'btn-primary' : 'btn-outline-secondary' ?>" title="<?= t('orders.period_range') ?>">
        <i class="bi bi-check-lg"></i>
      </button>
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

    // heredoc below interpolates variables, not calls — resolve the URL first.
    $chartJs = ds_asset('/vendor/chartjs/js/chart.umd.min.js');
    $scripts = <<<HTML
<script src="$chartJs"></script>
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
