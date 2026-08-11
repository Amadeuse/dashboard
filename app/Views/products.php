<?php
/**
 * @var array   $rows    every product, newest first
 * @var array   $units   all units rows, for the select + its modal
 * @var int     $total   row count
 * @var array   $errors  field => message, from the failed POST
 * @var array   $old     field => value, so a rejected form comes back filled
 * @var ?string $created name of the product just added
 * @var ?string $updated name of the product just edited
 *
 * Core only: name, unit, price. Type/quantity/image live on their own page
 * now (the Warehouse module's /warehouse, see app/Modules/Warehouse/) —
 * this view never mentions it.
 */
$editing = ($old['product_id'] ?? '') !== '';

$val      = static fn(string $f): string => e((string) ($old[$f] ?? ''));
$bad      = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$selected = static fn(string $f, string $optionValue): string
    => ((string) ($old[$f] ?? '')) === $optionValue ? 'selected' : '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.products') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">
      <?= t('page.products') ?>
      <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
    </h1>
  </div>
</div>

<?php if ($created !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('prod.created', e($created)) ?>
  </div>
<?php endif; ?>
<?php if ($updated !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('prod.updated', e($updated)) ?>
  </div>
<?php endif; ?>

<!-- ---- New product ---- -->
<details class="card ds-card mb-3" id="product-form" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-box-seam text-primary"></i>
    <h2 class="h6 mb-0"><?= t('prod.new_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <form method="post" action="/products" class="card-body pt-3" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="product_id" id="product_id" value="<?= e((string) ($old['product_id'] ?? '')) ?>">

    <div class="row g-3">
      <div class="col-12">
        <div class="form-floating">
          <input type="text" class="form-control <?= $bad('name') ?>" id="name" name="name"
                 value="<?= $val('name') ?>" placeholder=" " maxlength="255" required>
          <label for="name"><?= t('prod.name') ?> *</label>
        </div>
        <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <div class="col-md-6">
        <div class="input-group">
          <select class="form-select <?= $bad('unit_id') ?>" id="unit_id" name="unit_id" required
                  data-ds-select data-search-placeholder="<?= t('table.search') ?>"
                  data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>">
            <option value=""></option>
            <?php foreach ($units as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= $selected('unit_id', (string) $u['id']) ?>><?= e($u['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label for="unit_id"><?= t('prod.unit') ?> *</label>
          <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal"
                  data-bs-target="#unitModal" title="<?= t('prod.manage') ?>"><i class="bi bi-gear"></i></button>
        </div>
        <?php if (isset($errors['unit_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['unit_id']) ?></div><?php endif; ?>
      </div>

      <div class="col-md-6">
        <div class="form-floating">
          <input type="number" step="0.01" min="0" class="form-control <?= $bad('unit_price') ?>" id="unit_price"
                 name="unit_price" value="<?= $val('unit_price') ?>" placeholder=" " required>
          <label for="unit_price"><?= t('prod.price') ?> *</label>
        </div>
        <?php if (isset($errors['unit_price'])): ?><div class="invalid-feedback d-block"><?= e($errors['unit_price']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="reset" class="btn btn-outline-secondary" id="productResetBtn"><?= t('prod.reset') ?></button>
      <button type="submit" class="btn btn-primary" id="productSubmitBtn"
              data-label-add="<?= e(t('prod.save')) ?>" data-label-update="<?= e(t('prod.update')) ?>">
        <i class="bi bi-plus-lg me-1"></i><span id="productSubmitLabel"><?= $editing ? t('prod.update') : t('prod.save') ?></span>
      </button>
    </div>
  </form>
</details>

<!-- ---- List ---- -->
<details class="card ds-card" id="product-list" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-box-seam text-primary"></i>
    <h2 class="h6 mb-0"><?= t('prod.list_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <?php if ($rows === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-box-seam d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('prod.empty') ?>
    </div>
  <?php else: ?>
    <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('prod.name') ?></th>
            <th><?= t('prod.unit') ?></th>
            <th><?= t('prod.price') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p): ?>
          <tr class="ds-row-editable" title="<?= t('prod.edit_hint') ?>"
              data-id="<?= (int) $p['id'] ?>"
              data-name="<?= e($p['name']) ?>"
              data-unit="<?= (int) $p['unit_id'] ?>"
              data-price="<?= e((string) $p['unit_price']) ?>">
            <td><?= e($p['name']) ?></td>
            <td class="text-secondary"><?= e($p['unit_name']) ?></td>
            <td data-order="<?= (float) $p['unit_price'] ?>"><?= number_format((float) $p['unit_price'], 2) ?></td>
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
/** unit lookup modal — add/rename over AJAX, same shape as products.php always had. */
$lookupModal = static function (
    string $modalId, string $endpoint, string $title, string $placeholder,
    string $addLabel, string $updateLabel, string $emptyText, array $rows
): void { ?>
  <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= $title ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none py-2" data-lookup-error></div>
          <form data-lookup-form data-endpoint="<?= $endpoint ?>" class="d-flex gap-2 mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="id" data-lookup-id>
            <input type="text" class="form-control" name="name" placeholder="<?= $placeholder ?>"
                   data-lookup-name maxlength="255" required>
            <button type="submit" class="btn btn-primary text-nowrap" data-lookup-submit
                    data-label-add="<?= e($addLabel) ?>" data-label-update="<?= e($updateLabel) ?>"><?= $addLabel ?></button>
          </form>
          <div class="list-group" data-lookup-list style="max-height:220px;overflow-y:auto;">
            <?php foreach ($rows as $row): ?>
              <button type="button" class="list-group-item list-group-item-action"
                      data-id="<?= (int) $row['id'] ?>" data-name="<?= e($row['name']) ?>"><?= e($row['name']) ?></button>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><p class="text-secondary small mb-0 p-2" data-lookup-empty><?= $emptyText ?></p><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php };

$lookupModal('unitModal', '/units', t('unit.modal_title'), t('unit.name'),
    t('unit.add'), t('unit.update'), t('unit.empty'), $units);
?>

<?php
$scripts = ds_table_script() . <<<'HTML'

<script>
  // Unit modal: add or rename over AJAX, then reflect the result into the
  // product form's <select> — never a page reload, so whatever is already
  // typed into the product form survives.
  function wireLookupModal(modalId, selectId) {
    const modal   = document.getElementById(modalId);
    const select  = document.getElementById(selectId);
    const form    = modal.querySelector('[data-lookup-form]');
    const errorEl = modal.querySelector('[data-lookup-error]');
    const idInput = modal.querySelector('[data-lookup-id]');
    const nameInput = modal.querySelector('[data-lookup-name]');
    const submitBtn = modal.querySelector('[data-lookup-submit]');
    const list    = modal.querySelector('[data-lookup-list]');

    const resetForm = () => {
      form.reset();
      idInput.value = '';
      submitBtn.textContent = submitBtn.dataset.labelAdd;
      errorEl.classList.add('d-none');
    };

    list.addEventListener('click', (event) => {
      const item = event.target.closest('[data-id]');
      if (!item) return;
      idInput.value = item.dataset.id;
      nameInput.value = item.dataset.name;
      submitBtn.textContent = submitBtn.dataset.labelUpdate;
      nameInput.focus();
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      errorEl.classList.add('d-none');

      const res  = await fetch(form.dataset.endpoint, { method: 'POST', body: new FormData(form) });
      const data = await res.json().catch(() => null);

      if (!res.ok || !data || data.error) {
        errorEl.textContent = (data && data.error) || form.dataset.endpoint;
        errorEl.classList.remove('d-none');
        return;
      }

      let option = select.querySelector('option[value="' + data.id + '"]');
      if (!option) {
        option = document.createElement('option');
        select.appendChild(option);
      }
      option.value = data.id;
      option.textContent = data.name;
      select.value = data.id;
      select.dsSelect?.refresh(); // new/renamed <option> + the fresh value, into the trigger

      let item = list.querySelector('[data-id="' + data.id + '"]');
      if (!item) {
        item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action';
        list.querySelector('[data-lookup-empty]')?.remove();
        list.appendChild(item);
      }
      item.dataset.id = data.id;
      item.dataset.name = data.name;
      item.textContent = data.name;

      resetForm();
    });

    modal.addEventListener('hidden.bs.modal', resetForm);
  }

  wireLookupModal('unitModal', 'unit_id');

  // Row click = start of an edit. One delegated listener on the table survives
  // ds-table.js re-sorting and re-paging the rows (see customers.php for the
  // same pattern and why).
  (() => {
    const table = document.querySelector('#product-list table');
    const form  = document.querySelector('#product-form form');
    const idInput   = document.getElementById('product_id');
    const submitBtn = document.getElementById('productSubmitBtn');
    const labelSpan = document.getElementById('productSubmitLabel');
    if (!table || !form) return;

    table.addEventListener('click', (event) => {
      const row = event.target.closest('tr[data-id]');
      if (!row) return;

      document.getElementById('name').value = row.dataset.name || '';
      document.getElementById('unit_id').value = row.dataset.unit || '';
      document.getElementById('unit_id').dsSelect?.refresh();
      document.getElementById('unit_price').value = row.dataset.price || '';

      idInput.value = row.dataset.id;
      labelSpan.textContent = submitBtn.dataset.labelUpdate;

      const details = document.getElementById('product-form');
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
      // In this engine, a form's 'reset' event fires *before* the browser has
      // actually put the native <select>s back to their default value — a
      // refresh() called synchronously here still reads the pre-reset value.
      // One tick later, the reset has landed.
      setTimeout(() => document.getElementById('unit_id').dsSelect?.refresh(), 0);
    });
  })();
</script>
HTML;
?>
