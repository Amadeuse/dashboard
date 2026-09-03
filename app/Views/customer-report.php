<?php
/**
 * @var array   $customer           the customer row (Customer::find())
 * @var string  $invoicePrefix      organization.invoice_prefix, or "INV" if unset
 * @var string  $currency           organization.currency ('GEL' or 'USD')
 * @var array   $invoices           CustomerReport::invoices() — newest number first, creator_name joined in
 * @var ?int    $defaultInvoiceId   the invoice shown inline on load (most recent — first row of $invoices), or null if this customer has none
 * @var string  $invoicePreviewHtml invoice-preview.php already rendered for $defaultInvoiceId, or '' when there isn't one
 * @var array   $summary            CustomerReport::summary() — count/total/average/firstDate/lastDate
 * @var array   $statusTotals       CustomerReport::statusTotals() — document_state => {count,total}
 * @var array   $monthly            CustomerReport::monthlyTotals() — {months, series} (one per document_state)
 * @var array   $topProducts        CustomerReport::topProducts() — up to 5, {name,quantity,revenue}
 *
 * One customer's whole invoice history — reached from customers.php's
 * "რეპორტი" row-link (4.69/4.70 in handoff.md). Section order (customer
 * info, the invoice itself, the list, then the aggregate stats) and the
 * inline "ნახვა" (no modal — clicking a number in the list re-fetches
 * InvoiceController::preview() straight into this page, same endpoint
 * orders.php's modal always used) are both the user's own explicit request.
 */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);

// Same map orders.php/dashboard.php already use for the status badge.
$documentStateBadgeClass = [
    'draft' => 'bg-secondary-subtle text-secondary-emphasis',
    'final' => 'bg-info-subtle text-info-emphasis',
];
$defaultInvoiceNumber = $defaultInvoiceId !== null ? $invoiceNumber($invoices[0]) : '';
$defaultInvoiceStatus = $defaultInvoiceId !== null ? $invoices[0]['document_state'] : '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item"><a href="/customers" class="text-decoration-none"><?= t('page.customers') ?></a></li>
        <li class="breadcrumb-item active"><?= e($customer['customer_name']) ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= e($customer['customer_name']) ?></h1>
  </div>
  <a href="/customers" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= t('cust.report_back') ?></a>
</div>

<!-- 1. Customer info -->
<div class="card ds-card mb-3">
  <div class="card-body">
    <div class="row g-3">
      <?php
        $infoField = static function (string $icon, string $label, ?string $value): void {
            if ($value === null || $value === '') {
                return;
            } ?>
        <div class="col-sm-6 col-lg-3">
          <div class="text-secondary small"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></div>
          <div class="fw-semibold"><?= e($value) ?></div>
        </div>
      <?php };
        $infoField('bi-upc-scan', t('cust.taxid'), $customer['customer_taxid'] !== '0' ? $customer['customer_taxid'] : null);
        $infoField('bi-person', t('cust.contact'), $customer['customer_contact']);
        $infoField('bi-telephone', t('cust.phone'), $customer['customer_phone']);
        $infoField('bi-envelope', t('cust.email'), $customer['customer_email']);
        $infoField('bi-geo-alt', t('cust.address'), $customer['customer_address']);
      ?>
    </div>
  </div>
</div>

<!-- 2. The invoice itself — inline, no modal (4.70) -->
<div class="card ds-card mb-3">
  <div class="card-header bg-light d-flex align-items-center gap-2">
    <i class="bi bi-receipt text-primary"></i>
    <span class="fw-bold text-primary small text-uppercase">Invoice</span>
    <?php if ($defaultInvoiceId !== null): ?>
      <span class="text-secondary">|</span>
      <span class="fw-semibold" id="reportInvoiceNumber"><?= e($defaultInvoiceNumber) ?></span>
      <span class="badge rounded-pill <?= $documentStateBadgeClass[$defaultInvoiceStatus] ?> ms-auto" id="reportInvoiceStatus">
        <?= t('inv.status_' . $defaultInvoiceStatus) ?>
      </span>
    <?php endif; ?>
  </div>
  <div class="card-body" id="invoicePreviewInline">
    <?php if ($defaultInvoiceId !== null): ?>
      <?= $invoicePreviewHtml ?>
    <?php else: ?>
      <div class="text-center text-secondary py-5">
        <i class="bi bi-receipt d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
        <?= t('cust.report_empty') ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- 3. Invoice list -->
