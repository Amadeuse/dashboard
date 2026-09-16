<?php
/**
 * The module author's guide (4.113) — the documentation the first module
 * system never had, which is the direct reason it collapsed: with no written
 * contract, both modules put their strings in core lang files and their
 * markup inside core views, and removing one left traces behind in core.
 *
 * Written as a view, not a Markdown file, so the hook table below stays next
 * to the running app rather than drifting from it. When a hook point is added
 * or changed, this file is where it gets documented — that is the deal.
 */
$hooks = [
    [
        'point'   => 'invoice.list.data',
        'kind'    => 'data',
        'where'   => 'InvoiceController::orders()',
        'context' => "['ids' => list<int>]",
        'returns' => "['YourCode' => [invoiceId => mixed]]",
        'note'    => t('help.mod_hook_list_data'),
    ],
    [
        'point'   => 'render.invoice.row.badges',
        'kind'    => 'render',
        'where'   => 'orders.php',
        'context' => "['invoice' => array, 'data' => array]",
        'returns' => 'HTML string',
        'note'    => t('help.mod_hook_row_badges'),
    ],
    [
        'point'   => 'render.invoice.form.aside',
        'kind'    => 'render',
        'where'   => 'invoices.php',
        'context' => "['invoice' => ?array]",
        'returns' => 'HTML string',
        'note'    => t('help.mod_hook_form_aside'),
    ],
    [
        'point'   => 'invoice.saved',
        'kind'    => 'event',
        'where'   => 'InvoiceController::store()',
        'context' => "['id' => int, 'isNew' => bool]",
        'returns' => '—',
        'note'    => t('help.mod_hook_saved'),
    ],
];

$kindClass = [
    'data'   => 'bg-info-subtle text-info-emphasis',
    'render' => 'bg-primary-subtle text-primary-emphasis',
    'event'  => 'bg-secondary-subtle text-secondary-emphasis',
];
?>

<div class="mb-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-1">
      <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><?= e(app_name()) ?></a></li>
      <li class="breadcrumb-item"><a href="/help" class="text-decoration-none"><?= t('page.help') ?></a></li>
      <li class="breadcrumb-item active"><?= t('help.modules_title') ?></li>
    </ol>
  </nav>
  <h1 class="h4 fw-bold mb-1"><?= t('help.modules_title') ?></h1>
  <p class="text-secondary mb-0"><?= t('help.mod_lead') ?></p>
</div>

<div class="alert alert-primary d-flex align-items-start gap-2">
  <i class="bi bi-info-circle-fill mt-1"></i>
  <div><strong><?= t('help.mod_rule_title') ?></strong> <?= t('help.mod_rule_body') ?></div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3"><?= t('help.mod_concept') ?></h2>
    <p class="small text-secondary"><?= t('help.mod_concept_intro') ?></p>
    <div class="table-responsive">
      <table class="table table-sm align-middle small mb-0">
        <thead><tr><th style="width:2rem">#</th><th><?= t('help.mod_concept_rule') ?></th><th><?= t('help.mod_concept_enforced') ?></th></tr></thead>
        <tbody>
          <tr><td>1</td><td><?= t('help.mod_concept_1') ?></td><td><code>ModuleLint</code> — <?= t('help.mod_concept_1e') ?></td></tr>
          <tr><td>2</td><td><?= t('help.mod_concept_2') ?></td><td><code>ds_menu()</code> — <?= t('help.mod_concept_2e') ?></td></tr>
          <tr><td>3</td><td><?= t('help.mod_concept_3') ?></td><td><code>ModuleDb</code> + <code>ModuleLint</code> — <?= t('help.mod_concept_3e') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">1. <?= t('help.mod_structure') ?></h2>
    <div class="table-responsive">
      <pre class="small mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">app/Modules/<b>YourCode</b>/
├── module.json        <?= t('help.mod_f_manifest') ?>

├── Module.php         <?= t('help.mod_f_entry') ?>

├── lang/ka.php        <?= t('help.mod_f_lang') ?>

│   └── en.php
├── assets/module.css  <?= t('help.mod_f_assets') ?>

│   └── module.js
├── Controllers/       <?= t('help.mod_f_mvc') ?>

├── Models/
├── Views/
├── menu.json          <?= t('help.mod_f_menu') ?>

