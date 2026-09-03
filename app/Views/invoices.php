<?php
/**
 * @var array   $customers      every customer, for the picker
 * @var array   $products       every product, for each line's picker (id/name/unit_id/unit_price)
 * @var array   $units          every unit (id/name), for each line's unit select — picking a
 *                                product defaults its line's unit to the product's own unit_id,
 *                                but the select stays freely overridable (same convention as
 *                                unit_price already has)
 * @var array   $org            the organization row (Organization::get()), for partials/invoice-header.php
 * @var string  $invoicePrefix  organization.invoice_prefix, or "INV" if unset
 * @var array   $invoicesByCustomer  customer_id => list of ['number','total'], for the right column's history panel
 * @var ?array  $editingInvoice the invoice being edited (?edit=N in the URL, or a failed resubmit), or null when adding — also null while merely *duplicating* one (?duplicate=N, 4.96), see $old['duplicate_of']
 * @var array   $errors         field => message, from the failed POST ('items_N' per line, 'items' if none)
 * @var array   $old            field => value, so a rejected form (or ?edit=N / ?duplicate=N) comes back filled
 * @var ?string $created        formatted number ("PH 2026-08-13 0007") of the invoice just added
 * @var ?string $updated        formatted number of the invoice just edited
 * @var array   $emailErrors    'to'/'message' => message, from a failed "მეილზე გაგზავნა" submit — separate from $errors, a different form
 * @var array   $emailOld       'to'/'message' => value, so a rejected email-modal submit comes back filled
 * @var ?string $emailSent      formatted number of the invoice an email was just sent for
 * @var ?string $emailFailed    formatted number of the invoice an email failed to send for (SMTP down/misconfigured — see App\Core\Mailer)
 * @var string  $shareUrl       the editing invoice's public view_token link (4.68), or '' when adding a brand new one
 *
 * Create/edit only — the list lives on its own page now (/orders, see
 * orders.php), reached from the sidebar under შეკვეთები > ყველა შეკვეთა.
 * Editing an existing invoice from there means a real navigation
 * (?edit=N), not a same-page row click: InvoiceController::index() loads
 * that invoice into $old exactly like a failed-validation resubmit would,
 * so every bit of the rendering below (customer select, item rows, the
 * number/date line) needed zero new branching — it already knew how to
 * redraw itself from $old. "დუბლირება" (?duplicate=N, 4.96) is the same
 * idea, a real navigation into a pre-filled $old — but its own, separate
 * index() branch/private method (loadDuplicateOld()), not a reuse of
 * ?edit=N's own loading code, so a change meant for one path can't
 * silently break the other (the user's own explicit request, accepting
 * the resulting duplication between the two on purpose).
 *
 * Line items are the same repeatable-row UX as organization.php's IBAN
 * accounts (4.24 in handoff.md) — a product/qty/price row that auto-adds a
 * fresh empty one the moment its product is picked, generalized from one
 * input to a row of three plus a computed total.
 */
$editing     = ($old['invoice_id'] ?? '') !== '';
// A fresh copy staged by "დუბლირება" (InvoiceController::duplicate()) —
// $old is populated but there's no real 'invoice_id', so $editing above
// stays false (this becomes a genuine new row only once "განახლება" is
// actually clicked) while this page still needs its own distinct
// background tone/heading, not the blank "new invoice" look.
$duplicating = isset($old['duplicate_of']);

$val      = static fn(string $f): string => e((string) ($old[$f] ?? ''));
$bad      = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$selected = static fn(string $f, string $optionValue): string
    => ((string) ($old[$f] ?? '')) === $optionValue ? 'selected' : '';

// Defaults only apply on the modal's first-ever open ($emailOld empty) —
// a failed submit's own $emailOld (even if the sender cleared the message
// entirely) always wins, same convention as $val()/$old above. The org's
// own text (/settings/organization) wins over the built-in one when set —
// same fallback organization.php's own preview of this field uses.
$emailDefaults = ['message' => (string) ($org['email_message_default'] ?? '') ?: t('inv.email_message_default')];
$emailVal = static fn(string $f): string => e((string) ($emailOld[$f] ?? $emailDefaults[$f] ?? ''));
$emailBad = static fn(string $f): string => isset($emailErrors[$f]) ? 'is-invalid' : '';

/** "{prefix} {issue_date} {0007}" — the same format \App\Models\Invoice::number() writes everywhere else. */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);
$uploadUrl = '/assets/uploads/organization/';

// Per-organization, not hardcoded — see org.vat_rate in /settings/organization.
// Display-only: prices are already entered VAT-inclusive, so this never
// changes the stored invoice total, just what the form shows alongside it.
$vatRate = (float) ($org['vat_rate'] ?? 18);
$vatRateDisplay = rtrim(rtrim(number_format($vatRate, 2), '0'), '.') ?: '0';
$currency = (string) ($org['currency'] ?? 'GEL');

/** id => full row, for the 1/4-column "customer info" panel — JS re-renders it from this on every selection change. */
$customersById = array_column($customers, null, 'id');
$customerFieldLabels = [
    'customer_taxid'   => t('cust.taxid'),
    'customer_contact' => t('cust.contact'),
    'customer_phone'   => t('cust.phone'),
    'customer_email'   => t('cust.email'),
    'customer_address' => t('cust.address'),
    'customer_info'    => t('cust.info'),
];

