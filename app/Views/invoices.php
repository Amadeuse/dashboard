<?php
/**
 * @var array   $customers      every customer, for the picker
 * @var array   $products       every product, for each line's picker (id/name/unit_price)
 * @var array   $org            the organization row (Organization::get()), for partials/invoice-header.php
 * @var string  $invoicePrefix  organization.invoice_prefix, or "INV" if unset
 * @var array   $invoicesByCustomer  customer_id => list of ['number','total'], for the right column's history panel
 * @var ?array  $editingInvoice the invoice being edited (?edit=N in the URL, or a failed resubmit), or null when adding
 * @var array   $errors         field => message, from the failed POST ('items_N' per line, 'items' if none)
 * @var array   $old            field => value, so a rejected form (or ?edit=N) comes back filled
 * @var ?string $created        formatted number ("PH 2026-08-13 0007") of the invoice just added
 * @var ?string $updated        formatted number of the invoice just edited
 *
 * Create/edit only — the list lives on its own page now (/orders, see
 * orders.php), reached from the sidebar under შეკვეთები > ყველა შეკვეთა.
 * Editing an existing invoice from there means a real navigation
 * (?edit=N), not a same-page row click: InvoiceController::index() loads
 * that invoice into $old exactly like a failed-validation resubmit would,
 * so every bit of the rendering below (customer select, item rows, the
 * number/date line) needed zero new branching — it already knew how to
 * redraw itself from $old.
 *
 * Line items are the same repeatable-row UX as organization.php's IBAN
 * accounts (4.24 in handoff.md) — a product/qty/price row that auto-adds a
 * fresh empty one the moment its product is picked, generalized from one
 * input to a row of three plus a computed total.
 */
$editing = ($old['invoice_id'] ?? '') !== '';

$val      = static fn(string $f): string => e((string) ($old[$f] ?? ''));
$bad      = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$selected = static fn(string $f, string $optionValue): string
    => ((string) ($old[$f] ?? '')) === $optionValue ? 'selected' : '';

/** "{prefix} {issue_date} {0007}" — the same format \App\Models\Invoice::number() writes everywhere else. */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);
$uploadUrl = '/assets/uploads/organization/';

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
            'quantity'   => (string) (((array) ($old['item_quantity'] ?? []))[$i] ?? ''),
            'unit_price' => (string) (((array) ($old['item_unit_price'] ?? []))[$i] ?? ''),
        ];
    }
}
// Always leave one blank row to type into — a failed resubmit's own trailing
// blank row already round-trips through $old as-is, this only kicks in for
// a fresh ?edit=N load, where every row PHP built above is a real item.
if ($itemRows === [] || end($itemRows)['product_id'] !== '') {
    $itemRows[] = ['product_id' => '', 'quantity' => '', 'unit_price' => ''];
}

/** One product/qty/price/total line — used for both the initial render and the JS-built rows share this shape (see rowHtml() below). */
$itemRow = static function (int $i, array $row, ?string $err) use ($products): void {
    $productId = $row['product_id']; ?>
  <div class="row g-2 mb-2 align-items-center" data-item-row>
    <div class="col">
      <select class="form-select <?= $err ? 'is-invalid' : '' ?>" name="item_product_id[]"
              data-ds-select data-search-placeholder="<?= t('table.search') ?>"
              data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>"
              data-item-product>
        <option value=""></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int) $p['id'] ?>" data-price="<?= e((string) $p['unit_price']) ?>"
                  <?= $productId === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="number" step="0.001" min="0" class="form-control <?= $err ? 'is-invalid' : '' ?>"
             name="item_quantity[]" value="<?= e($row['quantity']) ?>" data-item-quantity>
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
    <h1 class="h3 fw-bold mb-0"><?= t('page.invoices') ?></h1>
  </div>
</div>

