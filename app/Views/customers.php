<?php
/**
 * @var array  $rows     every customer, newest first — ds-table pages them client-side
 * @var int    $total    row count
 * @var array  $errors   field => message, from the failed POST
 * @var array  $old      field => value, so a rejected form comes back filled
 * @var ?string $created name of the customer just added
 * @var ?string $updated name of the customer just edited
 *
 * The form is open on load (adding is what this page is for) but collapsible,
 * so a long list can be scanned without scrolling past it every time.
 *
 * Editing: clicking a row in the list (JS, bottom of this file) copies that
 * row's data into these same fields and sets #customer_id, so submitting the
 * one form either creates or updates depending on whether that id is set —
 * see CustomerController::store(). $editing below only covers the case where
 * an edit is submitted, fails validation, and the form must redraw as an edit.
 */
$editing = ($old['customer_id'] ?? '') !== '';

/** Field name → value, preferring what the user just typed. */
$val = static fn(string $f): string => e((string) ($old[$f] ?? ''));
/** Bootstrap invalid state + message for one field. */
$bad = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';

/**
 * One floating-label field (vendor/floating-label). placeholder=" " is what makes
 * :placeholder-shown work — the label floats and the clear button appears off it.
 * The error is printed with d-block instead of relying on the sibling selector,
 * because .invalid-feedback must sit outside .form-floating: the label is
 * height:100% of that box and a message inside it would stretch the label.
 *
 * $append is trailing HTML — a button, an addon. It turns the field into an
 * .input-group with .form-floating as its first child, which is the layout
 * vendor/floating-label was written against, so Bootstrap squares off the inner
 * corners on its own.
 */