├── migrations/*.sql   <?= t('help.mod_f_migrations') ?>

└── uninstall.sql      <?= t('help.mod_f_uninstall') ?>
</pre>
    </div>
    <p class="small text-secondary mb-0"><?= t('help.mod_code_rule') ?></p>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">2. module.json</h2>
    <pre class="small mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">{
  "name":        "yc.module_name",
  "description": "yc.module_description",
  "version":     "1.0.0",
  "author":      "Your Name",
  "icon":        "bi-box"
}</pre>
    <p class="small text-secondary mb-0"><?= t('help.mod_manifest_note') ?></p>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">3. Module.php</h2>
    <pre class="small mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">&lt;?php
namespace App\Modules\<b>YourCode</b>;

use App\Core\{Hooks, Lang, ModuleInterface, ModuleRouter};

final class Module implements ModuleInterface
{
    public function register(ModuleRouter $router): void
    {
        $router-&gt;get('/list',  [Controllers\YourController::class, 'index']);
        $router-&gt;post('/save', [Controllers\YourController::class, 'save']);

        Lang::loadModule('<b>YourCode</b>');

        Hooks::on('render.invoice.row.badges', static function (array $ctx): string {
            return '&lt;span class="badge bg-info-subtle"&gt;' . e(t('yc.hello')) . '&lt;/span&gt;';
        });
    }
}</pre>
    <p class="small text-secondary mb-0"><?= t('help.mod_router_note') ?></p>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">4. <?= t('help.mod_hooks') ?></h2>
    <p class="small text-secondary"><?= t('help.mod_hooks_intro') ?></p>
    <div class="table-responsive">
      <table class="table table-sm align-middle small mb-3">
        <thead>
          <tr>
            <th><?= t('help.mod_h_point') ?></th>
            <th><?= t('help.mod_h_kind') ?></th>
            <th><?= t('help.mod_h_context') ?></th>
            <th><?= t('help.mod_h_returns') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hooks as $h): ?>
            <tr>
              <td>
                <code><?= e($h['point']) ?></code>
                <div class="text-secondary" style="font-size:.8em;"><?= e($h['note']) ?></div>
              </td>
              <td><span class="badge rounded-pill <?= $kindClass[$h['kind']] ?>"><?= e($h['kind']) ?></span></td>
              <td><code><?= e($h['context']) ?></code></td>
              <td><code><?= e($h['returns']) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="small text-secondary mb-0"><?= t('help.mod_hooks_more') ?></p>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">4a. <?= t('help.mod_db') ?></h2>
    <p class="small text-secondary"><?= t('help.mod_db_intro') ?></p>
    <pre class="small mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">$db = ModuleDb::for('<b>YourCode</b>');

$db-&gt;select('SELECT … FROM invoices WHERE created_by IN (…)', $ids);   <span class="text-success-emphasis">// ✓ <?= t('help.mod_db_read') ?></span>
$db-&gt;execute('UPDATE your_table SET … WHERE id = ? AND ruler = ?', …); <span class="text-success-emphasis">// ✓ <?= t('help.mod_db_own') ?></span>
$db-&gt;execute('UPDATE invoices SET total = 0');                         <span class="text-danger-emphasis">// ✗ ModuleDbException</span></pre>
    <p class="small text-secondary mb-0"><?= t('help.mod_db_note') ?></p>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">5. <?= t('help.mod_visual') ?></h2>
    <div class="row g-3">
      <div class="col-md-6">
        <p class="small fw-semibold text-success-emphasis mb-2">
          <i class="bi bi-check-circle-fill me-1"></i><?= t('help.mod_do') ?>
        </p>
        <ul class="small text-secondary mb-0">
          <li><?= t('help.mod_do_1') ?></li>
          <li><?= t('help.mod_do_2') ?></li>
          <li><?= t('help.mod_do_3') ?></li>
        </ul>
      </div>
      <div class="col-md-6">
        <p class="small fw-semibold text-danger-emphasis mb-2">
          <i class="bi bi-x-circle-fill me-1"></i><?= t('help.mod_dont') ?>
        </p>
        <ul class="small text-secondary mb-0">
          <li><?= t('help.mod_dont_1') ?></li>
          <li><?= t('help.mod_dont_2') ?></li>
          <li><?= t('help.mod_dont_3') ?></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">6. <?= t('help.mod_packaging') ?></h2>
    <p class="small text-secondary mb-2"><?= t('help.mod_zip_note') ?></p>
    <pre class="small mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">YourCode.zip
└── YourCode/
    ├── module.json
    └── Module.php  …</pre>
    <ul class="small text-secondary mb-0">
      <li><?= t('help.mod_zip_1') ?></li>
      <li><?= t('help.mod_zip_2') ?></li>
      <li><?= t('help.mod_zip_3') ?></li>
    </ul>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="card ds-card mb-4">
  <div class="card-body">
    <h2 class="h6 fw-bold mb-3">7. <?= t('help.mod_lifecycle') ?></h2>
    <ol class="small text-secondary mb-0">
      <li><strong><?= t('modules.upload_button') ?></strong> — <?= t('help.mod_life_upload') ?></li>
      <li><strong><?= t('modules.install') ?></strong> — <?= t('help.mod_life_install') ?></li>
      <li><strong><?= t('modules.enable') ?></strong> — <?= t('help.mod_life_enable') ?></li>
      <li><strong><?= t('modules.uninstall') ?></strong> — <?= t('help.mod_life_uninstall') ?></li>
    </ol>
  </div>
</div>

<!-- ------------------------------------------------------------------ -->
<div class="alert alert-warning d-flex align-items-start gap-2">
  <i class="bi bi-shield-exclamation mt-1"></i>
  <div>
    <strong><?= t('help.mod_security_title') ?></strong>
    <p class="mb-1"><?= t('help.mod_security_body') ?></p>
    <ul class="small mb-0">
      <li><?= t('help.mod_security_1') ?></li>
      <li><?= t('help.mod_security_2') ?></li>
      <li><?= t('help.mod_security_3') ?></li>
    </ul>
  </div>
</div>

<p class="text-secondary small">
  <i class="bi bi-code-square me-1"></i><?= t('help.mod_reference') ?>
</p>