<?php if ($created !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('inv.created', e($created)) ?>
  </div>
<?php endif; ?>
<?php if ($updated !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('inv.updated', e($updated)) ?>
  </div>
<?php endif; ?>
<?php if (isset($errors['conflict'])): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= e($errors['conflict']) ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-9">
    <div class="card ds-card" id="invoice-form">
      <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-receipt text-primary"></i>
          <h2 class="h6 mb-0"><?= t('inv.new_title') ?></h2>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2"
             data-today-formatted="<?= e(date('Y-m-d')) ?>" data-new-label="<?= e(t('inv.new_number_pending')) ?>">
          <span class="fw-bold text-primary text-uppercase small"><?= t('inv.number') ?></span>
          <span class="text-secondary">|</span>
          <span class="fw-semibold" id="invoiceFormNumber"><?= $editingInvoice !== null ? e($invoiceNumber($editingInvoice)) : t('inv.new_number_pending') ?></span>
          <span class="text-secondary ms-2" id="invoiceFormDate"><?= $editingInvoice !== null ? '' : e(date('Y-m-d')) ?></span>
        </div>
      </div>

      <form method="post" action="/invoices" id="invoiceMainForm" class="card-body pt-3" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" id="invoice_id" value="<?= e((string) ($old['invoice_id'] ?? '')) ?>">
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
              <div class="col-md-2"><?= t('inv.unit_price') ?></div>
              <div class="col-md-2"><?= t('inv.line_total') ?></div>
              <div class="col-auto"></div>
            </div>

            <div id="invoiceItems"
                 data-products="<?= e(json_encode(array_map(
                     static fn(array $p): array => ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (string) $p['unit_price']],
                     $products
                 ), JSON_UNESCAPED_UNICODE)) ?>"
                 data-search-placeholder="<?= e(t('table.search')) ?>"
                 data-no-results="<?= e(t('table.empty')) ?>"
                 data-clear-label="<?= e(t('cust.clear_field')) ?>">
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
                  <span><?= t('inv.vat') ?> (18%):</span>
                  <span id="invoiceVat">0.00</span>
                </div>
                <div class="d-flex justify-content-between fw-semibold pt-2">
                  <span><?= t('inv.grand_total') ?>:</span>
                  <span id="invoiceGrandTotal">0.00</span>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                  <button type="reset" class="btn btn-outline-secondary"><?= t('inv.reset') ?></button>
                  <button type="submit" class="btn btn-primary" id="invoiceSubmitBtn"
                          data-label-add="<?= e(t('inv.save')) ?>" data-label-update="<?= e(t('inv.update')) ?>">
                    <i class="bi bi-plus-lg me-1"></i><span id="invoiceSubmitLabel"><?= $editing ? t('inv.update') : t('inv.save') ?></span>
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
    <!-- No functionality wired yet, per request — plain buttons/inputs, nothing submits or calls anything. -->
    <div class="card ds-card mb-3">
      <div class="card-body d-grid gap-2">
        <button type="button" class="btn btn-primary"><i class="bi bi-save me-1"></i><?= t('inv.action_save') ?></button>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i><?= t('inv.action_export_pdf') ?></button>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i><?= t('inv.action_preview') ?></button>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-envelope me-1"></i><?= t('inv.action_email') ?></button>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-whatsapp me-1"></i><?= t('inv.action_whatsapp') ?></button>
        <button type="button" class="btn btn-outline-secondary"><i class="bi bi-link-45deg me-1"></i><?= t('inv.action_share_link') ?></button>

        <hr class="my-1">

        <div class="form-floating">
          <select class="form-select" id="invoice_status" name="status" form="invoiceMainForm">
            <option value="draft" <?= $selected('status', 'draft') ?>><?= t('inv.status_draft') ?></option>
            <option value="final" <?= $selected('status', 'final') ?>><?= t('inv.status_final') ?></option>
            <option value="due" <?= $selected('status', 'due') ?>><?= t('inv.status_due') ?></option>
            <option value="paid" <?= $selected('status', 'paid') ?>><?= t('inv.status_paid') ?></option>
          </select>
          <label for="invoice_status"><?= t('inv.status_label') ?></label>
        </div>

        <div class="form-check form-switch mt-1">
          <input class="form-check-input" type="checkbox" id="invoice_zero" name="is_zero" form="invoiceMainForm" <?= !empty($old['is_zero']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="invoice_zero"><?= t('inv.flag_zero') ?></label>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="invoice_recurring" name="is_recurring" form="invoiceMainForm" <?= !empty($old['is_recurring']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="invoice_recurring"><?= t('inv.flag_recurring') ?></label>
        </div>
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

<?php
$scripts = <<<'HTML'

<script>
(() => {
  const container   = document.getElementById('invoiceItems');
  const grandTotalEl = document.getElementById('invoiceGrandTotal');
  const vatEl        = document.getElementById('invoiceVat');
  const products    = JSON.parse(container.dataset.products);
  const searchPh    = container.dataset.searchPlaceholder;
  const noResults   = container.dataset.noResults;
  const clearLabel  = container.dataset.clearLabel;

  const customerInfoPanel = document.getElementById('customerInfoPanel');
  const customersById  = customerInfoPanel ? JSON.parse(customerInfoPanel.dataset.customers) : {};
  const customerLabels = customerInfoPanel ? JSON.parse(customerInfoPanel.dataset.fieldLabels) : {};
  const customerInfoEmptyText = customerInfoPanel?.dataset.emptyText ?? '';
  const CUSTOMER_INFO_FIELDS = ['customer_taxid', 'customer_contact', 'customer_phone', 'customer_email', 'customer_address', 'customer_info'];

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
      `<option value="${p.id}" data-price="${p.price}">${escapeHtml(p.name)}</option>`
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
    vatEl.textContent = (sum * 0.18).toFixed(2);
    grandTotalEl.textContent = sum.toFixed(2);
  }

  function addRow(values) {
    container.insertAdjacentHTML('beforeend', rowHtml());
    const row = container.lastElementChild;
    const select = row.querySelector('[data-item-product]');
    if (window.DsSelect) select.dsSelect = new window.DsSelect(select);

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
    if (option && option.value !== '') row.querySelector('[data-item-price]').value = option.dataset.price;
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
    const dateSpan   = document.getElementById('invoiceFormDate');
    const meta = numberSpan.closest('[data-today-formatted]');
    if (!form) return;

    form.addEventListener('reset', () => {
      idInput.value = '';
      updatedAtInput.value = '';
      labelSpan.textContent = submitBtn.dataset.labelAdd;
      numberSpan.textContent = meta.dataset.newLabel;
      dateSpan.textContent = meta.dataset.todayFormatted;
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
?>
