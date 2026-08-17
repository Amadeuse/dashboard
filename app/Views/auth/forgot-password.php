<?php
/**
 * @var array  $errors  field => message from a failed submit
 * @var array  $old     field => value, so a rejected form comes back filled
 * @var ?bool  $sent    true right after a submit, regardless of whether the email is registered
 */
$val = static fn(string $f): string => e((string) ($old[$f] ?? ''));
?>

<div class="card ds-card ds-auth-card">
  <div class="ds-auth-card-header">
    <a class="ds-auth-brand" href="/">
      <span class="ds-avatar ds-avatar-soft">N</span>
      <span><?= e(app_name()) ?></span>
    </a>
    <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; $flagIdSuffix = 'forgot-card'; ?>
    <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php require APP_PATH . '/Views/partials/lang-flag.php'; ?>
    </a>
  </div>

  <div>
    <h1 class="ds-auth-title"><?= t('auth.forgot.title') ?></h1>
    <p class="ds-auth-subtitle"><?= t('auth.forgot.subtitle') ?></p>
  </div>

  <?php if ($sent): ?>
    <div class="alert alert-success d-flex align-items-center gap-2 mb-0"><i class="bi bi-envelope-check-fill"></i> <?= t('auth.forgot.sent') ?></div>
  <?php endif; ?>

  <form class="ds-auth-panel" method="post" action="/forgot-password" novalidate>
    <?= csrf_field() ?>
    <div class="form-floating">
      <input id="fp-email" name="email" type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= $val('email') ?>" placeholder=" " autocomplete="email" required>
      <label for="fp-email"><?= t('auth.email') ?></label>
      <button type="button" class="btn-close btn-clear" aria-label="<?= t('cust.clear_field') ?>"></button>
    </div>
    <?php if (isset($errors['email'])): ?><div class="invalid-feedback d-block"><?= e($errors['email']) ?></div><?php endif; ?>
    <button class="btn btn-primary ds-auth-submit" type="submit"><?= t('auth.forgot.submit') ?></button>
  </form>

  <p class="ds-auth-footer"><a class="ds-auth-link" href="/login"><?= t('auth.backToLogin') ?></a></p>
</div>
