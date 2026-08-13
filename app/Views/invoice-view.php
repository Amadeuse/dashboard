<?php
/**
 * @var array  $invoice        invoices row + customer_name/customer_taxid/customer_contact/customer_phone/customer_email/customer_address joined in (Invoice::find())
 * @var string $invoiceNumber  "{prefix}-0007", already formatted
 * @var array  $items          this invoice's line items, product_name joined in (Invoice::itemsFor())
 * @var array  $org            the organization row (Organization::get())
 * @var array  $bankIbans      organization's bank accounts (Organization::bankIbans())
 *
 * A printable document, not a form. The toolbar (number/date + Save PDF/
 * Print) is `.no-print` — design-system.css hides it and the app chrome
 * (sidebar/topbar) together in @media print, so Ctrl+P / the buttons below
 * leave only the card that follows. Neither button generates a real PDF —
 * this project has no PDF library (see handoff.md 4.25/CLAUDE.md's
 * no-Composer stance) — both just call window.print(), and every modern
 * browser's print dialog offers "Save as PDF" as a destination.
 */
$uploadUrl = '/assets/uploads/organization/';
$fmtQty    = static fn(string $q): string => rtrim(rtrim($q, '0'), '.') ?: '0';
?>

<div class="card ds-card mb-3 no-print">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 py-2">
    <div>
      <span class="fw-bold text-primary text-uppercase small"><?= t('inv.number') ?></span>
      <span class="text-secondary mx-1">|</span>
      <span class="fw-semibold"><?= e($invoiceNumber) ?></span>
      <span class="text-secondary ms-2"><?= e(ds_date($invoice['issue_date'])) ?></span>
    </div>
    <div class="d-flex gap-3">
      <button type="button" class="btn btn-link text-decoration-none p-0" onclick="window.print()">
        <i class="bi bi-file-earmark-pdf me-1"></i><?= t('inv.save_pdf') ?>
      </button>
      <button type="button" class="btn btn-link text-decoration-none p-0" onclick="window.print()">
        <i class="bi bi-printer me-1"></i><?= t('inv.print') ?>
      </button>
    </div>
  </div>
</div>

<div class="card ds-card">
  <div class="card-body p-4 p-md-5">

    <?php require APP_PATH . '/Views/partials/invoice-header.php'; ?>

    <div class="row mb-4">
      <div class="col-md-6">
        <div class="text-secondary small text-uppercase mb-1"><?= t('inv.bill_to') ?></div>
        <div class="fw-semibold"><?= e($invoice['customer_name']) ?></div>
        <?php foreach (['customer_taxid', 'customer_address', 'customer_phone', 'customer_email'] as $f): ?>
          <?php if ((string) ($invoice[$f] ?? '') !== ''): ?><div class="text-secondary small"><?= e($invoice[$f]) ?></div><?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <div class="text-secondary small text-uppercase mb-1"><?= t('inv.number') ?></div>
        <div class="fw-semibold fs-5"><?= e($invoiceNumber) ?></div>
        <div class="text-secondary small"><?= e(ds_date($invoice['issue_date'])) ?></div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table mb-4">
        <thead>
          <tr class="text-secondary small text-uppercase border-bottom">
            <th><?= t('inv.product') ?></th>
            <th class="text-end"><?= t('inv.quantity') ?></th>
            <th class="text-end"><?= t('inv.unit_price') ?></th>
            <th class="text-end"><?= t('inv.line_total') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
          <tr>
            <td><?= e($item['product_name']) ?></td>
            <td class="text-end"><?= e($fmtQty((string) $item['quantity'])) ?></td>
            <td class="text-end"><?= number_format((float) $item['unit_price'], 2) ?></td>
            <td class="text-end"><?= number_format((float) $item['line_total'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold border-top">
            <td colspan="3" class="text-end"><?= t('inv.total') ?></td>
            <td class="text-end"><?= number_format((float) $invoice['total'], 2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ($bankIbans !== [] || $org['signature'] !== null): ?>
    <div class="row mt-4 pt-4 border-top">
      <div class="col-md-8">
        <?php if ($bankIbans !== []): ?>
          <div class="text-secondary small text-uppercase mb-1"><?= t('org.bank_details') ?></div>
          <?php foreach ($bankIbans as $iban): ?><div class="small"><?= e($iban) ?></div><?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <?php if ($org['signature'] !== null): ?>
          <img src="<?= e($uploadUrl . $org['signature']) ?>" alt="" style="max-width:160px;max-height:80px;object-fit:contain;">
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
