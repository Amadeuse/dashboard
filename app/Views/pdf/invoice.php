<?php
/**
 * @var array  $invoice        invoices row (i.*, so notes is already in there) +
 *                              customer_name/customer_taxid/customer_contact/
 *                              customer_phone/customer_email/customer_address/creator_name
 *                              joined in (Invoice::find())
 * @var string $invoiceNumber  already formatted (Invoice::number())
 * @var array  $items          this invoice's line items, product_name and unit_name joined in
 *                              (Invoice::itemsFor()) — unit_name is NULL for a pre-migrations/030
 *                              line item (no unit_id recorded at save time), rendered as blank
 * @var array  $org            the organization row (Organization::get())
 * @var array  $bankIbans      organization's bank accounts (Organization::bankIbans())
 *
 * Single-invoice PDF — the per-row "მოქმედება" export on /orders
 * (InvoiceController::exportInvoicePdf()). Same underlying data as
 * invoice-view.php (the browser print page), but a different header layout
 * (user supplied a design screenshot) and plain mPDF-safe HTML/CSS instead
 * of Bootstrap — images are referenced by filesystem path (mPDF reads local
 * files directly, no HTTP round-trip) instead of a public URL.
 *
 * PDF-only — orders.php's "ნახვა" modal (InvoiceController::preview()) has
 * its own separate view (invoice-preview.php) with its own design, not this
 * template. An earlier version of that preview briefly reused this file
 * (behind an $isPreview flag) but the user asked for a genuinely separate,
 * independently-styled modal instead — that flag and its public-URL/
 * filesystem-path branching are gone again, this file only ever renders for
 * mPDF now.
 *
 * Header is logo + date/invoice-number stat pair, then one shaded box with
 * two columns: our own org details (right down to *which tenant member*
 * issued this one — creator_name) side by side with the customer's. Every
 * field below the bold name line is conditional (empty ones just don't
 * render) — same convention invoice-view.php's own bill-to block and
 * invoices.php's customer-info panel already use, not everything is always
 * filled in.
 */
$uploadDir = ROOT_PATH . '/public/assets/uploads/organization/';
$fmtQty    = static fn(string $q): string => rtrim(rtrim($q, '0'), '.') ?: '0';

// '0' is how legacy/imported rows spell "no tax id" — same treatment orders.php gives it.
$customerTaxId = (string) ($invoice['customer_taxid'] ?? '');
if ($customerTaxId === '0') {
    $customerTaxId = '';
}

