<?php
/**
 * @var array  $errors  field => message from a failed submit
 * @var string $email   from the reset link's query string, re-validated server-side on POST too
 * @var string $token   from the reset link's query string
 */
?>

<div class="card ds-card ds-auth-card">
  <div class="ds-auth-card-header">
    <a class="ds-auth-brand" href="/">
      <span class="ds-avatar ds-avatar-soft">N</span>
      <span><?= e(app_name()) ?></span>
    </a>
    <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; $flagIdSuffix = 'reset-card'; ?>
    <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php require APP_PATH . '/Views/partials/lang-flag.php'; ?>
    </a>
  </div>

  <div>
    <h1 class="ds-auth-title"><?= t('auth.reset.title') ?></h1>
    <p class="ds-auth-subtitle"><?= t('auth.reset.subtitle') ?></p>
  </div>

  <form class="ds-auth-panel" method="post" action="/reset-password" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="form-floating">
      <input id="rp-password" name="password" type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
      <label for="rp-password"><?= t('auth.reset.newPassword') ?></label>
    </div>
    <?php if (isset($errors['password'])): ?>
      <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
    <?php else: ?>
      <div class="form-text mt-n2"><?= t('auth.password.hint') ?></div>
    <?php endif; ?>

    <div class="form-floating">
      <input id="rp-password2" name="password_confirm" type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
      <label for="rp-password2"><?= t('auth.reset.confirmPassword') ?></label>
    </div>
    <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback d-block"><?= e($errors['password_confirm']) ?></div><?php endif; ?>

    <button class="btn btn-primary ds-auth-submit" type="submit"><?= t('auth.reset.submit') ?></button>
  </form>

  <p class="ds-auth-footer"><a class="ds-auth-link" href="/login"><?= t('auth.backToLogin') ?></a></p>
</div>
