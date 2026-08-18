<?php
/**
 * @var array    $modules  each: code, name, description, version, installed, enabled
 * @var ?string  $flash    "installed" confirmation message, if any
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('page.modules') ?></li>
      </ol>
    </nav>
  </div>
</div>

<?php if ($flash !== null): ?>
  <div class="alert alert-success fade show d-flex align-items-center gap-2 ds-alert-autodismiss" role="alert">
    <i class="bi bi-check-circle-fill"></i> <?= e($flash) ?>
  </div>
<?php endif; ?>

<?php if ($modules === []): ?>
  <div class="card ds-card">
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-puzzle d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('modules.empty') ?>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($modules as $m): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card ds-card h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <h2 class="h6 fw-bold mb-0"><?= e($m['name']) ?></h2>
              <?php if (!$m['installed']): ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= t('modules.not_installed') ?></span>
              <?php elseif ($m['enabled']): ?>
                <span class="badge bg-success-subtle text-success-emphasis rounded-pill"><?= t('modules.enabled') ?></span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill"><?= t('modules.disabled') ?></span>
              <?php endif; ?>
            </div>

            <?php if ($m['description'] !== null): ?>
              <p class="text-secondary small mb-2"><?= e($m['description']) ?></p>
            <?php endif; ?>
            <div class="text-secondary small mb-3">v<?= e($m['version']) ?></div>

            <div class="mt-auto d-flex gap-2">
              <?php if (!$m['installed']): ?>
                <form method="post" action="/settings/modules/install">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-primary btn-sm"><?= t('modules.install') ?></button>
                </form>
              <?php elseif ($m['enabled']): ?>
                <form method="post" action="/settings/modules/disable">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-outline-secondary btn-sm"><?= t('modules.disable') ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/settings/modules/enable">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-primary btn-sm"><?= t('modules.enable') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
