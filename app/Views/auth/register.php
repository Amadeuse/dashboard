<?php
/**
 * @var array $errors  field => message from a failed submit
 * @var array $old     field => value, so a rejected form comes back filled
 */
$val = static fn(string $f): string => e((string) ($old[$f] ?? ''));
?>

<div class="card ds-card ds-auth-card">
  <div class="ds-auth-card-header">
    <a class="ds-auth-brand" href="/">
      <span class="ds-avatar ds-avatar-soft">N</span>
      <span><?= e(app_name()) ?></span>
    </a>
    <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; $flagIdSuffix = 'register-card'; ?>
    <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php require APP_PATH . '/Views/partials/lang-flag.php'; ?>
    </a>
  </div>

  <div>
    <h1 class="ds-auth-title"><?= t('auth.register.title') ?></h1>
    <p class="ds-auth-subtitle"><?= t('auth.register.subtitle') ?></p>
  </div>

  <a class="btn btn-outline-secondary ds-auth-google-btn" href="/auth/google">
    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
      <path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/>
      <path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/>
      <path fill="#FBBC05" d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z"/>
      <path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z"/>
    </svg>
    <span><?= t('auth.google.register') ?></span>
  </a>
  <div class="ds-auth-divider"><span><?= t('auth.or') ?></span></div>

  <form class="ds-auth-panel" method="post" action="/register" novalidate>
    <?= csrf_field() ?>

    <div class="form-floating">
      <input id="rg-name" name="name" type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= $val('name') ?>" placeholder=" " autocomplete="name" required>
      <label for="rg-name"><?= t('auth.fullName') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <?php if (isset($errors['name'])): ?><div class="invalid-feedback d-block"><?= e($errors['name']) ?></div><?php endif; ?>

    <div class="form-floating">
      <input id="rg-email" name="email" type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= $val('email') ?>" placeholder=" " autocomplete="email" required>
      <label for="rg-email"><?= t('auth.email') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>

    <div class="form-floating">
      <input id="rg-phone" name="phone" type="tel" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= $val('phone') ?>" placeholder=" " autocomplete="tel">
      <label for="rg-phone"><?= t('auth.phone') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <?php if (isset($errors['phone'])): ?>
      <div class="invalid-feedback d-block"><?= e($errors['phone']) ?></div>
    <?php else: ?>
      <div class="form-text mt-n2"><?= t('auth.phone.hint') ?></div>
    <?php endif; ?>

    <div class="form-floating form-floating--password">
      <input id="rg-password" name="password" type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
      <label for="rg-password"><?= t('auth.password') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
      <button type="button" class="btn-toggle-password" aria-label="<?= t('auth.show_password') ?>"
              data-show-label="<?= t('auth.show_password') ?>" data-hide-label="<?= t('auth.hide_password') ?>">
        <i class="bi bi-eye"></i>
      </button>
    </div>
    <?php if (isset($errors['password'])): ?>
      <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
    <?php else: ?>
      <div class="form-text mt-n2"><?= t('auth.password.hint') ?></div>
    <?php endif; ?>

    <div class="form-floating form-floating--password">
      <input id="rg-password2" name="password_confirm" type="password" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" placeholder=" " autocomplete="new-password" required>
      <label for="rg-password2"><?= t('auth.confirmPassword') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
      <button type="button" class="btn-toggle-password" aria-label="<?= t('auth.show_password') ?>"
              data-show-label="<?= t('auth.show_password') ?>" data-hide-label="<?= t('auth.hide_password') ?>">
        <i class="bi bi-eye"></i>
      </button>
    </div>
    <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback d-block"><?= e($errors['password_confirm']) ?></div><?php endif; ?>

    <div class="form-check d-flex align-items-start gap-2 mb-0">
      <input class="form-check-input mt-1" type="checkbox" id="rg-terms">
      <label class="form-check-label small" for="rg-terms"><?= t('auth.agreeTerms') ?></label>
    </div>

    <button class="btn btn-primary ds-auth-submit" type="submit"><?= t('auth.register.submit') ?></button>
  </form>

  <p class="ds-auth-footer"><?= t('auth.register.haveAccount') ?> <a class="ds-auth-link" href="/login"><?= t('auth.login') ?></a></p>
</div>
