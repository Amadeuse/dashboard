<?php
/**
 * @var array  $org      the organization row (id=1, always exists)
 * @var array  $errors   field => message from a failed submit
 * @var array  $old      field => value, so a rejected form comes back filled
 * @var ?bool  $updated  true right after a successful save
 */
$val      = static fn(string $f): string => e((string) ($old[$f] ?? $org[$f] ?? ''));
$bad      = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$selected = static fn(string $f, string $optionValue): string
    => ((string) ($old[$f] ?? $org[$f] ?? 'GEL')) === $optionValue ? 'selected' : '';
$uploadUrl = '/assets/uploads/organization/';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('org.title') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= t('org.title') ?></h1>
  </div>
</div>

<?php if ($updated): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('org.updated') ?>
  </div>
<?php endif; ?>

<div class="card ds-card">
  <div class="card-body">
    <form method="post" action="/settings/organization" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <div class="row g-4 mb-4">
        <div class="col-auto">
          <div class="ds-product-thumb-wrap" style="width:140px;">
          <label for="org_logo" class="ds-product-thumb <?= $bad('logo') ?>" style="width:140px;height:140px;" title="<?= t('org.logo') ?>">
            <img src="<?= $org['logo'] !== null ? e($uploadUrl . $org['logo']) : '' ?>" alt="" class="<?= $org['logo'] === null ? 'd-none' : '' ?>">
            <span class="ds-product-thumb-placeholder <?= $org['logo'] !== null ? 'd-none' : '' ?>">
              <i class="bi bi-image"></i>
              <span><?= t('org.logo') ?></span>
            </span>
            <span class="ds-product-thumb-hover">
              <i class="bi bi-upload"></i>
              <span><?= t('prod.change_image') ?></span>
            </span>
          </label>
          </div>
          <input type="file" class="d-none" id="org_logo" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg">
          <?php if (isset($errors['logo'])): ?><div class="invalid-feedback d-block text-center" style="max-width:140px;"><?= e($errors['logo']) ?></div><?php endif; ?>
        </div>

        <div class="col-auto">
          <div class="ds-product-thumb-wrap" style="width:140px;">
          <label for="org_signature" class="ds-product-thumb <?= $bad('signature') ?>" style="width:140px;height:140px;" title="<?= t('org.signature') ?>">
            <img src="<?= $org['signature'] !== null ? e($uploadUrl . $org['signature']) : '' ?>" alt="" class="<?= $org['signature'] === null ? 'd-none' : '' ?>">
            <span class="ds-product-thumb-placeholder <?= $org['signature'] !== null ? 'd-none' : '' ?>">
              <i class="bi bi-pen"></i>
              <span><?= t('org.signature') ?></span>
            </span>
            <span class="ds-product-thumb-hover">
              <i class="bi bi-upload"></i>
              <span><?= t('prod.change_image') ?></span>
            </span>
          </label>
          </div>
          <input type="file" class="d-none" id="org_signature" name="signature" accept=".jpg,.jpeg,.png,.webp,.svg">
          <?php if (isset($errors['signature'])): ?><div class="invalid-feedback d-block text-center" style="max-width:140px;"><?= e($errors['signature']) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-6">
          <div class="form-floating">
            <input type="text" class="form-control <?= $bad('name') ?>" id="org_name" name="name" value="<?= $val('name') ?>" placeholder=" " maxlength="255" required>
            <label for="org_name"><?= t('org.name') ?> *</label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="form-floating">
            <input type="text" class="form-control <?= $bad('tax_id') ?>" id="org_tax_id" name="tax_id" value="<?= $val('tax_id') ?>" placeholder=" " maxlength="64">
            <label for="org_tax_id"><?= t('org.tax_id') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['tax_id'])): ?><div class="invalid-feedback d-block"><?= e($errors['tax_id']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-6">
          <div class="form-floating">
            <input type="email" class="form-control <?= $bad('email') ?>" id="org_email" name="email" value="<?= $val('email') ?>" placeholder=" " maxlength="255">
            <label for="org_email"><?= t('auth.email') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="form-floating">
            <input type="url" class="form-control <?= $bad('website') ?>" id="org_website" name="website" value="<?= $val('website') ?>" placeholder=" " maxlength="255">
            <label for="org_website"><?= t('org.website') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['website'])): ?><div class="invalid-feedback d-block"><?= e($errors['website']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-6">
          <div class="form-floating">
            <input type="tel" class="form-control <?= $bad('phone') ?>" id="org_phone" name="phone" value="<?= $val('phone') ?>" placeholder=" " maxlength="32">
            <label for="org_phone"><?= t('auth.phone') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['phone'])): ?><div class="invalid-feedback d-block"><?= e($errors['phone']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="form-floating">
            <input type="text" class="form-control <?= $bad('invoice_prefix') ?>" id="org_invoice_prefix" name="invoice_prefix" value="<?= $val('invoice_prefix') ?>" placeholder=" " maxlength="16">
            <label for="org_invoice_prefix"><?= t('org.invoice_prefix') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['invoice_prefix'])): ?>
            <div class="invalid-feedback d-block"><?= e($errors['invoice_prefix']) ?></div>
          <?php else: ?>
            <div class="form-text"><?= t('org.invoice_prefix_hint') ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-6">
          <div class="form-floating">
            <input type="number" step="0.01" min="0" max="100" class="form-control <?= $bad('vat_rate') ?>" id="org_vat_rate" name="vat_rate" value="<?= $val('vat_rate') ?>" placeholder=" ">
            <label for="org_vat_rate"><?= t('org.vat_rate') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['vat_rate'])): ?><div class="invalid-feedback d-block"><?= e($errors['vat_rate']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
          <div class="form-floating">
            <select class="form-select <?= $bad('currency') ?>" id="org_currency" name="currency">
              <option value="GEL" <?= $selected('currency', 'GEL') ?>><?= t('org.currency_gel') ?></option>
              <option value="USD" <?= $selected('currency', 'USD') ?>><?= t('org.currency_usd') ?></option>
            </select>
            <label for="org_currency"><?= t('org.currency') ?></label>
          </div>
          <?php if (isset($errors['currency'])): ?><div class="invalid-feedback d-block"><?= e($errors['currency']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-12">
          <div class="form-floating">
            <input type="text" class="form-control <?= $bad('address') ?>" id="org_address" name="address" value="<?= $val('address') ?>" placeholder=" " maxlength="255">
            <label for="org_address"><?= t('org.address') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['address'])): ?><div class="invalid-feedback d-block"><?= e($errors['address']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-12">
          <label class="form-label"><?= t('org.bank_details') ?></label>
          <div id="bankAccounts"
               data-banks="<?= e(json_encode($banks, JSON_UNESCAPED_UNICODE)) ?>"
               data-iban-label="<?= e(t('org.bank_iban_label')) ?>"
               data-remove-label="<?= e(t('org.bank_remove')) ?>"
               data-clear-label="<?= e(t('cust.clear_field')) ?>"
               data-next-index="<?= count($accounts) ?>">
            <?php foreach ($accounts as $i => $acc): $rowErr = $errors['bank_ibans_' . $i] ?? null; ?>
            <div class="input-group mb-3 bank-account-row">
              <span class="input-group-text ds-bank-addon">
                <?php if ($acc['logo'] !== null): ?>
                  <img src="/assets/images/banks/<?= e($acc['logo']) ?>" alt="<?= e($acc['name']) ?>" title="<?= e($acc['name']) ?>" width="20" height="20" style="object-fit:contain;">
                <?php elseif ($acc['code'] !== null): ?>
                  <span class="ds-avatar" style="width:26px;height:26px;font-size:.7rem;background:var(--bs-secondary)"><?= e($acc['code']) ?></span>
                <?php else: ?>
                  <span class="ds-avatar" style="width:26px;height:26px;background:var(--bs-secondary)"><i class="bi bi-bank2"></i></span>
                <?php endif; ?>
              </span>
              <div class="form-floating">
                <input type="text" class="form-control <?= $rowErr ? 'is-invalid' : '' ?>" name="bank_ibans[]"
                       id="bank_iban_<?= $i ?>" value="<?= e($acc['iban']) ?>" placeholder=" "
                       autocomplete="off" spellcheck="false" data-iban-input>
                <label for="bank_iban_<?= $i ?>"><?= t('org.bank_iban_label') ?> <?= $i + 1 ?></label>
                <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
              </div>
              <button type="button" class="btn btn-outline-secondary bank-account-remove" tabindex="-1" aria-label="<?= t('org.bank_remove') ?>"><i class="bi bi-x-lg"></i></button>
              <?php if ($rowErr): ?><div class="invalid-feedback d-block w-100"><?= e($rowErr) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text"><?= t('org.bank_details_hint') ?></div>
        </div>
      </div>

      <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary"><?= t('org.save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php $scripts = <<<'HTML'
<script>
  ['org_logo', 'org_signature'].forEach((id) => {
    document.getElementById(id).addEventListener('change', (event) => {
      const wrap = document.querySelector(`label[for="${id}"]`);
      const img = wrap.querySelector('img');
      const placeholder = wrap.querySelector('.ds-product-thumb-placeholder');
      const file = event.target.files[0];
      if (!file) return;
      img.src = URL.createObjectURL(file);
      img.classList.remove('d-none');
      placeholder.classList.add('d-none');
    });
  });

  (() => {
    const wrap = document.getElementById('bankAccounts');
    if (!wrap) return;

    const banks = JSON.parse(wrap.dataset.banks);
    const ibanLabel = wrap.dataset.ibanLabel;
    const removeLabel = wrap.dataset.removeLabel;
    const clearLabel = wrap.dataset.clearLabel;
    let nextIndex = Number(wrap.dataset.nextIndex);

    /** ISO 7064 mod-97-10 — mirrors App\Core\Iban::checksumValid() in PHP. */
    function ibanValid(iban) {
      if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(iban)) return false;
      if (iban.startsWith('GE') && iban.length !== 22) return false;
      if (iban.length < 15 || iban.length > 34) return false;
      const rearranged = iban.slice(4) + iban.slice(0, 4);
      let remainder = 0;
      for (const ch of rearranged) {
        const value = /[A-Z]/.test(ch) ? String(ch.charCodeAt(0) - 55) : ch;
        for (const digit of value) remainder = (remainder * 10 + Number(digit)) % 97;
      }
      return remainder === 1;
    }

    function badgeFor(raw) {
      const iban = raw.toUpperCase().replace(/\s+/g, '');
      const code = iban.startsWith('GE') && iban.length >= 6 ? iban.slice(4, 6) : null;
      const bank = code ? banks[code] : null;
      if (bank) {
        return `<img src="/assets/images/banks/${bank.logo}" alt="${bank.name}" title="${bank.name}" width="20" height="20" style="object-fit:contain;">`;
      }
      if (code) {
        return `<span class="ds-avatar" style="width:26px;height:26px;font-size:.7rem;background:var(--bs-secondary)">${code}</span>`;
      }
      return '<span class="ds-avatar" style="width:26px;height:26px;background:var(--bs-secondary)"><i class="bi bi-bank2"></i></span>';
    }

    function rowHtml(index) {
      return `
        <div class="input-group mb-3 bank-account-row">
          <span class="input-group-text ds-bank-addon">${badgeFor('')}</span>
          <div class="form-floating">
            <input type="text" class="form-control" name="bank_ibans[]" id="bank_iban_${index}"
                   placeholder=" " autocomplete="off" spellcheck="false" data-iban-input>
            <label for="bank_iban_${index}">${ibanLabel} ${index + 1}</label>
            <button type="button" class="btn-close btn-clear" aria-label="${clearLabel}"></button>
          </div>
          <button type="button" class="btn btn-outline-secondary bank-account-remove" tabindex="-1" aria-label="${removeLabel}"><i class="bi bi-x-lg"></i></button>
        </div>`;
    }

    function addRow() {
      wrap.insertAdjacentHTML('beforeend', rowHtml(nextIndex));
      nextIndex += 1;
    }

    wrap.addEventListener('input', (event) => {
      const input = event.target;
      if (!input.matches('[data-iban-input]')) return;

      const row = input.closest('.bank-account-row');
      row.querySelector('.ds-bank-addon').innerHTML = badgeFor(input.value);

      const valid = input.value.trim() === '' || ibanValid(input.value.toUpperCase().replace(/\s+/g, ''));
      input.classList.toggle('is-invalid', !valid);

      const isLastRow = row === wrap.lastElementChild;
      if (isLastRow && input.value.trim() !== '') addRow();
    });

    wrap.addEventListener('click', (event) => {
      const button = event.target.closest('.bank-account-remove');
      if (!button) return;

      const row = button.closest('.bank-account-row');
      if (wrap.children.length > 1) {
        row.remove();
      } else {
        row.querySelector('[data-iban-input]').value = '';
        row.querySelector('.ds-bank-addon').innerHTML = badgeFor('');
      }
    });
  })();
</script>
HTML;
?>
