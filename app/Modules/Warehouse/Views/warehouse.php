<?php
/**
 * @var array   $rows         product_warehouse rows joined with product name/price + type name
 * @var array   $products     every core product, for the picker (see Product::all())
 * @var array   $productTypes all product_types rows, for the select + its modal
 * @var int     $total        row count
 * @var array   $errors       field => message, from the failed POST
 * @var array   $old          field => value, so a rejected form comes back filled
 * @var ?string $saved        name of the product just saved into the warehouse
 *
 * Its own page: pick an existing product (Products already owns creating
 * one), then set type/quantity/image for it. product_id is the natural key —
 * upsert() makes "add" and "edit" the same action, so there is no separate
 * add/update button label to track here.
 */
$val      = static fn(string $f): string => e((string) ($old[$f] ?? ''));
$bad      = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$selected = static fn(string $f, string $optionValue): string
    => ((string) ($old[$f] ?? '')) === $optionValue ? 'selected' : '';

$uploadUrl     = '/assets/uploads/products/';
$existingImage = (string) ($old['existing_image'] ?? '');
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.warehouse') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">
      <?= t('page.warehouse') ?>
      <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
    </h1>
  </div>
</div>

<?php if ($saved !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('warehouse.saved', e($saved)) ?>
  </div>
<?php endif; ?>

<!-- ---- Add / edit ---- -->
<details class="card ds-card mb-3" id="warehouse-form" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-boxes text-primary"></i>
    <h2 class="h6 mb-0"><?= t('warehouse.new_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <form method="post" action="/warehouse" enctype="multipart/form-data" class="card-body pt-3" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="existing_image" id="existing_image" value="<?= e($existingImage) ?>">

    <div class="row g-3">
      <div class="col-auto">
        <div class="ds-product-thumb-wrap">
          <label for="image" class="ds-product-thumb <?= $bad('image') ?>" title="<?= t('prod.image') ?>">
            <img id="imagePreview" src="<?= $existingImage !== '' ? e($uploadUrl . $existingImage) : '' ?>"
                 alt="" class="<?= $existingImage === '' ? 'd-none' : '' ?>">
            <span id="imagePlaceholder" class="ds-product-thumb-placeholder <?= $existingImage !== '' ? 'd-none' : '' ?>">
              <i class="bi bi-camera-fill"></i>
              <span><?= t('prod.image') ?></span>
            </span>
            <span class="ds-product-thumb-hover">
              <i class="bi bi-upload"></i>
              <span><?= t('prod.change_image') ?></span>
            </span>
          </label>
        </div>
        <input type="file" class="d-none" id="image" name="image" accept=".jpg,.jpeg,.png,.webp">
        <div class="form-text text-center" style="max-width:200px;"><?= t('prod.image_hint') ?></div>
        <?php if (isset($errors['image'])): ?>
          <div class="invalid-feedback d-block text-center" style="max-width:200px;"><?= e($errors['image']) ?></div>
        <?php endif; ?>
      </div>

      <div class="col">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-floating">
              <select class="form-select <?= $bad('product_id') ?>" id="product_id" name="product_id" required
                      data-ds-select data-search-placeholder="<?= t('table.search') ?>"
                      data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>">
                <option value=""></option>
                <?php foreach ($products as $p): ?>
                  <option value="<?= (int) $p['id'] ?>" <?= $selected('product_id', (string) $p['id']) ?>><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="product_id"><?= t('warehouse.product') ?> *</label>
            </div>
            <?php if (isset($errors['product_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['product_id']) ?></div><?php endif; ?>
          </div>

          <div class="col-md-6">
            <div class="input-group">
              <select class="form-select <?= $bad('product_type_id') ?>" id="product_type_id" name="product_type_id" required
                      data-ds-select data-search-placeholder="<?= t('table.search') ?>"
                      data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>">
                <option value=""></option>
                <?php foreach ($productTypes as $pt): ?>
                  <option value="<?= (int) $pt['id'] ?>" <?= $selected('product_type_id', (string) $pt['id']) ?>><?= e($pt['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="product_type_id"><?= t('prod.type') ?> *</label>
              <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal"
                      data-bs-target="#productTypeModal" title="<?= t('prod.manage') ?>"><i class="bi bi-gear"></i></button>
            </div>
            <?php if (isset($errors['product_type_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['product_type_id']) ?></div><?php endif; ?>
          </div>

          <div class="col-md-6">
            <div class="form-floating">
              <input type="number" step="0.001" min="0" class="form-control <?= $bad('remaining_qty') ?>" id="remaining_qty"
                     name="remaining_qty" value="<?= $val('remaining_qty') ?>" placeholder=" " required>
              <label for="remaining_qty"><?= t('prod.qty') ?> *</label>
            </div>
            <?php if (isset($errors['remaining_qty'])): ?><div class="invalid-feedback d-block"><?= e($errors['remaining_qty']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="reset" class="btn btn-outline-secondary" id="warehouseResetBtn"><?= t('warehouse.reset') ?></button>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= t('warehouse.save') ?></button>
    </div>
  </form>
</details>

<!-- ---- List ---- -->
<details class="card ds-card" id="warehouse-list" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-boxes text-primary"></i>
    <h2 class="h6 mb-0"><?= t('warehouse.list_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <?php if ($rows === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-boxes d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('warehouse.empty') ?>
    </div>
  <?php else: ?>
    <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('prod.image') ?></th>
            <th><?= t('warehouse.product') ?></th>
            <th><?= t('prod.type') ?></th>
            <th><?= t('prod.qty') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr class="ds-row-editable" title="<?= t('warehouse.edit_hint') ?>"
              data-product="<?= (int) $r['product_id'] ?>"
              data-type="<?= (int) $r['product_type_id'] ?>"
              data-qty="<?= e((string) $r['remaining_qty']) ?>"
              data-image="<?= e((string) ($r['image'] ?? '')) ?>">
            <td>
              <?php if (!empty($r['image'])): ?>
                <img src="<?= e($uploadUrl . $r['image']) ?>" alt="" class="rounded border"
                     style="width:36px;height:36px;object-fit:cover;">
              <?php else: ?>
                <span class="text-secondary small"><?= t('prod.no_image') ?></span>
              <?php endif; ?>
            </td>
            <td><?= e($r['product_name']) ?></td>
            <td class="text-secondary"><?= e($r['product_type_name']) ?></td>
            <td data-order="<?= (float) $r['remaining_qty'] ?>"><?= (string) (float) $r['remaining_qty'] ?></td>
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
/** product_type lookup modal — add/rename over AJAX, same shape as products.php's unitModal. */
?>
<div class="modal fade" id="productTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= t('ptype.modal_title') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none py-2" data-lookup-error></div>
        <form data-lookup-form data-endpoint="/product-types" class="d-flex gap-2 mb-3">
          <?= csrf_field() ?>
          <input type="hidden" name="id" data-lookup-id>
          <input type="text" class="form-control" name="name" placeholder="<?= t('ptype.name') ?>"
                 data-lookup-name maxlength="255" required>
          <button type="submit" class="btn btn-primary text-nowrap" data-lookup-submit
                  data-label-add="<?= e(t('ptype.add')) ?>" data-label-update="<?= e(t('ptype.update')) ?>"><?= t('ptype.add') ?></button>
        </form>
        <div class="list-group" data-lookup-list style="max-height:220px;overflow-y:auto;">
          <?php foreach ($productTypes as $row): ?>
            <button type="button" class="list-group-item list-group-item-action"
                    data-id="<?= (int) $row['id'] ?>" data-name="<?= e($row['name']) ?>"><?= e($row['name']) ?></button>
          <?php endforeach; ?>
          <?php if ($productTypes === []): ?><p class="text-secondary small mb-0 p-2" data-lookup-empty><?= t('ptype.empty') ?></p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$scripts = ds_table_script() . <<<'HTML'

<script>
  // Same add/rename-over-AJAX pattern products.php uses for its own unit
  // modal — duplicated rather than shared across the core/module boundary.
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
      select.dsSelect?.refresh();

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

  wireLookupModal('productTypeModal', 'product_type_id');

  // Live preview for a newly chosen file, before it is ever uploaded. The
  // thumbnail itself is a <label for="image">, so clicking it already opens
  // the file picker natively — no JS needed for that part.
  document.getElementById('image').addEventListener('change', (event) => {
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('imagePlaceholder');
    const file = event.target.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.classList.remove('d-none');
    placeholder.classList.add('d-none');
  });

  // Row click = start of an edit: picks the same product in the dropdown and
  // fills its stored type/quantity/image. Submitting again just upserts —
  // no separate "editing" mode to track.
  (() => {
    const table = document.querySelector('#warehouse-list table');
    const form  = document.querySelector('#warehouse-form form');
    const existingImageInput = document.getElementById('existing_image');
    const preview     = document.getElementById('imagePreview');
    const placeholder = document.getElementById('imagePlaceholder');
    const uploadUrl   = '/assets/uploads/products/';
    if (!table || !form) return;

    table.addEventListener('click', (event) => {
      const row = event.target.closest('tr[data-product]');
      if (!row) return;

      document.getElementById('product_id').value = row.dataset.product || '';
      document.getElementById('product_id').dsSelect?.refresh();
      document.getElementById('product_type_id').value = row.dataset.type || '';
      document.getElementById('product_type_id').dsSelect?.refresh();
      document.getElementById('remaining_qty').value = row.dataset.qty || '';
      document.getElementById('image').value = '';

      existingImageInput.value = row.dataset.image || '';
      if (row.dataset.image) {
        preview.src = uploadUrl + row.dataset.image;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
      } else {
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
      }

      const details = document.getElementById('warehouse-form');
      const wasClosed = !details.open;
      details.open = true;
      if (wasClosed) {
        form.scrollIntoView({ block: 'start', behavior: 'smooth' });
      }
    });

    form.addEventListener('reset', () => {
      existingImageInput.value = '';
      preview.classList.add('d-none');
      placeholder.classList.remove('d-none');
      // Same one-tick-late refresh products.php's own reset handler needs —
      // this engine's 'reset' event fires before <select>s actually revert.
      setTimeout(() => {
        document.getElementById('product_id').dsSelect?.refresh();
        document.getElementById('product_type_id').dsSelect?.refresh();
      }, 0);
    });
  })();
</script>
HTML;
?>
