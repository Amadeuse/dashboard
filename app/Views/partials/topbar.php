<header class="ds-topbar d-flex align-items-center px-3 gap-2">
  <!-- mobile: offcanvas trigger / desktop: collapse sidebar -->
  <button class="ds-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="<?= t('topbar.menu') ?>">
    <i class="bi bi-list"></i>
  </button>
  <button class="ds-icon-btn d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-label="<?= t('topbar.collapse') ?>">
    <i class="bi bi-chevron-left ds-collapse-icon"></i>
  </button>

  <button class="ds-icon-btn" type="button" data-theme-toggle title="<?= t('topbar.theme') ?>" aria-label="<?= t('topbar.theme') ?>">
    <i class="bi bi-sun ds-icon-sun"></i>
    <i class="bi bi-moon-stars ds-icon-moon"></i>
  </button>

  <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; $flagIdSuffix = 'topbar'; ?>
  <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php require APP_PATH . '/Views/partials/lang-flag.php'; ?>
  </a>

  <div class="ds-search input-group ms-1">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="search" class="form-control" placeholder="<?= t('topbar.search') ?>" aria-label="<?= t('topbar.search') ?>">
  </div>

  <?php $authUser = \App\Core\Auth::user(); ?>
  <div class="ms-auto d-flex align-items-center gap-1">
    <div class="dropdown">
      <button class="btn ds-user-btn d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
        <?php if ($authUser && $authUser['avatar'] !== null): ?>
          <img class="ds-avatar" src="/assets/uploads/avatars/<?= e($authUser['avatar']) ?>" alt="" style="width:32px;height:32px;object-fit:cover;">
        <?php else: ?>
          <span class="ds-avatar ds-avatar-soft"><?= $authUser ? e(mb_strtoupper(mb_substr($authUser['name'], 0, 1))) : 'A' ?></span>
        <?php endif; ?>
        <span class="d-none d-md-inline small fw-semibold"><?= $authUser ? e($authUser['name']) : t('topbar.role') ?></span>
        <i class="bi bi-chevron-down ds-caret"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="/profile"><i class="bi bi-person me-2"></i><?= t('topbar.profile') ?></a></li>
        <li><a class="dropdown-item" href="/profile/settings"><i class="bi bi-gear me-2"></i><?= t('topbar.settings') ?></a></li>
        <li><hr class="dropdown-divider"></li>
        <?php if ($authUser): ?>
          <li>
            <form method="post" action="/logout" class="m-0">
              <?= csrf_field() ?>
              <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i><?= t('topbar.logout') ?></button>
            </form>
          </li>
        <?php else: ?>
          <li><a class="dropdown-item" href="/login"><i class="bi bi-box-arrow-in-right me-2"></i><?= t('auth.login') ?></a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="dropdown">
      <button class="ds-icon-btn position-relative" type="button" data-bs-toggle="dropdown" aria-label="<?= t('topbar.notifications') ?>">
        <i class="bi bi-bell"></i>
        <span class="ds-dot"></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end p-2" style="width:280px;">
        <li class="dropdown-header px-2"><?= t('topbar.notifications') ?></li>
        <li><a class="dropdown-item rounded-2 small py-2" href="#"><?= t('notif.1') ?></a></li>
        <li><a class="dropdown-item rounded-2 small py-2" href="#"><?= t('notif.2') ?></a></li>
        <li><a class="dropdown-item rounded-2 small py-2" href="#"><?= t('notif.3') ?></a></li>
      </ul>
    </div>

    <?php $navModules = array_filter(\App\Core\ModuleRegistry::summaries(), fn($m) => $m['installed']); ?>
    <div class="dropdown">
      <button class="ds-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="<?= t('topbar.apps') ?>">
        <i class="bi bi-grid-3x3-gap-fill"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end p-2" style="width:260px;">
        <li class="dropdown-header px-2"><?= t('page.modules') ?></li>
        <?php if ($navModules === []): ?>
          <li class="px-2 py-2 small text-secondary"><?= t('modules.empty') ?></li>
        <?php else: ?>
          <?php foreach ($navModules as $m): ?>
            <li class="d-flex align-items-center justify-content-between gap-2 px-2 py-1">
              <span class="small d-flex align-items-center gap-2"><i class="bi <?= e($m['icon']) ?>"></i><?= e($m['name']) ?></span>
              <form method="post" action="/settings/modules/<?= $m['enabled'] ? 'disable' : 'enable' ?>" data-module-toggle class="mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="code" value="<?= e($m['code']) ?>">
                <input type="hidden" name="redirect" value="<?= e(\App\Core\Router::current()) ?>">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input ds-module-switch" type="checkbox" role="switch" <?= $m['enabled'] ? 'checked' : '' ?> aria-label="<?= e($m['name']) ?>">
                </div>
              </form>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item rounded-2 small" href="/settings/modules"><i class="bi bi-puzzle me-2"></i><?= t('page.modules') ?></a></li>
      </ul>
    </div>
  </div>
</header>
