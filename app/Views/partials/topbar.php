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

  <?php $otherLang = ds_lang() === 'ka' ? 'en' : 'ka'; ?>
  <a class="ds-icon-btn" href="<?= ds_lang_url($otherLang) ?>" title="<?= t('topbar.language') ?>" aria-label="<?= t('topbar.language') ?>">
      <?php if (ds_lang() === 'ka'): ?>
        <svg class="ds-flag" viewBox="0 0 900 600" width="21" height="14" aria-hidden="true">
          <defs>
            <path id="ds-cross" d="M-60-20h40v-40h40v40h40v40h-40v40h-40v-40h-40z"/>
          </defs>
          <rect width="900" height="600" fill="#fff"/>
          <g fill="#ff0000">
            <rect x="390" width="120" height="600"/>
            <rect y="240" width="900" height="120"/>
            <use href="#ds-cross" x="195" y="120"/>
            <use href="#ds-cross" x="705" y="120"/>
            <use href="#ds-cross" x="195" y="480"/>
            <use href="#ds-cross" x="705" y="480"/>
          </g>
        </svg>
      <?php else: ?>
        <svg class="ds-flag" viewBox="0 0 60 30" width="21" height="14" preserveAspectRatio="none" aria-hidden="true">
          <clipPath id="ds-uk"><path d="M0 0v30h60V0z"/></clipPath>
          <rect width="60" height="30" fill="#012169"/>
          <path d="M0 0l60 30M60 0L0 30" stroke="#fff" stroke-width="6"/>
          <path d="M0 0l60 30M60 0L0 30" stroke="#c8102e" stroke-width="4" clip-path="url(#ds-uk)"/>
          <path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/>
          <path d="M30 0v30M0 15h60" stroke="#c8102e" stroke-width="6"/>
        </svg>
      <?php endif; ?>
  </a>

  <div class="ds-search input-group ms-1">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="search" class="form-control" placeholder="<?= t('topbar.search') ?>" aria-label="<?= t('topbar.search') ?>">
  </div>

  <div class="ms-auto d-flex align-items-center gap-1">
    <div class="dropdown">
      <button class="btn ds-user-btn d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
        <span class="ds-avatar ds-avatar-soft">A</span>
        <span class="d-none d-md-inline small fw-semibold"><?= t('topbar.role') ?></span>
        <i class="bi bi-chevron-down ds-caret"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i><?= t('topbar.profile') ?></a></li>
        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i><?= t('topbar.settings') ?></a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="#"><i class="bi bi-box-arrow-right me-2"></i><?= t('topbar.logout') ?></a></li>
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

    <button class="ds-icon-btn" type="button" aria-label="<?= t('topbar.apps') ?>">
      <i class="bi bi-grid-3x3-gap-fill"></i>
    </button>
  </div>
</header>
