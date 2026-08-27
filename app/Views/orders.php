<?php
/**
 * @var array   $rows           every invoice, newest first — customer_name/
 *                               customer_taxid/customer_email and the
 *                               creator's name (creator_name, nullable)
 *                               joined in by Invoice::all()
 * @var string  $invoicePrefix  organization.invoice_prefix, or "INV" if unset
 * @var string  $currency       organization.currency ('GEL' or 'USD')
 * @var int     $total          row count
 * @var array   $org            the organization row (Organization::get()), for the email modal's default message
 * @var array   $emailErrors    'to'/'message' => message, from a failed "მეილზე გაგზავნა" submit (4.62) — same shape as invoices.php's own
 * @var array   $emailOld       'to'/'message' => value, so a rejected email-modal submit comes back filled
 * @var ?string $emailSent      formatted number of the invoice an email was just sent for
 * @var ?string $emailFailed    formatted number of the invoice an email failed to send for (SMTP down/misconfigured — see App\Core\Mailer)
 * @var string  $appUrl         app_url() — base for each row's "ბმულის გაზიარება" link (4.68), possibly '' (see helpers.php)
 *
 * The list half of what used to be one /invoices page (see 4.25 in
 * handoff.md) — creating/editing lives on /invoices, this is browsing.
 * Reached from the sidebar (შეკვეთები > ყველა შეკვეთა), not its own
 * top-level item. Each row is two plain links, not a click-to-edit row:
 * editing now means a real navigation to /invoices?edit=N (that page loads
 * the invoice into its form server-side), so there's nothing left for a
 * row click to do in-place — an edit pencil + a print icon are more
 * honest than a "clickable" row that silently didn't used to do anything
 * here.
 */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);
$shareUrl      = static fn(array $row): string => $appUrl . '/invoices/view?id=' . (int) $row['id'] . '&token=' . $row['view_token'];

// Same convention as invoices.php's own email-modal closures (4.55/4.62) —
// the org's own default text (/settings/organization) wins over the
// built-in one, a failed submit's own $emailOld always wins over both.
$emailDefaults = ['message' => (string) ($org['email_message_default'] ?? '') ?: t('inv.email_message_default')];
$emailVal = static fn(string $f): string => e((string) ($emailOld[$f] ?? $emailDefaults[$f] ?? ''));
$emailBad = static fn(string $f): string => isset($emailErrors[$f]) ? 'is-invalid' : '';

// '0' is how legacy/imported rows spell "no tax id" (see Customer.php's
// docblock) — same treatment invoices.php's customer-info panel gives it.
$taxId = static function (array $row): string {
    $value = (string) ($row['customer_taxid'] ?? '');
    return $value !== '' && $value !== '0' ? e($value) : '<span class="text-secondary">—</span>';
};

// Same map dashboard.php's own status badge uses.
$documentStateBadgeClass = [
    'draft' => 'bg-secondary-subtle text-secondary-emphasis',
    'final' => 'bg-info-subtle text-info-emphasis',
];

// InvoiceWorkflow (payment/cancellation) is independent of the status above
// — see handoff.md and $workflow's own docblock in InvoiceController::orders().
// $workflow is keyed by invoice id and only has entries when the module is
// enabled, so this stays a no-op badge-wise when it's off.
$paymentBadgeClass = [
    'unpaid'  => 'bg-danger-subtle text-danger-emphasis',
    'partial' => 'bg-warning-subtle text-warning-emphasis',
    'paid'    => 'bg-success-subtle text-success-emphasis',
];
?>

