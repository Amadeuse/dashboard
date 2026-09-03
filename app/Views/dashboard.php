<?php
/**
 * @var array  $user    Auth::user() — for the greeting
 * @var array  $stats   Dashboard::stats() — 4 cards: customers/products/invoices/revenue
 * @var array  $revenue Dashboard::revenueByUser() — {months: ['2026-03', ...], series: [{userId,label,color,data}]}
 * @var array  $recent  Dashboard::recentInvoices() — last few invoices, any tenant member
 * @var array  $org     the organization row (Organization::get()) — the email modal's default message/signature (4.84)
 * @var string $invoicePrefix organization.invoice_prefix (or "INV"), for Invoice::number()
 * @var string $currency organization.currency ('GEL' or 'USD')
 * @var string $appUrl  app_url() — base for each row's "ბმულის გაზიარება" link (4.84), possibly '' (see helpers.php)
 * @var ?string $emailSent   formatted number of an invoice just emailed from /invoices, whose send bounced here (4.64) instead of back to the edit form
 * @var ?string $emailFailed formatted number of an invoice that failed to email — see InvoiceController::sendEmail()
 * @var array  $emailErrors  'to'/'message' => message, from a failed "მეილზე გაგზავნა" submit on this table's own modal (4.84) — same shape as orders.php's own
 * @var array  $emailOld     'to'/'message' => value, so a rejected email-modal submit comes back filled
 *
 * Real, tenant-scoped data (App\Core\Auth::tenantId()) — see handoff.md 4.35.
 * Replaces the earlier hardcoded sample dashboard (fake traffic sources,
 * fake team activity feed, fake monthly goal — none of those concepts exist
 * anywhere else in this app, so they were removed rather than wired to
 * something real that doesn't exist). The "Recent invoices" table itself
 * has the same row actions /orders does (4.84) — view/edit/print/PDF/
 * email/share, same modals and closures as orders.php, duplicated here
 * rather than shared (the established convention already used twice,
 * invoices.php/orders.php — see 4.62's own note on why).
 */
$documentStateBadgeClass = [
    'draft' => 'bg-secondary-subtle text-secondary-emphasis',
    'final' => 'bg-info-subtle text-info-emphasis',
];
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);
$shareUrl      = static fn(array $row): string => $appUrl . '/invoices/view?id=' . (int) $row['id'] . '&token=' . $row['view_token'];

// Same convention as orders.php's own email-modal closures (4.55/4.62).
$emailDefaults = ['message' => (string) ($org['email_message_default'] ?? '') ?: t('inv.email_message_default')];
$emailVal = static fn(string $f): string => e((string) ($emailOld[$f] ?? $emailDefaults[$f] ?? ''));
$emailBad = static fn(string $f): string => isset($emailErrors[$f]) ? 'is-invalid' : '';
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

<?php // Flashed notice/emailSent/emailFailed now render as toasts (ds_flash_toast(), appended to $scripts below) — see 4.82 in handoff.md. ?>

<!-- Stat cards -->
<?php
  $statUrl = [
      'stat.customers' => '/customers',
      'stat.products'  => '/products',
      'stat.invoices'  => '/orders',
      'stat.revenue'   => '/orders',
  ];
