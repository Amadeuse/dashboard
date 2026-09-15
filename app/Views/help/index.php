<?php
/** Help landing page — one card per guide. */
?>
<div class="mb-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-1">
      <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><?= e(app_name()) ?></a></li>
      <li class="breadcrumb-item active"><?= t('page.help') ?></li>
    </ol>
  </nav>
  <h1 class="h4 fw-bold mb-0"><?= t('page.help') ?></h1>
</div>

<div class="row g-3">
  <div class="col-md-6 col-lg-4">
    <a href="/help/modules" class="card ds-card h-100 text-decoration-none">
      <div class="card-body">
        <h2 class="h6 fw-bold mb-2">
          <i class="bi bi-puzzle me-1 text-secondary"></i><?= t('help.modules_title') ?>
        </h2>
        <p class="text-secondary small mb-0"><?= t('help.modules_lead') ?></p>
      </div>
    </a>
  </div>
</div>