$itemRows = [];
if (isset($old['item_product_id'])) {
    foreach ((array) $old['item_product_id'] as $i => $productId) {
        $itemRows[] = [
            'product_id' => (string) $productId,
            'unit_id'    => (string) (((array) ($old['item_unit_id'] ?? []))[$i] ?? ''),
            'quantity'   => (string) (((array) ($old['item_quantity'] ?? []))[$i] ?? ''),
            'unit_price' => (string) (((array) ($old['item_unit_price'] ?? []))[$i] ?? ''),
        ];
    }
}
// Always leave one blank row to type into — a failed resubmit's own trailing
// blank row already round-trips through $old as-is, this only kicks in for
// a fresh ?edit=N load, where every row PHP built above is a real item.
if ($itemRows === [] || end($itemRows)['product_id'] !== '') {
    $itemRows[] = ['product_id' => '', 'unit_id' => '', 'quantity' => '', 'unit_price' => ''];
}

/** One product/qty/unit/price/total line — used for both the initial render and the JS-built rows share this shape (see rowHtml() below). */
$itemRow = static function (int $i, array $row, ?string $err) use ($products, $units): void {
    $productId = $row['product_id'];
    $unitId    = $row['unit_id']; ?>
  <div class="row g-2 mb-2 align-items-center" data-item-row>
    <div class="col">
      <select class="form-select <?= $err ? 'is-invalid' : '' ?>" name="item_product_id[]"
              data-ds-select data-search-placeholder="<?= t('table.search') ?>"
              data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>"
              data-item-product>
        <option value=""></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" data-price="<?= e((string) $p['unit_price']) ?>" data-unit="<?= (int) $p['unit_id'] ?>"
                  <?= $productId === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" step="0.001" min="0" class="form-control <?= $err ? 'is-invalid' : '' ?>"
             name="item_quantity[]" value="<?= e($row['quantity']) ?>" data-item-quantity>
    </div>
    <div class="col-md-2">
      <select class="form-select <?= $err ? 'is-invalid' : '' ?>" name="item_unit_id[]"
              data-ds-select data-search-placeholder="<?= t('table.search') ?>"
              data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>"
              data-item-unit>
        <option value=""></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $unitId === (string) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" step="0.01" min="0" class="form-control <?= $err ? 'is-invalid' : '' ?>"
             name="item_unit_price[]" value="<?= e($row['unit_price']) ?>" data-item-price>
    </div>
    <div class="col-md-2">
      <input type="text" class="form-control text-end fw-semibold" readonly tabindex="-1"
             value="0.00" data-item-line-total>
    </div>
    <div class="col-auto">
      <button type="button" class="btn btn-outline-secondary btn-sm invoice-item-remove" tabindex="-1">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <?php if ($err): ?><div class="col-12"><div class="invalid-feedback d-block"><?= e($err) ?></div></div><?php endif; ?>
  </div>
<?php };
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.invoices') ?></li>
      </ol>
    </nav>
  </div>
</div>

<?php // Flashed created/updated/emailSent/emailFailed/conflict now render as toasts (ds_flash_toast(), appended to $scripts below) — see 4.82 in handoff.md. ?>

