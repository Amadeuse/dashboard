<?php
/**
 * @var array  $user     the logged-in user's row
 * @var array  $errors   field => message from a failed submit
 * @var ?bool  $updated  true right after a successful password change
 */
$hasPassword = $user['password_hash'] !== null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item"><a href="/profile" class="text-decoration-none"><?= t('profile.title') ?></a></li>
        <li class="breadcrumb-item active"><?= t('profile.settings.title') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0"><?= t('profile.settings.title') ?></h1>
  </div>
</div>

<?php if ($updated): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= t('profile.settings.updated') ?>
  </div>
<?php endif; ?>

<div class="card ds-card mb-3">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3"><i class="bi bi-key text-primary me-2"></i><?= t('profile.settings.password_title') ?></h2>

    <?php if (!$hasPassword): ?>
      <div class="alert alert-info small"><?= t('profile.settings.no_password_yet') ?></div>
    <?php endif; ?>

    <form method="post" action="/profile/settings" novalidate style="max-width:420px;">
      <?= csrf_field() ?>

      <?php if ($hasPassword): ?>
        <div class="form-floating mb-3">
          <input id="ps-current" name="current_password" type="password" class="form-control <?= isset($errors['current_password']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="current-password" required>
          <label for="ps-current"><?= t('profile.settings.current_password') ?></label>
        </div>
        <?php if (isset($errors['current_password'])): ?><div class="invalid-feedback d-block mb-3"><?= e($errors['current_password']) ?></div><?php endif; ?>
      <?php endif; ?>

      <div class="form-floating mb-3">
        <input id="ps-password" name="password" type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
        <label for="ps-password"><?= t('profile.settings.new_password') ?></label>
      </div>
      <?php if (isset($errors['password'])): ?>
        <div class="invalid-feedback d-block mb-3"><?= e($errors['password']) ?></div>
      <?php else: ?>
        <div class="form-text mb-3 mt-n2"><?= t('auth.password.hint') ?></div>
      <?php endif; ?>

      <div class="form-floating mb-3">
        <input id="ps-password2" name="password_confirm" type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
        <label for="ps-password2"><?= t('profile.settings.confirm_password') ?></label>
      </div>
      <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback d-block mb-3"><?= e($errors['password_confirm']) ?></div><?php endif; ?>

      <button type="submit" class="btn btn-primary"><?= t('profile.settings.save') ?></button>
    </form>
  </div>
</div>

<div class="card ds-card">
  <div class="card-body d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-google text-primary fs-5"></i>
      <div>
        <div class="fw-semibold"><?= t('profile.settings.google_title') ?></div>
        <div class="text-secondary small"><?= $user['google_id'] !== null ? t('profile.googleLinked') : t('profile.settings.google_not_linked') ?></div>
      </div>
    </div>
    <?php if ($user['google_id'] === null): ?>
      <a href="/auth/google" class="btn btn-outline-secondary btn-sm"><?= t('profile.settings.google_connect') ?></a>
    <?php endif; ?>
  </div>
</div>
