<?php
/**
 * The module's own page, rendered by TemplateController::index() through
 * viewAt(). It is wrapped in the app's normal layout — sidebar, topbar, theme
 * — so it should look like it belongs, not like a guest.
 *
 * Use core's classes (.ds-card, .ds-table, Bootstrap utilities). Anything you
 * add in assets/module.css should be layout only; colour and type come from
 * the tokens.
 *
 * @var list<array<string,mixed>> $items
 * @var array<string,string>      $errors
 * @var array<string,string>      $old
 * @var ?string                   $saved
 */
?>

<div class="mb-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small mb-1">
      <li class="breadcrumb-item"><a href="/" class="text-decoration-none"><?= e(app_name()) ?></a></li>
      <li class="breadcrumb-item active"><?= t('tpl.page_title') ?></li>
    </ol>
  </nav>
  <h1 class="h4 fw-bold mb-0"><?= t('tpl.page_title') ?></h1>
</div>

<div class="card ds-card mb-4">
  <div class="card-body">
    <form method="post" action="/m/moduletemplate/save" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-5">
        <div class="form-floating">
          <input type="text" class="form-control <?= isset($errors['label']) ? 'is-invalid' : '' ?>"
                 id="tpl_label" name="label" placeholder=" " value="<?= e($old['label'] ?? '') ?>">
          <label for="tpl_label"><?= t('tpl.label') ?></label>
          <?php if (isset($errors['label'])): ?>
            <div class="invalid-feedback"><?= e($errors['label']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-md-5">
        <div class="form-floating">
          <input type="text" class="form-control" id="tpl_note" name="note"
                 placeholder=" " value="<?= e($old['note'] ?? '') ?>">
          <label for="tpl_note"><?= t('tpl.note') ?></label>
        </div>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-primary"><?= t('tpl.add') ?></button>
      </div>
    </form>
  </div>
</div>

<?php if ($items === []): ?>
  <div class="card ds-card">
    <div class="card-body text-center text-secondary py-5"><?= t('tpl.empty') ?></div>
  </div>
<?php else: ?>
  <div class="tpl-grid">
    <?php foreach ($items as $item): ?>
      <div class="tpl-item">
        <div class="tpl-item-label"><?= e($item['label']) ?></div>
        <?php if (($item['note'] ?? '') !== ''): ?>
          <div class="small text-secondary mb-2"><?= e((string) $item['note']) ?></div>
        <?php endif; ?>
        <form method="post" action="/m/moduletemplate/delete" class="mb-0">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><?= t('tpl.delete') ?></button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php $scripts = ds_flash_toast($saved !== null ? e($saved) : null); ?>
