<?php
/**
 * @var array  $invoice        invoices row + customer_name/customer_taxid/customer_contact/
 *                              customer_phone/customer_email/customer_address (Invoice::find())
 * @var array  $items          this invoice's line items, product_name/unit_name joined in
 *                              (Invoice::itemsFor()) — unit_name is NULL for a pre-migrations/030
 *                              line item, rendered as a blank cell
 * @var array  $org            the organization row (Organization::get())
 * @var array  $bankIbans      organization's bank accounts (Organization::bankIbans())
 *
 * The "ნახვა" modal's body (orders.php, InvoiceController::preview(), fetched
 * and injected via innerHTML — see orders.php's own JS). Deliberately its own
 * template, not a reuse of pdf/invoice.php — the user supplied a reference
 * screenshot with a different visual (plain Bootstrap card/table look, no
 * shaded info box, no logo-as-hero-image treatment) and asked for this to be
 * built as separate code that never touches the PDF template. Plain Bootstrap
 * classes throughout (not pdf/invoice.php's own generic-named custom CSS) —
 * this fragment lands inside orders.php's existing page, so it shares that
 * page's stylesheet rather than carrying its own.
 */
$fmtQty = static fn(string $q): string => rtrim(rtrim($q, '0'), '.') ?: '0';

// '0' is how legacy/imported rows spell "no tax id" — same treatment orders.php gives it.
$customerTaxId = (string) ($invoice['customer_taxid'] ?? '');
if ($customerTaxId === '0') {
    $customerTaxId = '';
}

// Prices already include VAT (org.vat_rate is informational, not additive —
// see invoices.php's own docblock note) — this extracts how much of the
// stored total is VAT, same formula invoices.php's create-form JS and
// pdf/invoice.php both already use: vat = total * rate / (100 + rate).
$vatRate        = (float) ($org['vat_rate'] ?? 18);
$vatAmount      = (float) $invoice['total'] * $vatRate / (100 + $vatRate);
$vatRateDisplay = rtrim(rtrim(number_format($vatRate, 2), '0'), '.') ?: '0';
$uploadUrl      = '/assets/uploads/organization/';
?>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <?php if ($org['logo'] !== null): ?>
      <img src="<?= e($uploadUrl . $org['logo']) ?>" alt="" style="max-height:56px;max-width:220px;object-fit:contain;" class="mb-2 d-block">
    <?php endif; ?>
    <div class="fw-bold"><?= e((string) $org['name']) ?></div>
    <?php if ($org['tax_id'] !== null): ?><div class="small text-secondary"><?= t('inv.pdf_taxid') ?>: <?= e($org['tax_id']) ?></div><?php endif; ?>
    <?php if ($org['address'] !== null): ?><div class="small text-secondary"><?= e($org['address']) ?></div><?php endif; ?>
    <?php if ($org['phone'] !== null): ?><div class="small text-secondary"><i class="bi bi-telephone me-1"></i><?= e($org['phone']) ?></div><?php endif; ?>
    <?php if ($org['email'] !== null): ?><div class="small text-secondary"><i class="bi bi-envelope me-1"></i><?= e($org['email']) ?></div><?php endif; ?>
  </div>
  <div class="col-md-6 text-md-end">
    <div class="fw-bold"><?= e($invoice['customer_name']) ?></div>
    <?php if ($customerTaxId !== ''): ?><div class="small text-secondary"><?= t('inv.pdf_taxid') ?>: <?= e($customerTaxId) ?></div><?php endif; ?>
    <?php if ((string) ($invoice['customer_address'] ?? '') !== ''): ?><div class="small text-secondary"><?= e($invoice['customer_address']) ?></div><?php endif; ?>
    <?php if ((string) ($invoice['customer_phone'] ?? '') !== ''): ?><div class="small text-secondary"><i class="bi bi-telephone me-1"></i><?= e($invoice['customer_phone']) ?></div><?php endif; ?>
    <?php if ((string) ($invoice['customer_email'] ?? '') !== ''): ?><div class="small text-secondary"><i class="bi bi-envelope me-1"></i><?= e($invoice['customer_email']) ?></div><?php endif; ?>
  </div>
</div>

<div class="table-responsive mb-3">
  <table class="table table-sm align-middle mb-0">
    <thead class="table-light">
      <tr class="text-secondary small">
        <th>#</th>
        <th><?= t('inv.product') ?></th>
        <th class="text-end"><?= t('inv.quantity') ?></th>
        <th><?= t('prod.unit') ?></th>
        <th class="text-end"><?= t('inv.unit_price') ?></th>
        <th class="text-end"><?= t('inv.line_total') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $item): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= e($item['product_name']) ?></td>
        <td class="text-end"><?= e($fmtQty((string) $item['quantity'])) ?></td>
        <td><?= $item['unit_name'] !== null ? e($item['unit_name']) : '' ?></td>
        <td class="text-end"><?= e(money((float) $item['unit_price'], $org['currency'])) ?></td>
        <td class="text-end"><?= e(money((float) $item['line_total'], $org['currency'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="row g-3">
  <div class="col-md-7">
    <div class="border rounded-3 p-3 h-100">
      <div class="text-secondary small mb-2"><i class="bi bi-info-circle me-1"></i><?= t('inv.notes') ?></div>
      <div class="small">
        <?php if ((string) ($invoice['notes'] ?? '') !== ''): ?>
          <?= nl2br(e($invoice['notes'])) ?>
        <?php else: ?>
          <span class="text-secondary"><?= t('inv.notes_empty') ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="border rounded-3 p-3">
      <div class="d-flex justify-content-between small text-secondary mb-2">
        <span><?= t('inv.vat') ?> (<?= e($vatRateDisplay) ?>%):</span>
        <span><?= e(money($vatAmount, $org['currency'])) ?></span>
      </div>
      <div class="d-flex justify-content-between fw-bold text-primary fs-5">
        <span><?= t('inv.grand_total') ?>:</span>
        <span><?= e(money((float) $invoice['total'], $org['currency'])) ?></span>
      </div>
    </div>
  </div>
</div>

<?php if ($bankIbans !== []): ?>
  <div class="mt-3 pt-3 border-top">
    <div class="text-secondary small mb-1"><?= t('org.bank_details') ?></div>
    <?php foreach ($bankIbans as $iban): ?><div class="small"><?= e($iban) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>
