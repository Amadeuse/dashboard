<!doctype html>
<html lang="<?= ds_lang() ?>" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Nova Dashboard') ?></title>

  <!-- Inter has no Georgian glyphs (latin/greek/cyrillic/vietnamese only), so Georgian
       falls through to Noto Sans Georgian while Latin stays on Inter. -->
  <link href="<?= ds_asset('/vendor/google-fonts/css/fonts.css') ?>" rel="stylesheet">

  <link href="<?= ds_asset('/vendor/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/vendor/bootstrap-icons/font/bootstrap-icons.min.css') ?>" rel="stylesheet">
  <!-- BPG Arial Caps: Mtavruli-style glyphs mapped onto normal Mkhedruli codepoints -->
  <link href="<?= ds_asset('/assets/fonts/bpg-arial-caps/css/bpg-arial-caps.min.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/assets/css/design-system.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/vendor/table/css/ds-table.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/vendor/floating-label/css/floating-label.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/vendor/select/css/ds-select.css') ?>" rel="stylesheet">
  <!-- flatpickr powers ds-date-range's calendar (4.106). v4.6.13, ლოკალურად
       vendor/-ში (4.110) — CDN-ზე აღარაა დამოკიდებული, offline-ზეც მუშაობს. -->
  <link href="<?= ds_asset('/vendor/flatpickr/css/flatpickr.min.css') ?>" rel="stylesheet">
  <link href="<?= ds_asset('/vendor/date-range/css/ds-date-range.css') ?>" rel="stylesheet">
  <!-- gb_symbols: icon set requested for project-specific glyphs (e.g. gb_rs) not in Bootstrap Icons -->
  <link href="<?= ds_asset('/assets/fonts/gb/style.css') ?>" rel="stylesheet">
</head>
<body>

<?php require APP_PATH . '/Views/partials/sidebar.php'; ?>

<div class="ds-main">
  <?php require APP_PATH . '/Views/partials/topbar.php'; ?>
  <?php $impersonatingId = \App\Core\Auth::impersonating(); ?>
  <?php if ($impersonatingId !== null): ?>
    <?php
      $impersonatedTenant  = \App\Models\User::findById($impersonatingId);
      $impersonatedLabel   = $impersonatedTenant['name'] ?? '?';
      // Narrowed to one specific sub-user (4.86) — say so in the banner too,
      // since orders.php/the dashboard now show only their own numbers, not
      // the whole team's; same person as the tenant itself shows just the
      // tenant name, unchanged.
      $impersonatingUserId = \App\Core\Auth::impersonatingUserId();
      if ($impersonatingUserId !== null && $impersonatingUserId !== $impersonatingId) {
          $impersonatedUser  = \App\Models\User::findById($impersonatingUserId);
          $impersonatedLabel .= ' → ' . ($impersonatedUser['name'] ?? '?');
      }
    ?>
    <div class="alert alert-warning rounded-0 mb-0 py-2 px-3 d-flex align-items-center justify-content-between small">
      <span><i class="bi bi-eye-fill me-2"></i><?= t('superuser.impersonating_banner', e($impersonatedLabel)) ?></span>
      <form method="post" action="/superuser/stop" class="mb-0">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-dark"><?= t('superuser.stop_impersonating') ?></button>
      </form>
    </div>
  <?php endif; ?>
  <main class="ds-content">
    <?= $content ?>
  </main>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3" id="dsToastContainer" style="z-index:1080;"></div>

<!-- Notification catalog (app/config/notifications.php), text pre-resolved in
     the current locale — see app.js's dsNotifyCode(code). -->
<script>window.dsNotifications = <?= json_encode(array_map(
    static fn(array $n): array => ['type' => $n['type'], 'text' => t($n['key'])],
    \App\Core\Notifications::all()
), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<!-- dsNotify()'s own toast-header text (4.83 in handoff.md — standard
     Bootstrap toast layout: icon, app name, timestamp, close). -->
<script>
  window.dsAppName = <?= json_encode(app_name(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  window.dsToastJustNow = <?= json_encode(t('toast.just_now'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="<?= ds_asset('/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= ds_asset('/assets/js/app.js') ?>"></script>
<script src="<?= ds_asset('/vendor/floating-label/js/floating-label.js') ?>"></script>
<script src="<?= ds_asset('/vendor/select/js/ds-select.js') ?>"></script>
<script src="<?= ds_asset('/vendor/flatpickr/js/flatpickr.min.js') ?>"></script>
<script src="<?= ds_asset('/vendor/flatpickr/js/l10n/ka.js') ?>"></script>
<script src="<?= ds_asset('/vendor/date-range/js/ds-date-range.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
