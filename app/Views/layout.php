<!doctype html>
<html lang="<?= ds_lang() ?>" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Nova Dashboard') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Inter has no Georgian glyphs (latin/greek/cyrillic/vietnamese only), so Georgian
       falls through to Noto Sans Georgian while Latin stays on Inter. -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- BPG Arial Caps: Mtavruli-style glyphs mapped onto normal Mkhedruli codepoints -->
  <link href="/assets/fonts/bpg-arial-caps/css/bpg-arial-caps.min.css" rel="stylesheet">
  <link href="/assets/css/design-system.css" rel="stylesheet">
  <link href="/vendor/table/css/ds-table.css" rel="stylesheet">
  <link href="/vendor/floating-label/css/floating-label.css" rel="stylesheet">
  <link href="/vendor/select/css/ds-select.css" rel="stylesheet">
  <!-- gb_symbols: icon set requested for project-specific glyphs (e.g. gb_rs) not in Bootstrap Icons -->
  <link href="/assets/fonts/gb/style.css" rel="stylesheet">
</head>
<body>

<?php require APP_PATH . '/Views/partials/sidebar.php'; ?>

<div class="ds-main">
  <?php require APP_PATH . '/Views/partials/topbar.php'; ?>
  <?php $impersonatingId = \App\Core\Auth::impersonating(); ?>
  <?php if ($impersonatingId !== null): ?>
    <?php $impersonatedTenant = \App\Models\User::findById($impersonatingId); ?>
    <div class="alert alert-warning rounded-0 mb-0 py-2 px-3 d-flex align-items-center justify-content-between small">
      <span><i class="bi bi-eye-fill me-2"></i><?= t('superuser.impersonating_banner', e($impersonatedTenant['name'] ?? '?')) ?></span>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/vendor/floating-label/js/floating-label.js"></script>
<script src="/vendor/select/js/ds-select.js"></script>
<?= $scripts ?? '' ?>
</body>
</html>