// Prices already include VAT (org.vat_rate is informational, not additive —
// see invoices.php's own docblock note) — this extracts how much of the
// stored total is VAT, same formula as invoices.php's create-form JS:
// vat = total * rate / (100 + rate), not total * rate / 100.
$vatRate   = (float) ($org['vat_rate'] ?? 18);
$vatAmount = (float) $invoice['total'] * $vatRate / (100 + $vatRate);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: notosansgeorgian, sans-serif; font-size: 13px; color: #1a1a1a; }
  table { width: 100%; border-collapse: collapse; }
  .muted { color: #666; }
  .right { text-align: right; }
  /* No text-transform:uppercase anywhere here — Georgian has no case in the
     Latin sense; browsers/mPDF "uppercase" it via the Mtavruli Unicode block
     instead, which reads as a different, heavier script next to the rest of
     the (Mkhedruli) text rather than a subtle label style. The web app gets
     a proper small-caps look for this via a dedicated font (bpg-arial-caps)
     — not worth pulling in for one label style here. */

  /* ---- top row: logo + date/invoice-number stat pair ----
     table-layout:fixed + wider stat columns — the bigger .stat-value font
     (12px -> 14px, alongside this round's other size increases) no longer
     fit "TS1 2026-08-16 0012" in the old 20%-wide column without
     table-layout:fixed to enforce it; the number ran straight into the
     date column with no gap between them. */
  .top-row { margin-bottom: 14px; table-layout: fixed; }
  .top-row td { vertical-align: top; padding: 0; border: 0; }
  .org-name-fallback { font-size: 20px; font-weight: bold; }
  .stat-label { font-size: 10px; color: #8a94a6; margin-bottom: 2px; }
  .stat-value { font-size: 14px; font-weight: normal; color: #2563eb; }
  .top-rule { border-bottom: 1px solid #dde3ec; font-size: 1px; line-height: 1px; margin-bottom: 6px; }

  /* ---- shaded issuer/customer box ----
     A <table><td class="info-box"> wrapper, not a padded <div> — mPDF has
     repeatedly proven unreliable at keeping a padded block div the same
     rendered width as a sibling width:100% table (see the total/summary
     tables above), and that's exactly what made this box's right edge
     drift from .items' right edge below it. A table's own td padding has
     been the one thing that's held up correctly everywhere else in this
     file, so the box itself is now a table too. */
  .info-box-outer { margin-bottom: 20px; }
  .info-box-outer td.info-box { background-color: #eef4fc; border-radius: 6px; padding: 14px 16px; }
  .info-box table td { vertical-align: top; padding: 0; border: 0; }
  /* Neither margin-bottom nor padding-bottom on a bare <div> reliably
     reserves space in mPDF. .top-rule below already works around this
     the same way: a spacer element with real (non-breaking-space)
     content and an explicit line-height, not a margin/padding on an
     empty box — so .info-name-gap follows that same proven pattern. */
  .info-name { font-family: bpgarialcaps, notosansgeorgian, sans-serif; font-size: 14px; color: #2563eb; }
  .info-name-gap { font-size: 6px; line-height: 1px; }
  .info-field { font-size: 12px; margin-bottom: 5px; }
  .info-field .muted { font-size: 12px; }

  /* Thin blue underline instead of a solid grey fill, headers not bold —
     matches the reference table style the user attached, not the default
     bold <th> the browser/mPDF user-agent stylesheet applies otherwise. */
  .items th { font-weight: normal; font-size: 11px; color: #64748b; border-bottom: 1.5px solid #2563eb; padding: 6px 8px; text-align: left; }
  .items td { border-bottom: 1px solid #ddd; padding: 6px 8px; text-align: left; font-weight: normal; }
  .items td.amount, .items th.amount { text-align: right; }
  .items td.num, .items th.num { width: 24px; color: #8a94a6; }

  /* Summary block — a plain VAT line, then the grand total in its own
     filled/rounded box, per the user's reference screenshot — below
     .items, not a tfoot row. All three amount columns (.items' own line
     totals included) share the same 8px right padding, so every currency
     figure's right edge — line totals, VAT, grand total — lands on one
     shared vertical line, matching the reference image.

     .summary-wrap-outer: a full-width, single-row, 2-column table (empty
     left td, right td holding both mini-tables) — NOT align="right" on
     .summary-table/.total-table themselves. That was this block's first
     approach and it rendered fine in mPDF, but align="right" on a <table>
     is legacy-HTML float behavior in a real browser — exactly the context
     this same template now also renders in (orders.php's "ნახვა" preview
     modal, 4.43) — so the two floated tables overlapped .items' own
     trailing rows there instead of sitting below them. A table column
     positions its content at the row's right edge without floating
     anything, so it behaves identically in mPDF and in a real browser.

     table-layout: fixed + explicit column widths on both mini-tables —
     without it, mPDF (and a browser, confirmed the same way) auto-sizes
     each table to its own row's content instead of the declared 260px, so
     .summary-table (short "VAT:" text) and .total-table (bold, larger
     font) shrink to two DIFFERENT actual widths despite the same
     width:260px, and their left edges (and the total box's left edge)
     land at different X positions. Fixed layout forces both to the same
     real 260px, with identical label/value column splits, so the two
     rows' left edges (box included) line up too.

     .summary-wrap-outer ALSO needs table-layout:fixed + explicit %
     widths on its own two columns — without it, mPDF auto-sized the
     empty left <td> down to ~0 and rendered the right <td> (and
     everything in it) flush against the LEFT margin instead of shrink-
     wrapping it to the right like a browser does. Same fixed-layout
     fix as the two mini-tables above, one level up. An explicit
     .summary-wrap-spacer class on the left <td>, not a :first-child
     selector — this file has already burned itself once on trusting
     an mPDF pseudo-class (see .total-row's own comment).

     .summary-table/.total-table are width:100% here (of .summary-wrap),
     not a fixed 260px — a % column's real rendered width isn't exactly
     predictable across mPDF vs. a browser iframe, and a narrower-than-
     column fixed-px inner table just sits flush-LEFT inside a wider
     cell, leaving a gap before the true right edge instead of touching
     it. Filling 100% of the column removes the mismatch outright: the
     inner content is always exactly as wide as .summary-wrap, whatever
     that resolves to, so the right edges always meet with no gap. */
  .summary-wrap-outer { width: 100%; margin-top: 12px; table-layout: fixed; }
  .summary-wrap-outer td { padding: 0; border: 0; }
  .summary-wrap-spacer { width: 68%; }
  .summary-wrap { width: 32%; }
  .summary-table, .total-table { width: 100%; table-layout: fixed; }
  .summary-table td { padding: 5px 8px 5px 0; font-size: 13px; border: 0; white-space: nowrap; }
  .summary-label { color: #8a94a6; width: 40%; }
  .summary-value { text-align: right; color: #2563eb; width: 60%; }
  .total-table { margin-top: 6px; }
  .total-table td { background-color: #6366f1; color: #fff; font-weight: bold; font-size: 15px; padding: 12px 8px 12px 14px; border: 0; white-space: nowrap; }
  .total-label { width: 40%; }
  .total-table td.amount { text-align: right; width: 60%; }

  .section-label { font-size: 10px; color: #8a94a6; margin-bottom: 4px; }
  .notes { margin-top: 20px; padding-top: 14px; border-top: 1px solid #ccc; font-size: 12px; }
  .bank { margin-top: 20px; padding-top: 14px; border-top: 1px solid #ccc; font-size: 11px; }
  /* width:100% + text-align:right on the td — not align="right" on an
     auto-width table, which (unlike .summary-table/.total-table, both
     table-layout:fixed with a known width) mPDF couldn't right-align
     reliably here without a declared width to measure from. Same
     text-align:right-on-td approach .top-row already uses for the
     date/invoice-number stat pair. */
  .signature-table { margin-top: 20px; }
  .signature-table td { padding: 0; border: 0; text-align: right; }
</style>
</head>
<body>

  <table class="top-row">
    <tr>
      <td style="width:42%;">
        <?php if ($org['logo'] !== null && is_file($uploadDir . $org['logo'])): ?>
          <img src="<?= e($uploadDir . $org['logo']) ?>" style="max-width:170px;max-height:70px;">
        <?php else: ?>
          <div class="org-name-fallback"><?= e((string) $org['name']) ?></div>
        <?php endif; ?>
      </td>
      <td style="width:23%; padding-right:8px;" class="right">
        <div class="stat-label"><?= t('orders.date') ?></div>
        <div class="stat-value"><?= e($invoice['issue_date']) ?></div>
      </td>
      <td style="width:35%;" class="right">
        <div class="stat-label"><?= t('inv.number') ?></div>
        <div class="stat-value"><?= e($invoiceNumber) ?></div>
      </td>
    </tr>
  </table>
  <div class="top-rule">&nbsp;</div>

  <table class="info-box-outer">
    <tr>
      <td class="info-box">
        <table>
          <tr>
            <td style="width:50%;">
              <div class="info-name"><?= e((string) $org['name']) ?></div>
              <div class="info-name-gap">&nbsp;</div>
              <?php if ($org['tax_id'] !== null): ?><div class="info-field"><span class="muted"><?= t('inv.pdf_taxid') ?>: </span><?= e($org['tax_id']) ?></div><?php endif; ?>
              <?php if ($org['address'] !== null): ?><div class="info-field"><?= e($org['address']) ?></div><?php endif; ?>
              <?php if ($invoice['creator_name'] !== null): ?><div class="info-field"><span class="muted"><?= t('inv.pdf_contact') ?>: </span><?= e($invoice['creator_name']) ?></div><?php endif; ?>
              <?php if ($org['phone'] !== null): ?><div class="info-field"><?= e($org['phone']) ?></div><?php endif; ?>
              <?php if ($org['email'] !== null): ?><div class="info-field"><?= e($org['email']) ?></div><?php endif; ?>
            </td>
            <td style="width:50%; text-align:right;">
              <div class="info-name"><?= e($invoice['customer_name']) ?></div>
              <div class="info-name-gap">&nbsp;</div>
              <?php if ($customerTaxId !== ''): ?><div class="info-field"><span class="muted"><?= t('inv.pdf_taxid') ?>: </span><?= e($customerTaxId) ?></div><?php endif; ?>
              <?php if ((string) ($invoice['customer_address'] ?? '') !== ''): ?><div class="info-field"><?= e($invoice['customer_address']) ?></div><?php endif; ?>
              <?php if ((string) ($invoice['customer_contact'] ?? '') !== ''): ?><div class="info-field"><span class="muted"><?= t('inv.pdf_contact') ?>: </span><?= e($invoice['customer_contact']) ?></div><?php endif; ?>
              <?php if ((string) ($invoice['customer_phone'] ?? '') !== ''): ?><div class="info-field"><?= e($invoice['customer_phone']) ?></div><?php endif; ?>
              <?php if ((string) ($invoice['customer_email'] ?? '') !== ''): ?><div class="info-field"><?= e($invoice['customer_email']) ?></div><?php endif; ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th class="num">#</th>
        <th><?= t('inv.product') ?></th>
        <th class="amount"><?= t('inv.quantity') ?></th>
        <th><?= t('prod.unit') ?></th>
        <th class="amount"><?= t('inv.unit_price') ?></th>
        <th class="amount"><?= t('inv.line_total') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $item): ?>
      <tr>
        <td class="num"><?= $i + 1 ?>.</td>
        <td><?= e($item['product_name']) ?></td>
        <td class="amount"><?= e($fmtQty((string) $item['quantity'])) ?></td>
        <td><?= $item['unit_name'] !== null ? e($item['unit_name']) : '' ?></td>
        <td class="amount"><?= e(money((float) $item['unit_price'], $org['currency'])) ?></td>
        <td class="amount"><?= e(money((float) $item['line_total'], $org['currency'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="summary-wrap-outer">
    <tr>
      <td class="summary-wrap-spacer"></td>
      <td class="summary-wrap">
        <table class="summary-table">
          <tr>
            <td class="summary-label"><?= t('inv.vat') ?>:</td>
            <td class="summary-value"><?= e(money($vatAmount, $org['currency'])) ?></td>
          </tr>
        </table>
        <table class="total-table">
          <tr>
            <td class="total-label"><?= t('inv.grand_total') ?>:</td>
            <td class="amount"><?= e(money((float) $invoice['total'], $org['currency'])) ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <?php if ((string) ($invoice['notes'] ?? '') !== ''): ?>
    <div class="notes">
      <div class="section-label"><?= t('inv.notes') ?></div>
      <div><?= nl2br(e($invoice['notes'])) ?></div>
    </div>
  <?php endif; ?>

  <?php if ($bankIbans !== []): ?>
    <div class="bank">
      <div class="section-label"><?= t('org.bank_details') ?></div>
      <?php foreach ($bankIbans as $iban): ?><div><?= e($iban) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($org['signature'] !== null && is_file($uploadDir . $org['signature'])): ?>
    <table class="signature-table">
      <tr>
        <td><img src="<?= e($uploadDir . $org['signature']) ?>" style="max-width:200px;max-height:200px;"></td>
      </tr>
    </table>
  <?php endif; ?>

</body>
</html>
