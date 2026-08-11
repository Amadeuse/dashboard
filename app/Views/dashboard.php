<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.dashboard') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= t('dash.greeting', 'Givi') ?> 👋</h1>
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
          <div class="h4 fw-bold mb-1"><?= $s['value'] ?></div>
          <span class="badge bg-<?= $s['trend'] ?>-subtle text-<?= $s['trend'] ?>-emphasis rounded-pill">
            <i class="bi bi-arrow-<?= $s['dir'] ?>-short"></i><?= $s['delta'] ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Chart + traffic -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card ds-card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h6 fw-bold mb-0"><?= t('chart.revenue') ?></h2>
          <ul class="nav nav-pills small" role="tablist">
            <li class="nav-item"><button class="nav-link py-1 px-2 active" data-range="week"><?= t('range.week') ?></button></li>
            <li class="nav-item"><button class="nav-link py-1 px-2" data-range="month"><?= t('range.month') ?></button></li>
            <li class="nav-item"><button class="nav-link py-1 px-2" data-range="year"><?= t('range.year') ?></button></li>
          </ul>
        </div>
        <canvas id="revenueChart" height="110"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('traffic.title') ?></h2>
        <canvas id="trafficChart" height="170"></canvas>
        <ul class="list-unstyled small mt-3 mb-0">
          <?php foreach ($traffic as $row): ?>
          <li class="d-flex justify-content-between py-1">
            <span><i class="bi bi-circle-fill me-2" style="color:<?= $row['color'] ?>;font-size:.6rem;"></i><?= t($row['key']) ?></span>
            <span class="fw-semibold"><?= $row['pct'] ?>%</span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Table + activity -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card ds-card h-100">
      <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h2 class="h6 fw-bold mb-0"><?= t('orders.title') ?></h2>
        <a href="#" class="small text-decoration-none"><?= t('orders.view_all') ?></a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary small">
              <th><?= t('orders.customer') ?></th>
              <th><?= t('orders.product') ?></th>
              <th><?= t('orders.date') ?></th>
              <th><?= t('orders.amount') ?></th>
              <th><?= t('orders.status') ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="ds-avatar" style="width:32px;height:32px;font-size:.7rem;background:<?= $o['color'] ?>"><?= $o['initials'] ?></span>
                  <span class="fw-semibold"><?= e($o['name']) ?></span>
                </div>
              </td>
              <td class="text-secondary"><?= e($o['product']) ?></td>
              <td class="text-secondary"><?= ds_date($o['date']) ?></td>
              <td class="fw-semibold"><?= $o['amount'] ?></td>
              <td><span class="badge bg-<?= $o['tone'] ?>-subtle text-<?= $o['tone'] ?>-emphasis rounded-pill"><?= t($o['status']) ?></span></td>
              <td class="text-end pe-3"><a href="#" class="text-secondary"><i class="bi bi-three-dots"></i></a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card ds-card mb-3">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('activity.title') ?></h2>
        <ul class="ds-timeline">
          <?php foreach ($activity as $a): ?>
          <li>
            <div class="small fw-semibold"><?= t($a['text']) ?></div>
            <div class="text-secondary" style="font-size:.78rem;"><?= t($a['time']) ?></div>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="card ds-card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('goal.title') ?></h2>
        <div class="d-flex justify-content-between small mb-1">
          <span class="text-secondary"><?= $goal['current'] ?> / <?= $goal['target'] ?></span>
          <span class="fw-semibold"><?= $goal['percent'] ?>%</span>
        </div>
        <div class="progress" style="height:8px;">
          <div class="progress-bar" style="width:<?= $goal['percent'] ?>%"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$jsWeekdays = json_encode(explode(',', t('chart.weekdays')), JSON_UNESCAPED_UNICODE);
$jsRevenue  = json_encode(t('stat.revenue'), JSON_UNESCAPED_UNICODE);
$jsData     = json_encode($revenue);
$jsLabels   = json_encode(array_map(static fn($r) => t($r['key']), $traffic), JSON_UNESCAPED_UNICODE);
$jsValues   = json_encode(array_column($traffic, 'pct'));
$jsColors   = json_encode(array_column($traffic, 'color'));

$scripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: $jsWeekdays,
      datasets: [{
        label: $jsRevenue,
        data: $jsData,
        borderColor: '#4f46e5',
        backgroundColor: 'rgba(79,70,229,.12)',
        tension: .4,
        fill: true,
        pointRadius: 0,
        borderWidth: 2.5,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { grid: { color: 'rgba(148,163,184,.15)' } }, x: { grid: { display: false } } },
    }
  });

  new Chart(document.getElementById('trafficChart'), {
    type: 'doughnut',
    data: {
      labels: $jsLabels,
      datasets: [{ data: $jsValues, backgroundColor: $jsColors, borderWidth: 0 }]
    },
    options: { plugins: { legend: { display: false } }, cutout: '72%' }
  });
</script>
HTML;
