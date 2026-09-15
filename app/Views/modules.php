<?php
/**
 * Modules settings page (4.113).
 *
 * Four states a module can be in, and the page shows exactly which one it is,
 * because the buttons differ in how destructive they are:
 *
 *   on disk, not installed → Install (runs its migrations)  |  Delete files
 *   installed, off         → Enable                          |  Uninstall
 *   installed, on          → Disable                         |  Uninstall
 *
 * Uninstall is the only one that touches data, so it is the only one behind a
 * confirmation modal — and that modal says where the data is exported before
 * it goes.
 *
 * @var array               $modules   code, name, description, version, installedVersion, author, installed, enabled, icon
 * @var array<string,string> $failures  code => error message, modules that threw while loading
 * @var ?string             $flash
 * @var ?string             $error
 */
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><?= e(app_name()) ?></a></li>
        <li class="breadcrumb-item active"><?= t('page.modules') ?></li>
      </ol>
    </nav>
    <h1 class="h4 fw-bold mb-0"><?= t('page.modules') ?></h1>
  </div>
  <a href="/help/modules" class="btn btn-outline-secondary">
    <i class="bi bi-book me-1"></i><?= t('help.modules_title') ?>
  </a>
</div>

<?php if ($error !== null): ?>
  <div class="alert alert-danger d-flex align-items-start gap-2">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i><div><?= e($error) ?></div>
  </div>
<?php endif; ?>

<?php foreach ($failures as $failedCode => $message): ?>
  <div class="alert alert-warning d-flex align-items-start gap-2">
    <i class="bi bi-plug-fill mt-1"></i>
    <div>
      <strong><?= t('modules.failed_title') ?></strong><br>
      <?= t('modules.failed_body', e((string) $failedCode), e($message)) ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-1"><?= t('modules.upload_title') ?></h2>
    <p class="text-secondary small mb-3"><?= t('modules.upload_hint') ?></p>
    <form method="post" action="/settings/modules/upload" enctype="multipart/form-data" class="d-flex flex-wrap gap-2">
      <?= csrf_field() ?>
      <input type="file" name="module" accept=".zip,application/zip" class="form-control" style="max-width:24rem" required>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-upload me-1"></i><?= t('modules.upload_button') ?>
      </button>
    </form>
  </div>
</div>

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
              <h2 class="h6 fw-bold mb-0">
                <i class="bi <?= e($m['icon']) ?> me-1 text-secondary"></i><?= e($m['name']) ?>
              </h2>
              <?php if (!$m['installed']): ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= t('modules.not_installed') ?></span>
              <?php elseif ($m['enabled']): ?>
                <span class="badge bg-success-subtle text-success-emphasis rounded-pill"><?= t('modules.enabled') ?></span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill"><?= t('modules.disabled') ?></span>
              <?php endif; ?>
            </div>

            <?php if ($m['description'] !== null): ?>
              <p class="text-secondary small mb-3"><?= e($m['description']) ?></p>
            <?php endif; ?>

            <div class="small text-secondary mb-3 mt-auto">
              <div><?= t('modules.version') ?>: <code><?= e($m['version']) ?></code></div>
              <?php if ($m['author'] !== null): ?>
                <div><?= t('modules.author') ?>: <?= e($m['author']) ?></div>
              <?php endif; ?>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <?php if (!$m['installed']): ?>
                <form method="post" action="/settings/modules/install" class="mb-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-sm btn-primary"><?= t('modules.install') ?></button>
                </form>
                <form method="post" action="/settings/modules/remove-files" class="mb-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><?= t('modules.remove_files') ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="/settings/modules/<?= $m['enabled'] ? 'disable' : 'enable' ?>" class="mb-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-sm <?= $m['enabled'] ? 'btn-outline-secondary' : 'btn-primary' ?>">
                    <?= $m['enabled'] ? t('modules.disable') : t('modules.enable') ?>
                  </button>
                </form>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#uninstall-<?= e($m['code']) ?>">
                  <?= t('modules.uninstall') ?>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <?php if ($m['installed']): ?>
        <div class="modal fade" id="uninstall-<?= e($m['code']) ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h3 class="modal-title h6 fw-bold"><?= t('modules.confirm_uninstall_title', e($m['name'])) ?></h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('common.close') ?>"></button>
              </div>
              <div class="modal-body">
                <p class="mb-2"><?= t('modules.confirm_uninstall_body') ?></p>
                <?php $used = count(\App\Core\ModuleRegistry::tenantsUsing($m['code'])); ?>
                <?php if ($used > 0): ?>
                  <p class="text-warning-emphasis small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= t('modules.confirm_uninstall_used', $used) ?>
                  </p>
                <?php endif; ?>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= t('common.cancel') ?></button>
                <form method="post" action="/settings/modules/uninstall" class="mb-0">
                  <?= csrf_field() ?>
                  <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                  <button type="submit" class="btn btn-danger"><?= t('modules.uninstall') ?></button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php $scripts = ds_flash_toast($flash !== null ? e($flash) : null); ?>
