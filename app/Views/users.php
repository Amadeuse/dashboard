<?php
/**
 * @var array   $rows     every user in the organization (admin included)
 * @var array   $roles    role code => translated label
 * @var int     $total    row count
 * @var array   $errors   field => message, from the failed POST
 * @var array   $old      field => value, so a rejected form comes back filled
 * @var ?string $created  name of the sub-user just added
 * @var ?string $updated  name of the sub-user just edited
 *
 * Same add-or-edit-in-one-form pattern as customers.php: clicking a row
 * copies its data into the form and sets #user_id, so submitting either
 * creates or updates depending on whether that id is set — see
 * UserController::store(). Password is required when adding, optional
 * (blank = unchanged) when editing an existing row.
 */
$editing   = ($old['user_id'] ?? '') !== '';
$avatarUrl = '/assets/uploads/avatars/';

$val = static fn(string $f): string => e((string) ($old[$f] ?? ''));
$bad = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active">
          <?= t('users.title') ?>
          <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
        </li>
      </ol>
    </nav>
  </div>
</div>

<?php if ($created !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('users.created', e($created)) ?>
  </div>
<?php endif; ?>
<?php if ($updated !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('users.updated', e($updated)) ?>
  </div>
<?php endif; ?>

<!-- ---- Add / edit ---- -->
<details class="card ds-card mb-3" id="user-form" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-person-plus-fill text-primary"></i>
    <h2 class="h6 mb-0"><?= t('users.new_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <form method="post" action="/settings/users" class="card-body pt-3" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" id="user_id" value="<?= e((string) ($old['user_id'] ?? '')) ?>">

    <div class="row g-3">
      <div class="col-md-2 d-flex flex-column align-items-center">
        <div class="ds-product-thumb-wrap" style="width:88px;height:88px;">
          <label for="user_avatar" class="ds-product-thumb" style="width:88px;height:88px;border-radius:50%;" title="<?= t('profile.avatar') ?>">
            <img id="userAvatarPreview" src="" alt="" class="d-none">
            <span id="userAvatarPlaceholder" class="ds-product-thumb-placeholder">
              <i class="bi bi-camera-fill"></i>
            </span>
            <span class="ds-product-thumb-hover" style="border-radius:50%;">
              <i class="bi bi-upload"></i>
            </span>
          </label>
        </div>
        <input type="file" class="d-none" id="user_avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp"
               data-max-bytes="2097152">
        <div class="form-text text-center small mt-1"><?= t('users.avatar_hint') ?></div>
      </div>

      <div class="col-md-10">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control <?= $bad('name') ?>" id="user_name" name="name" value="<?= $val('name') ?>" placeholder=" " maxlength="255" required>
              <label for="user_name"><?= t('auth.fullName') ?> *</label>
            </div>
            <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <select class="form-select <?= $bad('role') ?>" id="user_role" name="role" required
                      data-ds-select data-search-placeholder="<?= t('table.search') ?>"
                      data-no-results="<?= t('table.empty') ?>" data-clear-label="<?= t('cust.clear_field') ?>">
                <?php foreach ($roles as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= ($old['role'] ?? 'viewer') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="user_role"><?= t('users.role') ?> *</label>
            </div>
            <?php if (isset($errors['role'])): ?><div class="invalid-feedback d-block"><?= e($errors['role']) ?></div><?php endif; ?>
          </div>

          <div class="col-md-6">
            <div class="form-floating">
              <input type="email" class="form-control <?= $bad('email') ?>" id="user_email" name="email" value="<?= $val('email') ?>" placeholder=" " maxlength="255" required>
              <label for="user_email"><?= t('auth.email') ?> *</label>
            </div>
            <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="tel" class="form-control <?= $bad('phone') ?>" id="user_phone" name="phone" value="<?= $val('phone') ?>" placeholder=" " maxlength="32">
              <label for="user_phone"><?= t('auth.phone') ?></label>
            </div>
            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback d-block"><?= e($errors['phone']) ?></div><?php endif; ?>
          </div>

          <div class="col-md-6">
            <div class="form-floating">
              <input type="password" class="form-control <?= $bad('password') ?>" id="user_password" name="password" placeholder=" " autocomplete="new-password">
              <label for="user_password" id="user_password_label"><?= $editing ? t('users.password_keep') : t('auth.password') . ' *' ?></label>
            </div>
            <?php if (isset($errors['password'])): ?>
              <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
            <?php else: ?>
              <div class="form-text"><?= t('auth.password.hint') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <button type="reset" class="btn btn-outline-secondary"><?= t('users.reset') ?></button>
      <button type="submit" class="btn btn-primary" id="userSubmitBtn"
              data-label-add="<?= e(t('users.save')) ?>" data-label-update="<?= e(t('users.update')) ?>">
        <i class="bi bi-plus-lg me-1"></i><span id="userSubmitLabel"><?= $editing ? t('users.update') : t('users.save') ?></span>
      </button>
    </div>
  </form>
</details>

<!-- ---- List ---- -->
<details class="card ds-card" id="user-list" open>
  <summary class="card-header bg-transparent d-flex align-items-center gap-2 py-3">
    <i class="bi bi-people-fill text-primary"></i>
    <h2 class="h6 mb-0"><?= t('users.list_title') ?></h2>
    <i class="bi bi-chevron-down ms-auto small text-secondary ds-details-caret"></i>
  </summary>

  <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr class="text-secondary">
              <th></th>
              <th><?= t('auth.fullName') ?></th>
              <th><?= t('auth.email') ?></th>
              <th><?= t('auth.phone') ?></th>
              <th><?= t('users.role') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $u): ?>
              <tr class="ds-row-editable" title="<?= t('cust.edit_hint') ?>"
                  data-id="<?= (int) $u['id'] ?>"
                  data-name="<?= e($u['name']) ?>"
                  data-email="<?= e($u['email']) ?>"
                  data-phone="<?= e((string) ($u['phone'] ?? '')) ?>"
                  data-role="<?= e($u['role']) ?>"
                  data-avatar="<?= $u['avatar'] !== null ? e($avatarUrl . $u['avatar']) : '' ?>">
                <td style="width:48px;">
                  <?php if ($u['avatar'] !== null): ?>
                    <img src="<?= e($avatarUrl . $u['avatar']) ?>" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                  <?php else: ?>
                    <span class="ds-avatar ds-avatar-soft" style="width:32px;height:32px;font-size:.8rem;"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= e($u['name']) ?></td>
                <td data-order="<?= e($u['email']) ?>"><a href="mailto:<?= e($u['email']) ?>" class="text-decoration-none"><?= e($u['email']) ?></a></td>
                <td data-order="<?= e((string) ($u['phone'] ?? '')) ?>"><?= e((string) ($u['phone'] ?? '—')) ?></td>
                <td><span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</details>

<?php
$scripts = ds_table_script() . <<<'HTML'

<script>
  document.getElementById('user_avatar').addEventListener('change', (event) => {
    const input = event.target;
    const preview = document.getElementById('userAvatarPreview');
    const placeholder = document.getElementById('userAvatarPlaceholder');
    const file = input.files[0];
    if (!file) return;

    if (file.size > Number(input.dataset.maxBytes)) {
      window.dsNotifyCode?.(4418); // app/config/notifications.php: prod.err_image_size
      input.value = '';
      return;
    }
    if (!/\.(jpe?g|png|webp)$/i.test(file.name)) {
      window.dsNotifyCode?.(6817); // app/config/notifications.php: prod.err_image_type
      input.value = '';
      return;
    }

    preview.src = URL.createObjectURL(file);
    preview.classList.remove('d-none');
    placeholder.classList.add('d-none');
  });

  (() => {
    const table = document.querySelector('#user-list table');
    const form  = document.querySelector('#user-form form');
    const idInput      = document.getElementById('user_id');
    const submitBtn     = document.getElementById('userSubmitBtn');
    const labelSpan     = document.getElementById('userSubmitLabel');
    const passwordLabel = document.getElementById('user_password_label');
    const preview       = document.getElementById('userAvatarPreview');
    const placeholder    = document.getElementById('userAvatarPlaceholder');
    if (!table || !form) return;

    const FIELDS = ['name', 'email', 'phone', 'role'];

    table.addEventListener('click', (event) => {
      if (event.target.closest('a')) return; // mailto: links keep working
      const row = event.target.closest('tr[data-id]');
      if (!row) return;

      FIELDS.forEach((f) => {
        const input = document.getElementById('user_' + f);
        if (!input) return;
        input.value = row.dataset[f] || '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
      document.getElementById('user_role').dsSelect?.refresh();
      idInput.value = row.dataset.id;
      labelSpan.textContent = submitBtn.dataset.labelUpdate;
      passwordLabel.textContent = window.dsUsersLabels.passwordKeep;

      if (row.dataset.avatar) {
        preview.src = row.dataset.avatar;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
      } else {
        preview.src = '';
        preview.classList.add('d-none');
        placeholder.classList.remove('d-none');
      }

      const details = document.getElementById('user-form');
      const wasClosed = !details.open;
      details.open = true;
      if (wasClosed) {
        form.scrollIntoView({ block: 'start', behavior: 'smooth' });
      }
    });

    form.addEventListener('reset', () => {
      idInput.value = '';
      labelSpan.textContent = submitBtn.dataset.labelAdd;
      passwordLabel.textContent = window.dsUsersLabels.passwordNew;
      preview.src = '';
      preview.classList.add('d-none');
      placeholder.classList.remove('d-none');
      // Same one-tick-late refresh warehouse.php's own reset handler needs —
      // this engine's 'reset' event fires before <select>s actually revert.
      setTimeout(() => {
        document.getElementById('user_role').dsSelect?.refresh();
      }, 0);
    });
  })();
</script>
HTML;

$labels = json_encode([
    'passwordKeep' => t('users.password_keep'),
    'passwordNew'  => t('auth.password') . ' *',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$scripts = "<script>window.dsUsersLabels = $labels;</script>\n" . $scripts;
?>
