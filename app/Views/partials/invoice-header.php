<?php
/**
 * @var array  $org        the organization row (Organization::get())
 * @var string $uploadUrl  '/assets/uploads/organization/', set by the including view
 *
 * Logo + org contact block, shared between invoice-view.php (the printable
 * document) and invoices.php (the create/edit form, so it reads as "this is
 * who's issuing this" while filling it in too) — not the print-page's
 * toolbar (number/date + Save PDF/Print), that part is specific to viewing
 * an already-saved invoice.
 */
?>
<div class="row mb-4 pb-4 border-bottom align-items-center">
  <div class="col-md-6">
    <?php if ($org['logo'] !== null): ?>
      <img src="<?= e($uploadUrl . $org['logo']) ?>" alt="<?= e($org['name']) ?>" style="max-width:180px;max-height:100px;object-fit:contain;">
    <?php else: ?>
      <div class="h4 fw-bold mb-0"><?= e($org['name']) ?></div>
    <?php endif; ?>
  </div>
  <div class="col-md-6 text-md-end mt-3 mt-md-0">
    <div class="fw-semibold"><?= e($org['name']) ?><?php if ($org['tax_id'] !== null): ?> — <?= e($org['tax_id']) ?><?php endif; ?></div>
    <?php foreach (['address', 'phone', 'email', 'website'] as $f): ?>
      <?php if ($org[$f] !== null): ?><div class="text-secondary small"><?= e($org[$f]) ?></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