<?php if ($emailSent !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('inv.email_sent', e($emailSent)) ?>
  </div>
<?php endif; ?>
<?php if ($emailFailed !== null): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= t('inv.email_failed', e($emailFailed)) ?>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active">
          <?= t('nav.orders_all') ?>
          <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
        </li>
      </ol>
    </nav>
  </div>
  <?php if ($rows !== []): ?>
    <!-- Same "ხელმოწერით"/"ხელმოწერის გარეშე" choice as invoices.php's own
         export dropdown (4.63 in handoff.md) — a plain ?sign= link here
         instead of a form submit, this page has no single invoice to save
         first. -->
    <div class="dropdown">
      <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-file-earmark-pdf me-1"></i> <?= t('orders.export_pdf') ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="/orders/export-pdf?sign=1"><i class="bi bi-pen me-1"></i><?= t('inv.export_signed') ?></a></li>
        <li><a class="dropdown-item" href="/orders/export-pdf?sign=0"><i class="bi bi-file-earmark me-1"></i><?= t('inv.export_unsigned') ?></a></li>
      </ul>
    </div>
  <?php endif; ?>
</div>

<div class="card ds-card">
  <?php if ($rows === []): ?>
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
            <th><?= t('inv.customer') ?></th>
            <th><?= t('cust.taxid') ?></th>
            <th><?= t('inv.creator') ?></th>
            <th><?= t('inv.total') ?> (<?= e(currency_symbol($currency)) ?>)</th>
            <th><?= t('orders.status') ?></th>
            <th><?= t('inv.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $inv): ?>
          <tr>
            <td><?= e($invoiceNumber($inv)) ?></td>
            <td><?= e($inv['customer_name']) ?></td>
            <td><?= $taxId($inv) ?></td>
            <td><?= $inv['creator_name'] !== null ? e($inv['creator_name']) : '<span class="text-secondary">—</span>' ?></td>
            <td data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
            <td>
              <span class="badge rounded-pill <?= $documentStateBadgeClass[$inv['document_state']] ?>">
                <?= t('inv.status_' . $inv['document_state']) ?>
              </span>
              <?php if (isset($workflow[$inv['id']])): $wf = $workflow[$inv['id']]; ?>
                <?php if ($wf['cancelled_at'] !== null): ?>
                  <span class="badge rounded-pill bg-dark-subtle text-dark-emphasis"><?= t('workflow.cancelled_label') ?></span>
                <?php else: ?>
                  <span class="badge rounded-pill <?= $paymentBadgeClass[$wf['payment_state']] ?>">
                    <?= t('workflow.payment_' . $wf['payment_state']) ?>
                  </span>
                <?php endif; ?>
              <?php endif; ?>
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

<?php require APP_PATH . '/Views/partials/invoice-preview-modal.php'; ?>

<!-- "მეილზე გაგზავნა" per row — same modal/route as invoices.php's own
     (4.55), just one shared instance for every row here instead of one
     invoice being edited. redirect=/orders tells InvoiceController::
     sendEmail() where to bounce back to (4.62); "to" prefills from the
     clicked row's data-customer-email, not a customer-select value like
     invoices.php has (there isn't one on this page). -->
<div class="modal fade" id="invoiceEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="/invoices/send-email" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="invoice_id" id="emailInvoiceId">
      <input type="hidden" name="redirect" value="/orders">
      <div class="modal-header bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-envelope-paper text-primary"></i>
          <span class="fw-bold text-primary small text-uppercase"><?= t('inv.email_modal_title') ?></span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="<?= t('inv.close') ?>"></button>
      </div>
      <?php
        // Same preview logic as invoices.php's own modal — see there for
        // why it's duplicated (emails/invoice.php's signature computation,
        // 4.66) rather than shared.
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
$scripts = ds_table_script() . ds_invoice_preview_script() . ds_share_link_script() . <<<'HTML'

<script>
(() => {
  const modal = document.getElementById('invoiceEmailModal');

  // Prefills "to" from the clicked row's own data-customer-email — only
  // when empty, so a failed submit's server-rendered $emailOld (still in
  // the input from the reopen below) is never clobbered.
  modal?.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    document.getElementById('emailInvoiceId').value = btn?.dataset.invoiceId ?? '';
    const toInput = document.getElementById('emailTo');
    if (!toInput.value) toInput.value = btn?.dataset.customerEmail ?? '';
  });

  // Same trick invoices.php uses for its own modal (4.55): a failed send
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