<div class="row g-3">
  <div class="col-lg-9">
    <div class="card ds-card" id="invoice-form">
      <?php
        $headerBg    = $duplicating ? 'bg-info-subtle' : ($editing ? 'bg-warning-subtle' : 'bg-transparent');
        $headerTitle = $duplicating ? t('inv.duplicate_title') : ($editing ? t('inv.edit_title') : t('inv.new_title'));
      ?>
      <div class="card-header <?= $headerBg ?> d-flex flex-wrap justify-content-between align-items-center gap-2 py-3"
           id="invoiceFormHeader" data-title-add="<?= e(t('inv.new_title')) ?>" data-title-edit="<?= e(t('inv.edit_title')) ?>">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-receipt text-primary"></i>
          <h2 class="h6 mb-0" id="invoiceFormTitle"><?= $headerTitle ?></h2>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2"
             data-new-label="<?= e($previewNumber) ?>">
          <span class="fw-bold text-primary text-uppercase small"><?= t('inv.number') ?></span>
          <span class="text-secondary">|</span>
          <span class="fw-semibold" id="invoiceFormNumber"><?= $editingInvoice !== null ? e($invoiceNumber($editingInvoice)) : e($previewNumber) ?></span>
        </div>
      </div>

      <form method="post" action="/invoices" id="invoiceMainForm" class="card-body pt-3" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" id="invoice_id" value="<?= e((string) ($old['invoice_id'] ?? '')) ?>">
        <!-- Only the marker store() needs to tell a duplicate-completion
             create apart from an ordinary brand-new one (4.95) — round-trips
             through a failed-validation resubmit's own flashed $old too
             (see store()), so a rejected duplicate save keeps its
             bg-info-subtle tone/heading instead of reverting to plain "new". -->
        <input type="hidden" name="duplicate_of" value="<?= e((string) ($old['duplicate_of'] ?? '')) ?>">
        <input type="hidden" name="updated_at" value="<?= e((string) ($old['updated_at'] ?? '')) ?>">

        <?php require APP_PATH . '/Views/partials/invoice-header.php'; ?>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label"><?= t('inv.customer_info') ?></label>
            <div class="form-floating">
              <select class="form-select <?= $bad('customer_id') ?>" id="customer_id" name="customer_id" required
                      data-ds-select data-search-placeholder="<?= t('table.search') ?>"
                      data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>">
                <option value=""></option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" <?= $selected('customer_id', (string) $c['id']) ?>><?= e($c['customer_name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="customer_id"><?= t('inv.customer') ?> *</label>
            </div>
            <?php if (isset($errors['customer_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['customer_id']) ?></div><?php endif; ?>

            <div class="bg-primary-subtle rounded-3 p-3 mt-2"
                 id="customerInfoPanel"
                 data-customers="<?= e(json_encode($customersById, JSON_UNESCAPED_UNICODE)) ?>"
                 data-field-labels="<?= e(json_encode($customerFieldLabels, JSON_UNESCAPED_UNICODE)) ?>"
                 data-empty-text="<?= e(t('inv.customer_info_empty')) ?>">
            </div>
          </div>

          <div class="col-12">
            <?php if (isset($errors['items'])): ?>
              <div class="alert alert-danger py-2 small"><?= e($errors['items']) ?></div>
            <?php endif; ?>

            <div class="row g-2 text-secondary small mb-1 d-none d-md-flex">
              <div class="col"><?= t('inv.product') ?></div>
              <div class="col-md-2"><?= t('inv.quantity') ?></div>
              <div class="col-md-2"><?= t('prod.unit') ?></div>
              <div class="col-md-2"><?= t('inv.unit_price') ?> (<?= e(currency_symbol($currency)) ?>)</div>
              <div class="col-md-2"><?= t('inv.line_total') ?> (<?= e(currency_symbol($currency)) ?>)</div>
              <div class="col-auto"></div>
            </div>

            <div id="invoiceItems"
                 data-products="<?= e(json_encode(array_map(
                     static fn(array $p): array => ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (string) $p['unit_price'], 'unitId' => (int) $p['unit_id']],
                     $products
                 ), JSON_UNESCAPED_UNICODE)) ?>"
                 data-units="<?= e(json_encode(array_map(
                     static fn(array $u): array => ['id' => (int) $u['id'], 'name' => $u['name']],
                     $units
                 ), JSON_UNESCAPED_UNICODE)) ?>"
                 data-search-placeholder="<?= e(t('table.search')) ?>"
                 data-no-results="<?= e(t('table.empty')) ?>"
                 data-clear-label="<?= e(t('cust.clear_field')) ?>"
                 data-vat-rate="<?= e((string) $vatRate) ?>"
                 data-currency-symbol="<?= e(currency_symbol($currency)) ?>">
              <?php foreach ($itemRows as $i => $row): $itemRow($i, $row, $errors['items_' . $i] ?? null); endforeach; ?>
            </div>
