<?php
/**
 * @var array   $user     the logged-in user's row
 * @var array   $errors   field => message from a failed submit
 * @var array   $old      field => value, so a rejected form comes back filled
 * @var ?bool   $updated  true right after a successful save
 */
$val = static fn(string $f): string => e((string) ($old[$f] ?? $user[$f] ?? ''));
$bad = static fn(string $f): string => isset($errors[$f]) ? 'is-invalid' : '';
$avatarUrl  = '/assets/uploads/avatars/';
$hasAvatar  = (string) ($user['avatar'] ?? '') !== '';
$roleLabels = \App\Models\User::roles();
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('profile.title') ?></li>
      </ol>
    </nav>
  </div>
</div>

<?php if ($updated): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('profile.updated') ?>
  </div>
<?php endif; ?>

<div class="card ds-card">
  <div class="card-body">
    <form method="post" action="/profile" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>

      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="ds-product-thumb-wrap" style="width:88px;">
          <label for="pf-avatar" class="ds-product-thumb <?= $bad('avatar') ?>" style="width:88px;height:88px;border-radius:50%;" title="<?= t('profile.avatar') ?>">
            <img id="pf-avatar-preview" src="<?= $hasAvatar ? e($avatarUrl . $user['avatar']) : '' ?>" alt="" class="<?= $hasAvatar ? '' : 'd-none' ?>">
            <span id="pf-avatar-placeholder" class="ds-avatar ds-avatar-soft <?= $hasAvatar ? 'd-none' : '' ?>" style="width:100%;height:100%;font-size:1.4rem;">
              <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
            </span>
            <span class="ds-product-thumb-hover" style="border-radius:50%;">
              <i class="bi bi-upload"></i>
            </span>
          </label>
        </div>
        <input type="file" class="d-none" id="pf-avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp">

        <div>
          <div class="fw-semibold"><?= e($user['name']) ?></div>
          <div class="text-secondary small"><?= t('profile.memberSince', ds_date(substr($user['created_at'], 0, 10))) ?></div>
          <div class="d-flex gap-1 mt-1">
            <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill">
              <?= e($roleLabels[$user['role'] ?? 'admin'] ?? (string) ($user['role'] ?? 'admin')) ?>
            </span>
            <?php if ($user['google_id'] !== null): ?>
              <span class="badge bg-primary-subtle text-primary rounded-pill">
                <i class="bi bi-google me-1"></i><?= t('profile.googleLinked') ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if (isset($errors['avatar'])): ?><div class="invalid-feedback d-block mb-3"><?= e($errors['avatar']) ?></div><?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-floating">
            <input id="pf-name" name="name" type="text" class="form-control <?= $bad('name') ?>" value="<?= $val('name') ?>" placeholder=" " autocomplete="name" required>
            <label for="pf-name"><?= t('auth.fullName') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-6">
          <div class="form-floating">
            <input id="pf-email" name="email" type="email" class="form-control <?= $bad('email') ?>" value="<?= $val('email') ?>" placeholder=" " autocomplete="email" required>
            <label for="pf-email"><?= t('auth.email') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>
        </div>

        <div class="col-md-6">
          <div class="form-floating">
            <input id="pf-phone" name="phone" type="tel" class="form-control <?= $bad('phone') ?>" value="<?= $val('phone') ?>" placeholder=" " autocomplete="tel">
            <label for="pf-phone"><?= t('auth.phone') ?></label>
            <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
          </div>
          <?php if (isset($errors['phone'])): ?>
            <div class="invalid-feedback d-block"><?= e($errors['phone']) ?></div>
          <?php else: ?>
            <div class="form-text"><?= t('auth.phone.hint') ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="/profile/settings" class="btn btn-outline-secondary"><i class="bi bi-shield-lock me-1"></i><?= t('profile.settings.title') ?></a>
        <button type="submit" class="btn btn-primary"><?= t('profile.save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php $scripts = <<<'HTML'
<script>
  document.getElementById('pf-avatar').addEventListener('change', (event) => {
    const preview = document.getElementById('pf-avatar-preview');
    const placeholder = document.getElementById('pf-avatar-placeholder');
    const file = event.target.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.classList.remove('d-none');
    placeholder.classList.add('d-none');
  });
</script>
HTML;
?>
