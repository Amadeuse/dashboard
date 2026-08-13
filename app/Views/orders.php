<?php
/**
 * @var array   $rows           every invoice, newest first, customer_name joined in
 *                               (status/is_zero/is_recurring come along for free — Invoice::all() is `SELECT i.*`)
 * @var string  $invoicePrefix  organization.invoice_prefix, or "INV" if unset
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

$statusBadgeClass = [
    'draft' => 'bg-secondary-subtle text-secondary-emphasis',
    'final' => 'bg-info-subtle text-info-emphasis',
    'due'   => 'bg-warning-subtle text-warning-emphasis',
    'paid'  => 'bg-success-subtle text-success-emphasis',
];
$statusLabel = [
    'draft' => t('inv.status_draft'),
    'final' => t('inv.status_final'),
    'due'   => t('inv.status_due'),
    'paid'  => t('inv.status_paid'),
];
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small mb-1">
        <li class="breadcrumb-item"><a href="/" class="text-decoration-none">Nova</a></li>
        <li class="breadcrumb-item active"><?= t('nav.orders_all') ?></li>
      </ol>
    </nav>
    <h1 class="h3 fw-bold mb-0">
      <?= t('nav.orders_all') ?>
      <span class="badge bg-primary-subtle text-primary rounded-pill align-middle ms-1"><?= $total ?></span>
    </h1>
  </div>
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
            <th><?= t('inv.issue_date') ?></th>
            <th><?= t('inv.total') ?></th>
            <th><?= t('inv.status_label') ?></th>
            <th><?= t('inv.type_label') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $inv): ?>
          <tr>
            <td><?= e($invoiceNumber($inv)) ?></td>
            <td><?= e($inv['customer_name']) ?></td>
            <td data-order="<?= e($inv['issue_date']) ?>"><?= e(ds_date($inv['issue_date'])) ?></td>
            <td data-order="<?= (float) $inv['total'] ?>"><?= number_format((float) $inv['total'], 2) ?></td>
            <td>
              <span class="badge rounded-pill <?= $statusBadgeClass[$inv['status']] ?? $statusBadgeClass['draft'] ?>">
                <?= e($statusLabel[$inv['status']] ?? $inv['status']) ?>
              </span>
            </td>
            <td>
              <?php if ((int) $inv['is_zero'] === 1): ?>
                <span class="badge bg-dark-subtle text-dark-emphasis rounded-pill"><?= t('inv.flag_zero') ?></span>
              <?php endif; ?>
              <?php if ((int) $inv['is_recurring'] === 1): ?>
                <span class="badge bg-primary-subtle text-primary rounded-pill"><?= t('inv.flag_recurring') ?></span>
              <?php endif; ?>
              <?php if ((int) $inv['is_zero'] !== 1 && (int) $inv['is_recurring'] !== 1): ?>
                <span class="text-secondary">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a href="/invoices?edit=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('cust.edit_hint') ?>">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="/invoices/view?id=<?= (int) $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" title="<?= t('inv.print') ?>">
                <i class="bi bi-printer"></i>
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

<?php $scripts = ds_table_script(); ?>