<hr>
            <div class="row g-3 mt-1">
              <div class="col-md-8">
                <textarea class="form-control" name="notes" rows="4"
                          placeholder="<?= e(t('inv.notes')) ?>"><?= $val('notes') ?></textarea>
              </div>
              <div class="col-md-4">
                <div class="d-flex justify-content-between text-secondary small pb-2 border-bottom">
                  <span><?= t('inv.vat') ?> (<?= e($vatRateDisplay) ?>%):</span>
                  <span id="invoiceVat"><?= e(currency_symbol($currency)) ?> 0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-semibold pt-2">
                  <span><?= t('inv.grand_total') ?>:</span>
                  <span id="invoiceGrandTotal"><?= e(currency_symbol($currency)) ?> 0.00</span>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                  <button type="reset" class="btn btn-outline-secondary"><?= t('inv.reset') ?></button>
                  <button type="submit" class="btn btn-primary" id="invoiceSubmitBtn"
                          data-label-add="<?= e(t('inv.save')) ?>" data-label-update="<?= e(t('inv.update')) ?>">
                    <i class="bi bi-plus-lg me-1"></i><span id="invoiceSubmitLabel"><?= $editing || $duplicating ? t('inv.update') : t('inv.save') ?></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-3">
    <!-- action_save removed — it duplicated the form's own submit button
         with none of its functionality (4.40 in handoff.md). action_export_pdf
         submits the same form (form="invoiceMainForm", like the status
         select/checkboxes below already do) with an extra submit_action=
         export_pdf_signed/export_pdf_unsigned field (a dropdown of the two,
         4.63 in handoff.md, replacing one plain button) — InvoiceController::
         store() saves/updates exactly as normal, then redirects to the PDF
         (?sign=1 or 0) instead of back to /invoices, so one click both saves
         (or creates) the invoice and downloads it.
         action_preview opens the same modal orders.php's "ნახვა" uses
         (4.46 in handoff.md). For an invoice that already has a real id
         (editing one) it's a plain modal-trigger button, no submit
         involved. For a brand new, unsaved one there's nothing to preview
         yet, so it's the same submit_action=export_pdf trick instead —
         value="preview" saves/updates exactly like that button does, then
         InvoiceController::store() redirects to ?edit=N&preview=1, and this
         page's own JS (below) auto-clicks the now-real "გადახედვა" button
         (populated by the fresh ?edit=N load) once, on page load — same
         modal either way, this branch just needs one extra round trip to
         get a real id first.
         action_email opens #invoiceEmailModal, same two-state pattern as
         action_preview above (4.55 in handoff.md). action_share_link is
         the same two-state pattern again (4.68) — data-share-url instead
         of a modal trigger, ds_share_link_script() (helpers.php) does the
         actual copy-to-clipboard. action_whatsapp remains an unwired
         placeholder, per the original request. -->
    <div class="card ds-card mb-3">
      <div class="card-body d-grid gap-2">
        <div class="dropdown">
          <button type="button" class="btn btn-outline-secondary dropdown-toggle w-100" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-file-earmark-pdf me-1"></i><?= t('inv.action_export_pdf') ?>
          </button>
          <ul class="dropdown-menu w-100">
            <li>
              <button type="submit" form="invoiceMainForm" name="submit_action" value="export_pdf_signed"
                      class="dropdown-item <?= $editingInvoice === null ? 'js-confirm-create' : '' ?>">
                <i class="bi bi-pen me-1"></i><?= t('inv.export_signed') ?>
              </button>
            </li>
            <li>
              <button type="submit" form="invoiceMainForm" name="submit_action" value="export_pdf_unsigned"
                      class="dropdown-item <?= $editingInvoice === null ? 'js-confirm-create' : '' ?>">
                <i class="bi bi-file-earmark me-1"></i><?= t('inv.export_unsigned') ?>
              </button>
            </li>
          </ul>
        </div>
        <?php if ($editingInvoice !== null): ?>
          <button type="button" class="btn btn-outline-secondary" id="invoicePreviewTrigger" data-bs-toggle="modal" data-bs-target="#invoicePreviewModal"
                  data-invoice-id="<?= (int) $editingInvoice['id'] ?>"
                  data-invoice-number="<?= e($invoiceNumber($editingInvoice)) ?>"
                  data-invoice-document-state="<?= e($editingInvoice['document_state']) ?>">
            <i class="bi bi-eye me-1"></i><?= t('inv.action_preview') ?>
          </button>
        <?php else: ?>
          <button type="submit" form="invoiceMainForm" name="submit_action" value="preview" class="btn btn-outline-secondary js-confirm-create">
            <i class="bi bi-eye me-1"></i><?= t('inv.action_preview') ?>
          </button>
        <?php endif; ?>
        <?php if ($editingInvoice !== null): ?>
          <button type="button" class="btn btn-outline-secondary" id="invoiceEmailTrigger" data-bs-toggle="modal" data-bs-target="#invoiceEmailModal"
                  data-invoice-id="<?= (int) $editingInvoice['id'] ?>">
            <i class="bi bi-envelope me-1"></i><?= t('inv.action_email') ?>
          </button>
        <?php else: ?>
          <button type="submit" form="invoiceMainForm" name="submit_action" value="email" class="btn btn-outline-secondary js-confirm-create">
            <i class="bi bi-envelope me-1"></i><?= t('inv.action_email') ?>
          </button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-whatsapp me-1"></i><?= t('inv.action_whatsapp') ?></button>
        <?php if ($editingInvoice !== null): ?>
          <button type="button" class="btn btn-outline-secondary" id="invoiceShareLinkTrigger" data-share-url="<?= e($shareUrl) ?>">
            <i class="bi bi-link-45deg me-1"></i><?= t('inv.action_share_link') ?>
          </button>
        <?php else: ?>
          <button type="submit" form="invoiceMainForm" name="submit_action" value="share_link" class="btn btn-outline-secondary js-confirm-create">
            <i class="bi bi-link-45deg me-1"></i><?= t('inv.action_share_link') ?>
          </button>
        <?php endif; ?>
        <?php if ($editingInvoice !== null): ?>
          <!-- Only once there's a real, saved invoice to copy from — unlike
               preview/email/share_link above, duplicating a not-yet-saved
               new invoice makes no sense, so this has no submit_action
               fallback branch for that case, it's just absent instead.
               A plain GET link (?duplicate=N, 4.96) — same navigation, not
               a form submit, as the edit pencil's own ?edit=N link. -->
          <a href="/invoices?duplicate=<?= (int) $editingInvoice['id'] ?>" class="btn btn-outline-secondary w-100">
            <i class="bi bi-copy me-1"></i><?= t('inv.action_duplicate') ?>
          </a>
        <?php endif; ?>

        <hr class="my-1">

        <?php
          $documentState = (string) ($old['document_state'] ?? '');
          if (!in_array($documentState, \App\Models\Invoice::DOCUMENT_STATES, true)) {
              $documentState = \App\Models\Invoice::DOCUMENT_STATES[0]; // 'draft'
          }
        ?>
        <label class="form-label mb-1"><?= t('inv.status_label') ?></label>
        <input type="hidden" id="invoice_document_state" name="document_state" form="invoiceMainForm" value="<?= e($documentState) ?>">
        <?php foreach (\App\Models\Invoice::DOCUMENT_STATES as $s): ?>
          <div class="form-check form-switch">
            <input class="form-check-input document-state-toggle" type="checkbox" id="status_<?= $s ?>"
                   data-status-value="<?= $s ?>" <?= $documentState === $s ? 'checked' : '' ?>>
            <label class="form-check-label" for="status_<?= $s ?>"><?= t('inv.status_' . $s) ?></label>
          </div>
        <?php endforeach; ?>

        <div class="form-check form-switch mt-1">
          <input class="form-check-input" type="checkbox" id="invoice_zero" name="is_zero" form="invoiceMainForm" <?= !empty($old['is_zero']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="invoice_zero"><?= t('inv.flag_zero') ?></label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="invoice_recurring" name="is_recurring" form="invoiceMainForm" <?= !empty($old['is_recurring']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="invoice_recurring"><?= t('inv.flag_recurring') ?></label>
        </div>

        <?php if ($workflow !== null):
          $wfInvoiceId = (int) $editingInvoice['id'];
          $wfRedirect  = '/invoices?edit=' . $wfInvoiceId;
          $paymentBadgeClass = [
              'unpaid'  => 'bg-danger-subtle text-danger-emphasis',
              'partial' => 'bg-warning-subtle text-warning-emphasis',
              'paid'    => 'bg-success-subtle text-success-emphasis',
          ];
        ?>
        <hr class="my-1">
        <div class="d-flex align-items-center gap-2">
          <span class="text-secondary small"><?= t('workflow.label') ?>:</span>
          <?php if ($workflow['cancelled_at'] !== null): ?>
            <span class="badge rounded-pill bg-dark-subtle text-dark-emphasis"><?= t('workflow.cancelled_label') ?></span>
          <?php else: ?>
            <span class="badge rounded-pill <?= $paymentBadgeClass[$workflow['payment_state']] ?>">
              <?= t('workflow.payment_' . $workflow['payment_state']) ?>
            </span>
          <?php endif; ?>
        </div>

        <form method="post" action="/invoice-workflow/payment" class="d-flex gap-1">
          <?= csrf_field() ?>
          <input type="hidden" name="invoice_id" value="<?= $wfInvoiceId ?>">
          <input type="hidden" name="redirect" value="<?= e($wfRedirect) ?>">
          <select class="form-select form-select-sm" name="payment_state" data-ds-select
                  data-search-placeholder="<?= t('table.search') ?>" data-no-results="<?= t('table.empty') ?>"
                  data-clear-label="<?= t('cust.clear_field') ?>">
            <option value="unpaid" <?= $workflow['payment_state'] === 'unpaid' ? 'selected' : '' ?>><?= t('workflow.payment_unpaid') ?></option>
            <option value="partial" <?= $workflow['payment_state'] === 'partial' ? 'selected' : '' ?>><?= t('workflow.payment_partial') ?></option>
            <option value="paid" <?= $workflow['payment_state'] === 'paid' ? 'selected' : '' ?>><?= t('workflow.payment_paid') ?></option>
          </select>
          <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="paid_amount" value="<?= e($workflow['paid_amount']) ?>">
          <button type="submit" class="btn btn-sm btn-outline-secondary flex-shrink-0"><i class="bi bi-check-lg"></i></button>
        </form>

        <form method="post" action="/invoice-workflow/<?= $workflow['cancelled_at'] !== null ? 'uncancel' : 'cancel' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="invoice_id" value="<?= $wfInvoiceId ?>">
          <input type="hidden" name="redirect" value="<?= e($wfRedirect) ?>">
          <button type="submit" class="btn btn-sm btn-outline-<?= $workflow['cancelled_at'] !== null ? 'secondary' : 'danger' ?> w-100">
            <?= $workflow['cancelled_at'] !== null ? t('workflow.uncancel') : t('workflow.cancel') ?>
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card ds-card">
      <div class="card-header bg-transparent py-3">
        <h2 class="h6 mb-0"><?= t('inv.customer_invoices') ?></h2>
      </div>
      <div class="card-body"
           id="customerInvoicesPanel"
           data-invoices="<?= e(json_encode($invoicesByCustomer, JSON_UNESCAPED_UNICODE)) ?>"
           data-empty-text="<?= e(t('inv.customer_invoices_empty')) ?>">
      </div>
    </div>
  </div>
</div>

<!-- "ეს მოქმედება ჯერ შეინახავს ინვოისს" — the action-panel's own
     PDF-export/გადახედვა/მეილზე-გაგზავნა/ბმულის-გაზიარება buttons all
     submit_action=... the whole main form when there's no real invoice
     yet ($editingInvoice === null — a brand new one, or a staged
     "დუბლირება" copy, 4.94), silently creating it before doing whatever
     was actually clicked. user's own explicit request: ask first. Same
     "own header/footer chrome" convention as invoiceEmailModal above.
     JS below (js-confirm-create) intercepts every button carrying that
     class, stashes which submit_action it was, and only actually submits
     once "დიახ, შევქმნათ" is clicked. -->
<div class="modal fade" id="invoiceConfirmCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-info-circle text-primary"></i>
          <span class="fw-bold text-primary small text-uppercase"><?= t('inv.confirm_create_title') ?></span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="<?= t('inv.close') ?>"></button>
      </div>
      <div class="modal-body">
        <?= t('inv.confirm_create_body') ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('inv.close') ?></button>
        <button type="button" class="btn btn-primary" id="invoiceConfirmCreateBtn"><?= t('inv.confirm_create_confirm') ?></button>
      </div>
    </div>
  </div>
</div>

<?php require APP_PATH . '/Views/partials/invoice-preview-modal.php'; ?>

<!-- Same "own header/footer chrome" polish as invoice-preview-modal.php
     (bg-light header, icon+label instead of Bootstrap's plain modal-title)
     instead of a stock Bootstrap modal — the attachment/signature notes
     moved from two bare lines into a light info panel, same convention as
     ds-card (4.65 in handoff.md, a pure redesign, no behavior changed). -->
<div class="modal fade" id="invoiceEmailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="/invoices/send-email" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="invoice_id" id="emailInvoiceId">
      <div class="modal-header bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-envelope-paper text-primary"></i>
          <span class="fw-bold text-primary small text-uppercase"><?= t('inv.email_modal_title') ?></span>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="<?= t('inv.close') ?>"></button>
      </div>
      <?php
        // Signature lines shown below, exactly as emails/invoice.php (4.66)
        // itself computes them — so what's previewed here is what actually
        // gets sent, not a lookalike drawn separately.
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

        <!-- Live mockup of emails/invoice.php's own layout (4.66) — the
             textarea sits inside the same blue-header/white-card/signature
             shape the actual email renders in, so composing here already
             shows what the recipient will see, not a generic form. -->
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
$scripts = ds_invoice_preview_script() . ds_share_link_script()
    . ds_flash_toast($created !== null ? t('inv.created', e($created)) : null)
    . ds_flash_toast($updated !== null ? t('inv.updated', e($updated)) : null)
    . ds_flash_toast($emailSent !== null ? t('inv.email_sent', e($emailSent)) : null)
    . ds_flash_toast($emailFailed !== null ? t('inv.email_failed', e($emailFailed)) : null, 'warning', 'bi-exclamation-triangle-fill')
    . ds_flash_toast(isset($errors['conflict']) ? e($errors['conflict']) : null, 'warning', 'bi-exclamation-triangle-fill')
    . <<<'HTML'

<script>
(() => {
  // Status switches — draft/final, exactly one checked, mirrored into the
  // hidden #invoice_document_state.
  const documentToggles = document.querySelectorAll('.document-state-toggle');
  const documentInput   = document.getElementById('invoice_document_state');

  function wireExclusiveGroup(toggles, hiddenInput) {
    toggles.forEach((box) => {
      box.addEventListener('change', () => {
        if (!box.checked) { box.checked = true; return; } // can't leave zero active
        toggles.forEach((other) => { if (other !== box) other.checked = false; });
        hiddenInput.value = box.dataset.statusValue;
      });
    });
  }
  wireExclusiveGroup(documentToggles, documentInput);

  const container   = document.getElementById('invoiceItems');
  const grandTotalEl = document.getElementById('invoiceGrandTotal');
  const vatEl        = document.getElementById('invoiceVat');
  const products    = JSON.parse(container.dataset.products);
  const units       = JSON.parse(container.dataset.units);
  const searchPh    = container.dataset.searchPlaceholder;
  const noResults   = container.dataset.noResults;
  const clearLabel  = container.dataset.clearLabel;
  const vatRate     = parseFloat(container.dataset.vatRate) || 0;
  const currencySym = container.dataset.currencySymbol;

  const customerInfoPanel = document.getElementById('customerInfoPanel');
  const customersById  = customerInfoPanel ? JSON.parse(customerInfoPanel.dataset.customers) : {};
  const customerLabels = customerInfoPanel ? JSON.parse(customerInfoPanel.dataset.fieldLabels) : {};
  const customerInfoEmptyText = customerInfoPanel?.dataset.emptyText ?? '';
  const CUSTOMER_INFO_FIELDS = ['customer_taxid', 'customer_contact', 'customer_phone', 'customer_email', 'customer_address', 'customer_info'];

  // "მეილზე გაგზავნა" modal — reuses customersById (already built above for
  // the customer-info panel) to prefill "to", instead of a separate
  // data-invoice-* attribute. Only fills it when empty, so a failed
  // submit's own $emailOld value (server-rendered into the input) is never
  // clobbered on reopen.
  document.getElementById('invoiceEmailModal')?.addEventListener('show.bs.modal', (event) => {
    const btn = event.relatedTarget;
    document.getElementById('emailInvoiceId').value = btn?.dataset.invoiceId ?? '';
    const toInput = document.getElementById('emailTo');
    if (!toInput.value) {
      const customerId = document.getElementById('customer_id').value;
      toInput.value = customersById[customerId]?.customer_email ?? '';
    }
  });

  // Re-rendered from scratch on every selection change, not just toggled —
  // simplest way to guarantee it never shows a stale customer's details.
  function renderCustomerInfo(customerId) {
    if (!customerInfoPanel) return;
    const c = customersById[customerId];
    if (!c) {
      customerInfoPanel.innerHTML = `<div class="text-secondary small text-center py-2">${escapeHtml(customerInfoEmptyText)}</div>`;
      return;
    }
    let html = `<div class="fw-bold text-primary mb-2">${escapeHtml(c.customer_name)}</div>`;
    CUSTOMER_INFO_FIELDS.forEach((field) => {
      const value = c[field];
      // '0' in customer_taxid means "no tax id" (see app/Models/Customer.php) — same as empty here.
      if (!value || (field === 'customer_taxid' && value === '0')) return;
      html += `<div class="small mb-1"><span class="fw-semibold">${escapeHtml(customerLabels[field])}:</span> ${escapeHtml(value)}</div>`;
    });
    customerInfoPanel.innerHTML = html;
  }

  const customerInvoicesPanel = document.getElementById('customerInvoicesPanel');
  const invoicesByCustomer = customerInvoicesPanel ? JSON.parse(customerInvoicesPanel.dataset.invoices) : {};
  const customerInvoicesEmptyText = customerInvoicesPanel?.dataset.emptyText ?? '';

  function renderCustomerInvoices(customerId) {
    if (!customerInvoicesPanel) return;
    const list = invoicesByCustomer[customerId] ?? [];
    if (list.length === 0) {
      customerInvoicesPanel.innerHTML = `<div class="text-secondary small text-center py-2">${escapeHtml(customerInvoicesEmptyText)}</div>`;
      return;
    }
    customerInvoicesPanel.innerHTML = list.map((inv) =>
      `<div class="d-flex justify-content-between small mb-1"><span>${escapeHtml(inv.number)}</span><span class="fw-semibold">${escapeHtml(inv.total)}</span></div>`
    ).join('');
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function productOptionsHtml() {
    return '<option value=""></option>' + products.map((p) =>
      `<option value="${p.id}" data-price="${p.price}" data-unit="${p.unitId}">${escapeHtml(p.name)}</option>`
    ).join('');
  }

  function unitOptionsHtml() {
    return '<option value=""></option>' + units.map((u) =>
      `<option value="${u.id}">${escapeHtml(u.name)}</option>`
    ).join('');
  }

  function rowHtml() {
    return `
      <div class="row g-2 mb-2 align-items-center" data-item-row>
        <div class="col">
          <select class="form-select" name="item_product_id[]" data-ds-select
                  data-search-placeholder="${searchPh}" data-no-results="${noResults}"
                  data-clear-label="${clearLabel}" data-item-product>
            ${productOptionsHtml()}
          </select>
        </div>
        <div class="col-md-2">
          <input type="number" step="0.001" min="0" class="form-control" name="item_quantity[]" data-item-quantity>
        </div>
        <div class="col-md-2">
          <select class="form-select" name="item_unit_id[]" data-ds-select
                  data-search-placeholder="${searchPh}" data-no-results="${noResults}"
                  data-clear-label="${clearLabel}" data-item-unit>
            ${unitOptionsHtml()}
          </select>
        </div>
        <div class="col-md-2">
          <input type="number" step="0.01" min="0" class="form-control" name="item_unit_price[]" data-item-price>
        </div>
        <div class="col-md-2">
          <input type="text" class="form-control text-end fw-semibold" readonly tabindex="-1" value="0.00" data-item-line-total>
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-outline-secondary btn-sm invoice-item-remove" tabindex="-1"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>`;
  }

  function updateRowTotal(row) {
    const qty   = parseFloat(row.querySelector('[data-item-quantity]').value) || 0;
    const price = parseFloat(row.querySelector('[data-item-price]').value) || 0;
    row.querySelector('[data-item-line-total]').value = (qty * price).toFixed(2);
    updateGrandTotal();
  }

  function updateGrandTotal() {
    let sum = 0;
    container.querySelectorAll('[data-item-row]').forEach((row) => {
      sum += parseFloat(row.querySelector('[data-item-line-total]').value) || 0;
    });
    // sum is already VAT-inclusive (see the org.vat_rate docblock note above)
    // — this extracts how much of it is VAT, it doesn't add anything on top:
    // vat = total * rate / (100 + rate), not total * rate / 100.
    vatEl.textContent = currencySym + ' ' + (sum * (vatRate / (100 + vatRate))).toFixed(2);
    grandTotalEl.textContent = currencySym + ' ' + sum.toFixed(2);
  }

  function addRow(values) {
    container.insertAdjacentHTML('beforeend', rowHtml());
    const row = container.lastElementChild;
    const select = row.querySelector('[data-item-product]');
    const unitSelect = row.querySelector('[data-item-unit]');
    if (window.DsSelect) {
      select.dsSelect = new window.DsSelect(select);
      unitSelect.dsSelect = new window.DsSelect(unitSelect);
    }

    if (values) {
      select.value = values.product_id;
      select.dsSelect?.refresh();
      row.querySelector('[data-item-quantity]').value = values.quantity;
      row.querySelector('[data-item-price]').value = values.unit_price;
    }
    updateRowTotal(row);
    return row;
  }

  container.addEventListener('change', (event) => {
    const select = event.target.closest('[data-item-product]');
    if (!select) return;
    const row = select.closest('[data-item-row]');
    const option = select.selectedOptions[0];
    if (option && option.value !== '') {
      row.querySelector('[data-item-price]').value = option.dataset.price;
      const unitSelect = row.querySelector('[data-item-unit]');
      unitSelect.value = option.dataset.unit;
      unitSelect.dsSelect?.refresh();
    }
    updateRowTotal(row);

    if (row === container.lastElementChild && select.value !== '') addRow();
  });

  container.addEventListener('input', (event) => {
    if (event.target.matches('[data-item-quantity], [data-item-price]')) {
      updateRowTotal(event.target.closest('[data-item-row]'));
    }
  });

  container.addEventListener('click', (event) => {
    const btn = event.target.closest('.invoice-item-remove');
    if (!btn) return;
    const row = btn.closest('[data-item-row]');
    if (container.children.length > 1) {
      row.remove();
    } else {
      row.querySelector('[data-item-product]').value = '';
      row.querySelector('[data-item-product]').dsSelect?.refresh();
      row.querySelector('[data-item-quantity]').value = '';
      row.querySelector('[data-item-unit]').value = '';
      row.querySelector('[data-item-unit]').dsSelect?.refresh();
      row.querySelector('[data-item-price]').value = '';
      updateRowTotal(row);
    }
    updateGrandTotal();
  });

  container.querySelectorAll('[data-item-row]').forEach(updateRowTotal);

  // "გასუფთავება" drops an ?edit=N-loaded invoice back to a blank "add new" state.
  (() => {
    const form  = document.querySelector('#invoice-form form');
    const idInput        = document.getElementById('invoice_id');
    const updatedAtInput = document.querySelector('input[name="updated_at"]');
    const customerSelect = document.getElementById('customer_id');
    const submitBtn = document.getElementById('invoiceSubmitBtn');
    const labelSpan = document.getElementById('invoiceSubmitLabel');
    const numberSpan = document.getElementById('invoiceFormNumber');
    const meta = numberSpan.closest('[data-new-label]');
    const formHeader = document.getElementById('invoiceFormHeader');
    const formTitle  = document.getElementById('invoiceFormTitle');
    if (!form) return;

    form.addEventListener('reset', () => {
      idInput.value = '';
      updatedAtInput.value = '';
      labelSpan.textContent = submitBtn.dataset.labelAdd;
      numberSpan.textContent = meta.dataset.newLabel;
      // Either of the two non-default tones (4.94's own bg-info-subtle for
      // a staged "დუბლირება" copy, or the existing bg-warning-subtle for a
      // real edit) — reset always drops back to the plain blank-add look.
      formHeader.classList.remove('bg-warning-subtle', 'bg-info-subtle');
      formHeader.classList.add('bg-transparent');
      formTitle.textContent = formHeader.dataset.titleAdd;
      document.getElementById('status_draft').checked = true;
      document.getElementById('status_final').checked = false;
      document.getElementById('invoice_document_state').value = 'draft';
      setTimeout(() => {
        customerSelect.dsSelect?.refresh();
        container.innerHTML = '';
        addRow();
        updateGrandTotal();
        renderCustomerInfo('');
        renderCustomerInvoices('');
      }, 0);
    });

    customerSelect.addEventListener('change', () => {
      renderCustomerInfo(customerSelect.value);
      renderCustomerInvoices(customerSelect.value);
    });
    renderCustomerInfo(customerSelect.value);
    renderCustomerInvoices(customerSelect.value);
  })();
})();
</script>
HTML;

// After a "გადახედვა"-triggered save (submit_action=preview above),
// InvoiceController::store() redirects here with ?preview=1 — this
// auto-clicks the now-real, populated #invoicePreviewTrigger button once,
// the same way a tenant clicking it themselves would. history.replaceState
// drops the query flag so a manual refresh of this URL doesn't re-open it.
if (isset($_GET['preview']) && $editingInvoice !== null) {
    $scripts .= <<<'HTML'

<script>
document.getElementById('invoicePreviewTrigger')?.click();
history.replaceState(null, '', location.pathname + location.search.replace(/[?&]preview=1/, ''));
</script>
HTML;
}

// Same trick for "მეილზე გაგზავნა" — a save-then-redirect (?email=1, after
// submit_action=email on an unsaved invoice) *or* a failed send/validation
// (InvoiceController::sendEmail() flashes email_errors and redirects with
// the same flag) both need the modal open again on load, not just a plain
// page. $emailErrors reopening (not just ?email=1) matters here: the flag
// isn't in the URL on that path, sendEmail() put it there itself.
if (($editingInvoice !== null) && (isset($_GET['email']) || $emailErrors !== [])) {
    $scripts .= <<<'HTML'

<script>
document.getElementById('invoiceEmailTrigger')?.click();
history.replaceState(null, '', location.pathname + location.search.replace(/[?&]email=1/, ''));
</script>
HTML;
}

// "ეს მოქმედება ჯერ შეინახავს ინვოისს" (see invoiceConfirmCreateModal
// above) — every .js-confirm-create button is a real type="submit" with
// its own name="submit_action" value, so clicking it directly would
// submit (create) immediately; intercepted here instead, the value is
// only actually attached to the form (as a plain hidden input — a
// script-invoked requestSubmit() carries no "which button" info of its
// own) once "დიახ, შევქმნათ" is clicked. Nothing to wire when editing a
// real invoice — none of those buttons carry the class then.
$scripts .= <<<'HTML'

<script>
(() => {
  const form = document.getElementById('invoiceMainForm');
  const modalEl = document.getElementById('invoiceConfirmCreateModal');
  const confirmBtn = document.getElementById('invoiceConfirmCreateBtn');
  const triggers = document.querySelectorAll('.js-confirm-create');
  if (!form || !modalEl || !confirmBtn || triggers.length === 0) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  let pendingAction = null;

  triggers.forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      pendingAction = btn.value;
      modal.show();
    });
  });

  confirmBtn.addEventListener('click', () => {
    if (!pendingAction) return;
    let hidden = form.querySelector('input[name="submit_action"]');
    if (!hidden) {
      hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'submit_action';
      form.appendChild(hidden);
    }
    hidden.value = pendingAction;
    modal.hide();
    form.requestSubmit();
  });
})();
</script>
HTML;
?>
