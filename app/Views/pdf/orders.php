<?php
/**
 * @var array<int,array<string,mixed>> $rows same shape as orders.php's rows
 *   (Invoice::all()'s output — customer_name/customer_taxid/creator_name joined in)
 * @var string $invoicePrefix organization.invoice_prefix, or "INV"
 * @var array  $org           Organization::get() — name for the header, currency for amounts
 * @var string $generatedAt   already-formatted "generated at" timestamp
 * @var bool   $signed        whether to show the org's signature image below the table (4.63)
 *
 * Plain, mPDF-safe HTML — no Bootstrap grid/flex/utility classes, mPDF's CSS
 * support doesn't cover those. Rendered via Controller::renderToString() (no
 * layout at all) and fed into App\Core\Pdf::download() by
 * InvoiceController::exportOrdersPdf().
 */
$invoiceNumber = static fn(array $row): string => \App\Models\Invoice::number($row, $invoicePrefix);
$uploadDir     = ROOT_PATH . '/public/assets/uploads/organization/';

// '0' is how legacy/imported rows spell "no tax id" — same treatment orders.php gives it.
$taxId = static function (array $row): string {
    $value = (string) ($row['customer_taxid'] ?? '');
    return $value !== '' && $value !== '0' ? e($value) : '—';
};

$statusLabels = [
    'draft' => t('inv.status_draft'),
    'final' => t('inv.status_final'),
];

$grandTotal = array_sum(array_map(static fn(array $r): float => (float) $r['total'], $rows));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: notosansgeorgian, sans-serif; font-size: 11px; color: #1a1a1a; }
  h1 { font-size: 16px; margin: 0 0 2px; }
  .meta { color: #666; font-size: 10px; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border-bottom: 1px solid #ddd; padding: 6px 8px; text-align: left; }
  th { background: #f3f4f6; font-size: 10px; color: #555; }
  td.amount, th.amount { text-align: right; }
  tfoot td { font-weight: bold; border-top: 2px solid #333; border-bottom: none; }
</style>
</head>
<body>
  <h1><?= e((string) ($org['name'] ?? '')) ?></h1>
  <div class="meta"><?= t('nav.orders_all') ?> &mdash; <?= e($generatedAt) ?></div>
  <table>
    <thead>
      <tr>
        <th><?= t('inv.number') ?></th>
        <th><?= t('inv.customer') ?></th>
        <th><?= t('cust.taxid') ?></th>
        <th><?= t('inv.creator') ?></th>
        <th class="amount"><?= t('inv.total') ?></th>
        <th><?= t('orders.status') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $inv): ?>
      <tr>
        <td><?= e($invoiceNumber($inv)) ?></td>
        <td><?= e($inv['customer_name']) ?></td>
        <td><?= $taxId($inv) ?></td>
        <td><?= e($inv['creator_name'] ?? '—') ?></td>
        <td class="amount"><?= e(money((float) $inv['total'], $org['currency'])) ?></td>
        <td><?= e($statusLabels[$inv['document_state']] ?? $inv['document_state']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="4"><?= t('inv.total') ?></td>
        <td class="amount"><?= e(money($grandTotal, $org['currency'])) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <?php if ($signed && ($org['signature'] ?? null) !== null && is_file($uploadDir . $org['signature'])): ?>
    <table style="margin-top:20px;">
      <tr>
        <td style="border-bottom:0; text-align:right; padding:0;">
          <img src="<?= e($uploadDir . $org['signature']) ?>" style="max-width:200px;max-height:200px;">
        </td>
      </tr>
    </table>
  <?php endif; ?>
</body>
</html>
