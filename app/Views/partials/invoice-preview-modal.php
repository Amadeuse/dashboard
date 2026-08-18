<?php
/**
 * The "ნახვა" invoice-preview modal — shared markup, `require`d by any page
 * with a button that sets data-invoice-id/data-invoice-number/data-invoice-status
 * and opens #invoicePreviewModal (orders.php's row action, invoices.php's
 * "გადახედვა" sidebar button). Pair with ds_invoice_preview_script()
 * (app/Core/helpers.php) for the JS that actually drives it — call that in
 * the including page's own $scripts. Own header/footer chrome (not
 * Bootstrap's default modal-title), matching the user's reference
 * screenshot — number/status set from the clicked button's data-*
 * attributes, body fetched fresh from /invoices/preview and injected via
 * innerHTML each time (invoice-preview.php — plain Bootstrap markup, safe
 * to inject directly, unlike pdf/invoice.php's own generic-named custom CSS).
 */
?>
<div class="modal fade" id="invoicePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-receipt text-primary"></i>
          <span class="fw-bold text-primary small text-uppercase">Invoice</span>
          <span class="text-secondary">|</span>
          <span class="fw-semibold" id="ipModalNumber"></span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
          <span class="badge rounded-pill" id="ipModalStatus"></span>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('inv.close') ?>"></button>
        </div>
      </div>
      <div class="modal-body">
        <div id="invoicePreviewBody">
          <div class="text-center text-secondary py-5"><span class="spinner-border spinner-border-sm"></span></div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">
          <i class="bi bi-x-lg me-1"></i><?= t('inv.close') ?>
        </button>
        <a href="#" id="ipModalPrintLink" target="_blank" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-printer me-1"></i><?= t('inv.print') ?>
        </a>
        <a href="#" id="ipModalPdfLink" class="btn btn-success btn-sm">
          <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
      </div>
    </div>
  </div>
</div>
