<?php
/**
 * @var array   $rows           every invoice, newest first — customer_name/
 *                               customer_taxid and the creator's name
 *                               (creator_name, nullable) joined in by
 *                               Invoice::all()
 * @var string  $invoicePrefix  organization.invoice_prefix, or "INV" if unset
 * @var string  $currency       organization.currency ('GEL' or 'USD')
 * @var int     $total          row count
 *
 * The list half of what used to be one /invoices page (see 4.25 in
 * handoff.md) — creating/editing lives on /invoices, this is browsing.
 * Reached from the sidebar (შეკვეთები > ყველა შეკვეთა), not its own
 * top-level item. Each row is two plain links, not a click-to-edit row:
 * editing now means a real navigation to /invoices?edit=N (that page loads
 * the invoice into its form server-side), so there's nothing left for a
 * row click to do in-place — an edit pencil + a print icon are more
 * honest than a "clickable" row that silently didn't used to do anything
 * here.
 */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);

// '0' is how legacy/imported rows spell "no tax id" (see Customer.php's
// docblock) — same treatment invoices.php's customer-info panel gives it.
$taxId = static function (array $row): string {
    $value = (string) ($row['customer_taxid'] ?? '');
    return $value !== '' && $value !== '0' ? e($value) : '<span class="text-secondary">—</span>';
};
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active">
          <?= t('nav.orders_all') ?>
          <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
        </li>
      </ol>
    </nav>
  </div>
  <?php if ($rows !== []): ?>
    <a href="/orders/export-pdf" class="btn btn-outline-secondary">
      <i class="bi bi-file-earmark-pdf me-1"></i> <?= t('orders.export_pdf') ?>
    </a>
  <?php endif; ?>
</div>

<div class="card ds-card">
  <?php if ($rows === []): ?>
    <div class="card-body text-center text-secondary py-5">
      <i class="bi bi-receipt d-block mb-2" style="font-size:2rem;opacity:.4;"></i>
      <?= t('inv.empty') ?>
    </div>
  <?php else: ?>
    <div class="card-body">
    <div class="ds-table" data-ds-table data-per-page="10" data-per-page-options="10,25,50,100">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr class="text-secondary">
            <th><?= t('inv.number') ?></th>
            <th><?= t('inv.customer') ?></th>
            <th><?= t('cust.taxid') ?></th>
            <th><?= t('inv.creator') ?></th>
            <th><?= t('inv.total') ?> (<?= e(currency_symbol($currency)) ?>)</th>
            <th><?= t('inv.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $inv): ?>
          <tr>
            <td><?= e($invoiceNumber($inv)) ?></td>
            <td><?= e($inv['customer_name']) ?></td>
            <td><?= $taxId($inv) ?></td>
            <td><?= $inv['creator_name'] !== null ? e($inv['creator_name']) : '<span class="text-secondary">—</span>' ?></td>
            <td data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.view') ?>"
                      data-bs-toggle="modal" data-bs-target="#invoicePreviewModal"
                      data-invoice-id="<?= (int) $inv['id'] ?>"
                      data-invoice-number="<?= e($invoiceNumber($inv)) ?>"
                      data-invoice-status="<?= e($inv['status']) ?>">
                <i class="bi bi-eye"></i>
              </button>
              <a href="/invoices?edit=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('cust.edit_hint') ?>">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="/invoices/view?id=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.print') ?>">
                <i class="bi bi-printer"></i>
              </a>
              <a href="/invoices/export-pdf?id=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('orders.export_pdf') ?>">
                <i class="bi bi-file-earmark-pdf"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    </div><!-- /.ds-table -->
    </div><!-- /.card-body -->
  <?php endif; ?>
</div>

<?php require APP_PATH . '/Views/partials/invoice-preview-modal.php'; ?>

<?php $scripts = ds_table_script() . ds_invoice_preview_script(); ?>