?>
<div class="row g-3 mb-3">
  <?php foreach ($stats as $s):
    $toneClass = $s['tone'] === 'primary'
      ? 'bg-primary-subtle text-primary'
      : "bg-{$s['tone']}-subtle text-{$s['tone']}-emphasis";
  ?>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= e($statUrl[$s['key']]) ?>" class="ds-card-link">
      <div class="card ds-card h-100">
        <div class="card-body d-flex gap-3">
          <div class="ds-icon-tile <?= $toneClass ?>"><i class="bi <?= $s['icon'] ?>"></i></div>
          <div>
            <div class="text-secondary small"><?= t($s['key']) ?></div>
            <div class="h4 fw-bold mb-0"><?= $s['value'] ?><?= $s['key'] === 'stat.revenue' ? ' ' . e(currency_symbol($currency)) : '' ?></div>
          </div>
        </div>
      </div>
    </a>
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
        <table class="table table-hover table-striped align-middle mb-0">
          <thead>
            <tr class="text-secondary">
              <th><?= t('inv.number') ?></th>
              <th><?= t('orders.customer') ?></th>
              <th><?= t('inv.creator') ?></th>
              <th><?= t('orders.date') ?></th>
              <th><?= t('orders.amount') ?> (<?= e(currency_symbol($currency)) ?>)</th>
              <th><?= t('orders.status') ?></th>
              <th><?= t('inv.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $inv): ?>
            <tr>
              <td class="fw-semibold"><?= e($invoiceNumber($inv)) ?></td>
              <td><?= e($inv['customer_name']) ?></td>
              <td class="text-secondary"><?php if (($inv['creator_color'] ?? null) !== null): ?><span class="ds-color-dot" style="background:<?= e($inv['creator_color']) ?>"></span><?php endif; ?><?= e($inv['creator_name'] ?? '—') ?></td>
              <td class="text-secondary" data-order="<?= e($inv['issue_date']) ?>"><?= ds_date($inv['issue_date']) ?></td>
              <td class="fw-semibold" data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
              <td>
                <span class="badge rounded-pill <?= $documentStateBadgeClass[$inv['document_state']] ?>">
                  <?= t('inv.status_' . $inv['document_state']) ?>
                </span>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.view') ?>"
                        data-bs-toggle="modal" data-bs-target="#invoicePreviewModal"
                        data-invoice-id="<?= (int) $inv['id'] ?>"
                        data-invoice-number="<?= e($invoiceNumber($inv)) ?>"
                        data-invoice-document-state="<?= e($inv['document_state']) ?>">
                  <i class="bi bi-eye"></i>
                </button>
                <a href="/invoices?edit=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('cust.edit_hint') ?>">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="/invoices/view?id=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.print') ?>">
                  <i class="bi bi-printer"></i>
                </a>
                <div class="dropdown d-inline-block">
                  <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" title="<?= t('orders.export_pdf') ?>">
                    <i class="bi bi-file-earmark-pdf"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/invoices/export-pdf?id=<?= (int) $inv['id'] ?>&sign=1"><i class="bi bi-pen me-1"></i><?= t('inv.export_signed') ?></a></li>
                    <li><a class="dropdown-item" href="/invoices/export-pdf?id=<?= (int) $inv['id'] ?>&sign=0"><i class="bi bi-file-earmark me-1"></i><?= t('inv.export_unsigned') ?></a></li>
                  </ul>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.action_email') ?>"
                        data-bs-toggle="modal" data-bs-target="#invoiceEmailModal"
                        data-invoice-id="<?= (int) $inv['id'] ?>"
                        data-customer-email="<?= e((string) ($inv['customer_email'] ?? '')) ?>">
                  <i class="bi bi-envelope"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.action_share_link') ?>"
                        data-share-url="<?= e($shareUrl($inv)) ?>">
                  <i class="bi bi-link-45deg"></i>
                </button>
                <a href="/invoices?duplicate=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.action_duplicate') ?>">
                  <i class="bi bi-copy"></i>
                </a>
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

<?php require APP_PATH . '/Views/partials/invoice-preview-modal.php'; ?>

<!-- "მეილზე გაგზავნა" per row — same modal/route as orders.php's own
     (4.62), redirect=/ tells InvoiceController::sendEmail() to bounce
     back here (4.84) instead of /invoices?edit=N. -->
<div class="modal fade" id="invoiceEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="/invoices/send-email" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="invoice_id" id="emailInvoiceId">
      <input type="hidden" name="redirect" value="/">
      <div class="modal-header bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-envelope-paper text-primary"></i>
          <span class="fw-bold text-primary small text-uppercase"><?= t('inv.email_modal_title') ?></span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="<?= t('inv.close') ?>"></button>
      </div>
      <?php
        // Same preview logic as orders.php's/invoices.php's own modal — see
        // there for why it's duplicated (emails/invoice.php's signature
        // computation, 4.66) rather than shared.
        $emailSignatureLines = array_filter([
            (string) ($org['name'] ?? ''),
            (string) ($org['phone'] ?? ''),
            (string) ($org['email'] ?? ''),
            (string) ($org['address'] ?? ''),
        ], static fn(string $v): bool => $v !== '');
      ?>
      <div class="modal-body">
        <div class="form-floating mb-3">
          <input type="email" class="form-control <?= $emailBad('to') ?>" id="emailTo" name="to" value="<?= $emailVal('to') ?>" placeholder=" " required>
          <label for="emailTo"><?= t('inv.email_to') ?></label>
          <?php if (isset($emailErrors['to'])): ?><div class="invalid-feedback"><?= e($emailErrors['to']) ?></div><?php endif; ?>
        </div>

        <div class="rounded-3 overflow-hidden border <?= isset($emailErrors['message']) ? 'border-danger' : '' ?>">
          <div class="px-3 py-2" style="background:#2563eb;">
            <span class="text-white fw-bold small"><?= e((string) ($org['name'] ?? app_name())) ?></span>
          </div>
          <div class="p-3">
            <textarea class="form-control border-0 p-0 shadow-none" id="emailMessage" name="message"
                      placeholder="<?= t('inv.email_message_placeholder') ?>" style="height:8rem; resize:none;" required><?= $emailVal('message') ?></textarea>
          </div>
          <?php if ($emailSignatureLines !== []): ?>
            <div class="px-3 pb-3 small text-secondary">
              <div class="border-top pt-2"><?= implode('<br>', array_map('e', $emailSignatureLines)) ?></div>
            </div>
          <?php endif; ?>
        </div>
        <?php if (isset($emailErrors['message'])): ?><div class="invalid-feedback d-block"><?= e($emailErrors['message']) ?></div><?php endif; ?>

        <div class="text-secondary small mt-2 d-flex align-items-center gap-2">
          <i class="bi bi-paperclip"></i><?= t('inv.email_attachment_note') ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('inv.close') ?></button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i><?= t('inv.email_send') ?></button>
      </div>
    </form>
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

$scripts = ds_table_script() . ds_invoice_preview_script() . ds_share_link_script()
    . ds_flash_toast($notice !== null ? e($notice) : null, 'warning', 'bi-exclamation-triangle-fill')
    . ds_flash_toast($emailSent !== null ? t('inv.email_sent', e($emailSent)) : null)
    . ds_flash_toast($emailFailed !== null ? t('inv.email_failed', e($emailFailed)) : null, 'warning', 'bi-exclamation-triangle-fill')
    . <<<HTML
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
HTML
    . <<<'HTML'

<script>
(() => {
  const modal = document.getElementById('invoiceEmailModal');

  // Prefills "to" from the clicked row's own data-customer-email — only
  // when empty, so a failed submit's server-rendered $emailOld (still in
  // the input from the reopen below) is never clobbered. Same as
  // orders.php's own (4.62).
  modal?.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    document.getElementById('emailInvoiceId').value = btn?.dataset.invoiceId ?? '';
    const toInput = document.getElementById('emailTo');
    if (!toInput.value) toInput.value = btn?.dataset.customerEmail ?? '';
  });

  // Same trick orders.php uses for its own modal: a failed send
  // (InvoiceController::sendEmail() redirects here with ?email_error=<id>)
  // needs the modal open again, for that same row's trigger button, so its
  // errors/old values (already rendered server-side above) are visible.
  const params = new URLSearchParams(location.search);
  const errorId = params.get('email_error');
  if (errorId) {
    document.querySelector(`[data-bs-target="#invoiceEmailModal"][data-invoice-id="${errorId}"]`)?.click();
    history.replaceState(null, '', location.pathname + location.search.replace(/[?&]email_error=\d+/, ''));
  }
})();
</script>
HTML;
?>