$field = static function (string $name, string $label, string $type = 'text', string $extra = '', string $append = '')
    use ($val, $bad, $errors): void { ?>
  <?php if ($append !== ''): ?><div class="input-group"><?php endif; ?>
  <div class="form-floating">
    <input type="<?= $type ?>" class="form-control <?= $bad($name) ?>" id="<?= $name ?>"
           name="<?= $name ?>" value="<?= $val($name) ?>" placeholder=" " <?= $extra ?>>
    <label for="<?= $name ?>"><?= $label ?></label>
    <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
  </div>
  <?= $append ?>
  <?php if ($append !== ''): ?></div><?php endif; ?>
  <?php if (isset($errors[$name])): ?>
    <div class="invalid-feedback d-block"><?= e($errors[$name]) ?></div>
  <?php endif;
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.customers') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">
      <?= t('page.customers') ?>
      <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
    </h1>
  </div>
</div>

<?php if ($created !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('cust.created', e($created)) ?>
  </div>
<?php endif; ?>
<?php if ($updated !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('cust.updated', e($updated)) ?>
  </div>
<?php endif; ?>

<!-- ---- New customer ---- -->
<details class="card ds-card mb-3" id="customer-form" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-person-plus-fill text-primary"></i>
    <h2 class="h6 mb-0"><?= t('cust.new_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <form method="post" action="/customers" class="card-body pt-3" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="customer_id" id="customer_id" value="<?= e((string) ($old['customer_id'] ?? '')) ?>">
    <div class="row g-3">
      

    <div class="col-md-4">
      <?php $field('customer_taxid', t('cust.taxid'), 'text', 'maxlength="64" inputmode="numeric"',
        '<button class="btn btn-outline-primary" type="button" id="taxidLookup"'
        . ' title="' . t('cust.taxid_lookup') . '"><i class="gb_rs fs-5"></i></button>'); ?>
        <div class="form-text"><?= t('cust.taxid.rs') ?></div>
    </div>


  <div class="col-md-8">
    
<div class="row">
      <div class="col-md-8 mb-3">
        <?php $field('customer_name', t('cust.name') . ' *', 'text', 'maxlength="255" required'); ?>
        <!-- <div class="form-text"><?= t('cust.name_hint') ?></div> -->
      </div>

      <div class="col-md-4">
        <?php $field('customer_contact', t('cust.contact'), 'text', 'maxlength="255"'); ?>
      </div>
</div>
<div class="col-md-12 mb-3">
        <?php $field('customer_address', t('cust.address'), 'text', 'maxlength="255"'); ?>
        <!-- <div class="form-text"><?= t('cust.address_hint') ?></div> -->
</div>

<div class="row">
        <div class="col-md-6 mb-3">
          <?php $field('customer_phone', t('cust.phone'), 'tel', 'maxlength="255"'); ?>
        </div>
        <div class="col-md-6 mb-3">
          <?php $field('customer_email', t('cust.email'), 'email', 'maxlength="255"'); ?>
        <!-- <div class="form-text"><?= t('cust.email_hint') ?></div> -->
      </div>
      </div>

      <div class="col-md-12">
        <div class="form-floating">
          <textarea class="form-control" id="customer_info" name="customer_info"
                    placeholder=" " style="min-height:2.9rem;"><?= $val('customer_info') ?></textarea>
          <label for="customer_info"><?= t('cust.info') ?></label>
        </div>
        <!-- <div class="form-text"><?= t('cust.info_hint') ?></div> -->
      </div>


</div>
      
      
      
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="reset" class="btn btn-outline-secondary"><?= t('cust.reset') ?></button>
      <button type="submit" class="btn btn-primary" id="customerSubmitBtn"
              data-label-add="<?= e(t('cust.save')) ?>" data-label-update="<?= e(t('cust.update')) ?>">
        <i class="bi bi-plus-lg me-1"></i><span id="customerSubmitLabel"><?= $editing ? t('cust.update') : t('cust.save') ?></span>
      </button>
    </div>
  </form>
</details>

<!-- ---- List ---- -->
<details class="card ds-card" id="customer-list" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-people-fill text-primary"></i>
    <h2 class="h6 mb-0"><?= t('cust.list_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <?php if ($rows === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-people d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('cust.empty') ?>
    </div>
  <?php else: ?>
    <!-- The card holds the table, it is not the table: .ds-table is its own bordered
         panel inside the card body. ds-table.js builds the search box, page-size
         select, sort arrows and pager around it. -->
    <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('cust.name') ?></th>
            <th><?= t('cust.taxid') ?></th>
            <th><?= t('cust.contact') ?></th>
            <th><?= t('cust.phone') ?></th>
            <th><?= t('cust.email') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $c):
            $rowTaxid = ($c['customer_taxid'] === '0') ? null : $c['customer_taxid']; ?>
          <tr class="ds-row-editable" title="<?= t('cust.edit_hint') ?>"
              data-id="<?= (int) $c['id'] ?>"
              data-name="<?= e($c['customer_name']) ?>"
              data-taxid="<?= e((string) $rowTaxid) ?>"
              data-contact="<?= e((string) ($c['customer_contact'] ?? '')) ?>"
              data-phone="<?= e((string) ($c['customer_phone'] ?? '')) ?>"
              data-email="<?= e((string) ($c['customer_email'] ?? '')) ?>"
              data-address="<?= e((string) ($c['customer_address'] ?? '')) ?>"
              data-info="<?= e((string) ($c['customer_info'] ?? '')) ?>">
            <td>
              <div><?= e($c['customer_name']) ?></div>
              <?php if ($c['customer_address'] !== null): ?>
                <div class="text-secondary"><?= e($c['customer_address']) ?></div>
              <?php endif; ?>
            </td>
            <?php // No tax id — NULL on new rows, '0' in the imported data — means an
                  // individual rather than a company. Label it, don't print the placeholder.
                  // data-order is empty for those, so the badge text does not sort in
                  // among the numbers. ?>
            <td data-order="<?= e((string) $rowTaxid) ?>">
              <?php if ($rowTaxid !== null): ?>
                <code class="text-body"><?= e($rowTaxid) ?></code>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= t('cust.individual') ?></span>
              <?php endif; ?>
            </td>
            <td class="text-secondary" data-order="<?= e((string) ($c['customer_contact'] ?? '')) ?>">
              <?= e((string) ($c['customer_contact'] ?? '—')) ?>
            </td>
            <?php // data-order keeps the em dash out of sorting and searching. ?>
            <td data-order="<?= e((string) ($c['customer_phone'] ?? '')) ?>">
              <?php if ($c['customer_phone'] !== null): ?>
                <a href="tel:<?= e($c['customer_phone']) ?>" class="text-decoration-none"><?= e($c['customer_phone']) ?></a>
              <?php else: ?>
                <span class="text-secondary">—</span>
              <?php endif; ?>
            </td>
            <td data-order="<?= e((string) ($c['customer_email'] ?? '')) ?>">
              <?php if ($c['customer_email'] !== null): ?>
                <a href="mailto:<?= e($c['customer_email']) ?>" class="text-decoration-none"><?= e($c['customer_email']) ?></a>
              <?php else: ?>
                <span class="text-secondary">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div><!-- /.ds-table -->
    </div><!-- /.card-body -->
  <?php endif; ?>
</details>

<?php
// The tax id button searches the list below for what is typed — the "do we already
// have this customer?" check, which is the question you ask before adding one.
$scripts = ds_table_script() . <<<'HTML'

<script>
  document.getElementById('taxidLookup').addEventListener('click', () => {
    const search = document.querySelector('.ds-table-search input');
    if (!search) return;

    search.value = document.getElementById('customer_taxid').value.trim();
    search.dispatchEvent(new Event('input', { bubbles: true }));

    document.getElementById('customer-list').open = true;
    search.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  });

  // Row click = start of an edit. One delegated listener on the table survives
  // ds-table.js re-sorting and re-paging the rows (it moves the same <tr> nodes,
  // never clones them, so their data-* attributes and this listener stay intact).
  (() => {
    const table = document.querySelector('#customer-list table');
    const form  = document.querySelector('#customer-form form');
    const idInput   = document.getElementById('customer_id');
    const submitBtn = document.getElementById('customerSubmitBtn');
    const labelSpan = document.getElementById('customerSubmitLabel');
    if (!table || !form) return;

    const FIELDS = ['name', 'taxid', 'contact', 'phone', 'email', 'address', 'info'];

    table.addEventListener('click', (event) => {
      if (event.target.closest('a')) return; // tel:/mailto: links keep working
      const row = event.target.closest('tr[data-id]');
      if (!row) return;

      FIELDS.forEach((f) => {
        const input = document.getElementById('customer_' + f);
        if (!input) return;
        input.value = row.dataset[f] || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
      idInput.value = row.dataset.id;
      labelSpan.textContent = submitBtn.dataset.labelUpdate;

      // The form is open by default, so most clicks are a no-op here — only
      // scroll when it was actually closed. Unconditional scrollIntoView was
      // re-aligning the form's top edge to the viewport top on every click,
      // which reads as an unwanted jump when the form was already in view.
      const details = document.getElementById('customer-form');
      const wasClosed = !details.open;
      details.open = true;
      if (wasClosed) {
        form.scrollIntoView({ block: 'start', behavior: 'smooth' });
      }
    });

    // Reset clears the fields natively; it must also drop back to "add" mode.
    form.addEventListener('reset', () => {
      idInput.value = '';
      labelSpan.textContent = submitBtn.dataset.labelAdd;
    });
  })();
</script>
HTML;
?>
