<?php
/**
 * @var array  $invoice        invoices row + customer_name/customer_taxid/customer_contact/customer_phone/customer_email/customer_address joined in (Invoice::find())
 * @var string $invoiceNumber  "{prefix}-0007", already formatted
 * @var array  $items          this invoice's line items, product_name joined in (Invoice::itemsFor())
 * @var array  $org            the organization row (Organization::get())
 * @var array  $bankIbans      organization's bank accounts (Organization::bankIbans())
 *
 * A printable document, not a form — and, since 4.77, not an app screen
 * either: rendered via Controller::document() (no sidebar/topbar, no
 * buttons anywhere, not even a print/save-PDF toolbar) inside
 * document/_layout.php's own A4-simulated page (.ds-document-page,
 * design-system.css). Ctrl+P still works — that's the browser's own
 * feature, nothing here needs to offer it. Reached two ways, both handled
 * by InvoiceController::show(): a share-link's own view_token (no login),
 * or a logged-in tenant viewer's own "ბეჭდვა" navigation.
 */
$uploadUrl = '/assets/uploads/organization/';
$fmtQty    = static fn(string $q): string => rtrim(rtrim($q, '0'), '.') ?: '0';
?>

<div class="p-4 p-md-5">

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
      <table class="table table-striped mb-4">
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
            <td class="text-end"><?= e(money((float) $item['unit_price'], $org['currency'])) ?></td>
            <td class="text-end"><?= e(money((float) $item['line_total'], $org['currency'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold border-top">
            <td colspan="3" class="text-end"><?= t('inv.total') ?></td>
            <td class="text-end"><?= e(money((float) $invoice['total'], $org['currency'])) ?></td>
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
          <img src="<?= e($uploadUrl . $org['signature']) ?>" alt="" style="max-width:220px;max-height:220px;object-fit:contain;">
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

</div>
