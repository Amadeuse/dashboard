<div class="mb-4">
  <h1 class="h3 fw-bold mb-1"><?= t('sg.title') ?></h1>
  <p class="text-secondary mb-0"><?= t('sg.intro') ?></p>
</div>

<!-- Colors -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h5 fw-bold mb-3"><?= t('sg.colors') ?></h2>
    <div class="row g-3">
      <?php foreach ($colors as $c): ?>
      <div class="col-6 col-md-3">
        <div class="ds-swatch bg-<?= $c['name'] ?> mb-2"></div>
        <div class="small fw-semibold text-capitalize"><?= $c['name'] ?></div>
        <div class="text-secondary" style="font-size:.78rem;"><?= t("color.{$c['name']}") ?> · <?= $c['hex'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Typography -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h5 fw-bold mb-3"><?= t('sg.typography') ?></h2>
    <h1><?= t('sg.h1') ?></h1>
    <h2><?= t('sg.h2') ?></h2>
    <h3><?= t('sg.h3') ?></h3>
    <h4><?= t('sg.h4') ?></h4>
    <p class="lead mb-2"><?= t('sg.lead') ?></p>
    <p class="mb-2"><?= t('sg.body') ?></p>
    <p class="small text-secondary mb-0"><?= t('sg.small') ?></p>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Buttons -->
  <div class="col-lg-6">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3"><?= t('sg.buttons') ?></h2>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-primary">Primary</button>
          <button class="btn btn-secondary">Secondary</button>
          <button class="btn btn-success">Success</button>
          <button class="btn btn-danger">Danger</button>
          <button class="btn btn-warning">Warning</button>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="btn btn-outline-primary"><?= t('sg.outline') ?></button>
          <button class="btn btn-outline-secondary"><?= t('sg.outline') ?></button>
          <button class="btn btn-primary" disabled><?= t('sg.disabled') ?></button>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <button class="btn btn-primary btn-sm"><?= t('sg.size_small') ?></button>
          <button class="btn btn-primary"><?= t('sg.size_default') ?></button>
          <button class="btn btn-primary btn-lg"><?= t('sg.size_large') ?></button>
        </div>
      </div>
    </div>
  </div>

  <!-- Badges & Alerts -->
  <div class="col-lg-6">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3"><?= t('sg.badges') ?></h2>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">Primary</span>
          <span class="badge bg-success-subtle text-success-emphasis rounded-pill"><?= t('status.paid') ?></span>
          <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill"><?= t('status.pending') ?></span>
          <span class="badge bg-danger-subtle text-danger-emphasis rounded-pill"><?= t('status.rejected') ?></span>
          <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill"><?= t('status.cancelled') ?></span>
        </div>
        <h2 class="h5 fw-bold mb-3"><?= t('sg.alerts') ?></h2>
        <div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle-fill me-2"></i><?= t('sg.alert_success') ?></div>
        <div class="alert alert-warning py-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= t('sg.alert_warning') ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Forms -->
  <div class="col-lg-6">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3"><?= t('sg.forms') ?></h2>
        <div class="form-floating mb-3">
          <input type="text" class="form-control" id="fgName" placeholder=" ">
          <label for="fgName"><?= t('sg.field_name') ?></label>
        </div>
        <div class="mb-3">
          <select class="form-select">
            <option><?= t('sg.select_role') ?></option>
            <option><?= t('sg.role_admin') ?></option>
            <option><?= t('sg.role_editor') ?></option>
          </select>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="fgCheck" checked>
          <label class="form-check-label small" for="fgCheck"><?= t('sg.remember') ?></label>
        </div>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="fgSwitch" checked>
          <label class="form-check-label small" for="fgSwitch"><?= t('sg.notifications') ?></label>
        </div>
      </div>
    </div>
  </div>

  <!-- Avatars & spacing -->
  <div class="col-lg-6">
    <div class="card ds-card h-100">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3"><?= t('sg.avatars') ?></h2>
        <div class="d-flex gap-2 mb-4">
          <span class="ds-avatar" style="background:#4f46e5;">NK</span>
          <span class="ds-avatar" style="background:#22c55e;">LG</span>
          <span class="ds-avatar" style="background:#f59e0b;">AC</span>
          <span class="ds-avatar" style="background:#ef4444;">BL</span>
        </div>
        <h2 class="h5 fw-bold mb-3"><?= t('sg.spacing') ?></h2>
        <?php foreach ([1, 2, 3, 4, 5] as $s): ?>
        <div class="d-flex align-items-center gap-3 mb-1">
          <span class="text-secondary small" style="width:70px;">spacer-<?= $s ?></span>
          <div class="bg-primary" style="height:10px;width:<?= $s * 16 ?>px;border-radius:4px;"></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Select (ds-select) -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h5 fw-bold mb-3"><?= t('sg.select') ?></h2>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="small text-secondary mb-2"><?= t('sg.select_single_label') ?></div>
        <select class="form-select" id="sgCountry" data-ds-select
                data-search-placeholder="<?= t('table.search') ?>"
                data-no-results="<?= t('table.empty') ?>"
                data-clear-label="<?= t('cust.clear_field') ?>">
          <option value=""></option>
          <option value="ge">Georgia</option>
          <option value="de">Germany</option>
          <option value="fr">France</option>
          <option value="it">Italy</option>
          <option value="es">Spain</option>
          <option value="tr">Türkiye</option>
          <option value="am" disabled>Armenia (disabled)</option>
          <option value="az">Azerbaijan</option>
        </select>
        <label for="sgCountry"><?= t('sg.select_country_label') ?></label>
      </div>

      <div class="col-md-4">
        <div class="small text-secondary mb-2"><?= t('sg.select_multi_label') ?></div>
        <select class="form-select" id="sgSkills" multiple data-ds-select
                data-search-placeholder="<?= t('table.search') ?>"
                data-no-results="<?= t('table.empty') ?>">
          <option value="php" selected>PHP</option>
          <option value="js" selected>JavaScript</option>
          <option value="css">CSS</option>
          <option value="sql">SQL</option>
          <option value="docker">Docker</option>
        </select>
        <label for="sgSkills"><?= t('sg.select_skills_label') ?></label>
      </div>

      <div class="col-md-4">
        <div class="small text-secondary mb-2"><?= t('sg.select_disabled_label') ?></div>
        <select class="form-select" id="sgDisabled" disabled data-ds-select>
          <option>Option A</option>
          <option>Option B</option>
        </select>
        <label for="sgDisabled"><?= t('sg.select_option_label') ?></label>
      </div>
    </div>
  </div>
</div>

<!-- Radius token -->
<div class="card ds-card">
  <div class="card-body">
    <h2 class="h5 fw-bold mb-3"><?= t('sg.radius') ?></h2>
    <p class="text-secondary small"><?= t('sg.radius_note') ?></p>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="p-3 bg-body-tertiary rounded">--ds-radius · .5rem</div>
      </div>
      <div class="col-md-4">
        <div class="p-3 bg-body-tertiary border rounded">border + rounded</div>
      </div>
      <div class="col-md-4">
        <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill">rounded-pill</span>
        <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">badge</span>
      </div>
    </div>
  </div>
</div>