<div class="card ds-card mb-3">
  <div class="card-header bg-transparent py-3">
    <h2 class="h6 mb-0"><?= t('cust.report_invoices_title') ?></h2>
  </div>
  <?php if ($invoices === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-receipt d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('cust.report_empty') ?>
    </div>
  <?php else: ?>
    <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('inv.number') ?></th>
            <th><?= t('orders.date') ?></th>
            <th><?= t('inv.creator') ?></th>
            <th><?= t('inv.total') ?> (<?= e(currency_symbol($currency)) ?>)</th>
            <th><?= t('orders.status') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr class="<?= (int) $inv['id'] === $defaultInvoiceId ? 'table-active' : '' ?>">
            <td>
              <button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold js-report-invoice-trigger"
                      data-invoice-id="<?= (int) $inv['id'] ?>"
                      data-invoice-number="<?= e($invoiceNumber($inv)) ?>"
                      data-invoice-document-state="<?= e($inv['document_state']) ?>">
                <?= e($invoiceNumber($inv)) ?>
              </button>
            </td>
            <td class="text-secondary" data-order="<?= e($inv['issue_date']) ?>"><?= ds_date($inv['issue_date']) ?></td>
            <td class="text-secondary"><?= $inv['creator_name'] !== null ? e($inv['creator_name']) : '<span class="text-secondary">—</span>' ?></td>
            <td class="fw-semibold" data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
            <td>
              <span class="badge rounded-pill <?= $documentStateBadgeClass[$inv['document_state']] ?>">
                <?= t('inv.status_' . $inv['document_state']) ?>
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

<!-- 4. Headline stats -->
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
        <div class="ds-icon-tile bg-primary-subtle text-primary"><i class="bi bi-calendar-check"></i></div>
        <div>
          <div class="text-secondary small"><?= t('cust.report_last_invoice') ?></div>
          <div class="h4 fw-bold mb-0"><?= $summary['lastDate'] !== null ? ds_date($summary['lastDate']) : t('cust.report_never') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Chart — one series per document_state (4.70) -->
  <div class="col-lg-8">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('cust.report_chart_title') ?></h2>
        <?php if ($monthly['months'] === []): ?>
          <div class="text-center text-secondary py-5">
            <i class="bi bi-bar-chart d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
            <?= t('cust.report_empty') ?>
          </div>
        <?php else: ?>
          <canvas id="customerVolumeChart" height="90"></canvas>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Status totals + top products -->
  <div class="col-lg-4 d-flex flex-column gap-3">
    <div class="card ds-card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('cust.report_status_totals') ?></h2>
        <?php foreach ($statusTotals as $status => $data): ?>
          <div class="<?= $status !== array_key_last($statusTotals) ? 'mb-3' : '' ?>">
            <div class="d-flex justify-content-between align-items-center">
              <span class="badge rounded-pill <?= $documentStateBadgeClass[$status] ?>"><?= t('inv.status_' . $status) ?></span>
              <span class="fw-semibold"><?= e(money($data['total'], $currency)) ?></span>
            </div>
            <div class="text-secondary small"><?= $data['count'] ?> <?= mb_strtolower(t('cust.report_total_invoices')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card ds-card">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-3"><?= t('cust.report_top_products') ?></h2>
        <?php if ($topProducts === []): ?>
          <div class="text-secondary small"><?= t('cust.report_empty') ?></div>
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
$jsMonths = json_encode(array_map(
    static fn(string $ym): string => t('month.' . (int) substr($ym, 5, 2)) . ' ' . substr($ym, 0, 4),
    $monthly['months']
), JSON_UNESCAPED_UNICODE);
$jsDatasets = json_encode(array_map(static fn(array $s): array => [
    'label'           => $s['label'],
    'data'            => $s['data'],
    'backgroundColor' => $s['color'],
    'borderRadius'    => 4,
], $monthly['series']), JSON_UNESCAPED_UNICODE);

$scripts = ds_table_script();
if ($monthly['months'] !== []) {
    $scripts .= <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  new Chart(document.getElementById('customerVolumeChart'), {
    type: 'bar',
    data: {
      labels: $jsMonths,
      datasets: $jsDatasets,
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
}

// Inline "ნახვა" (4.70) — clicking a row re-fetches InvoiceController::
// preview() (unchanged, orders.php/invoices.php's own modal used the exact
// same endpoint) straight into #invoicePreviewInline instead of a modal
// body. $statusData mirrors ds_invoice_preview_script()'s own (helpers.php)
// — not reused directly, that helper also wires up #invoicePreviewModal's
// show.bs.modal listener, which doesn't exist on this page at all anymore.
$statusData = json_encode([
    'classes' => $documentStateBadgeClass,
    'labels'  => ['draft' => t('inv.status_draft'), 'final' => t('inv.status_final')],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$scripts .= <<<HTML
<script>
(() => {
  const statusData = $statusData;
  const numberEl = document.getElementById('reportInvoiceNumber');
  const statusEl = document.getElementById('reportInvoiceStatus');
  const body      = document.getElementById('invoicePreviewInline');
  const card      = body.closest('.card');
  const loadingHtml = body.innerHTML;

  document.querySelectorAll('.js-report-invoice-trigger').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.invoiceId;

      numberEl.textContent = btn.dataset.invoiceNumber;
      statusEl.textContent = statusData.labels[btn.dataset.invoiceDocumentState] ?? '';
      statusEl.className   = 'badge rounded-pill ms-auto ' + (statusData.classes[btn.dataset.invoiceDocumentState] ?? '');

      document.querySelectorAll('tbody tr.table-active').forEach((row) => row.classList.remove('table-active'));
      btn.closest('tr')?.classList.add('table-active');

      body.innerHTML = loadingHtml;
      const res = await fetch('/invoices/preview?id=' + id);
      body.innerHTML = res.ok ? await res.text() : '';
      card.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
  });
})();
</script>
HTML;
?>
