-- A random per-invoice secret that lets InvoiceController::show() (the print
-- page) grant access without login — the vendor sends the customer a link
-- carrying it (?id=N&token=...), the customer never needs an account here.
-- Set once at creation (Invoice::save()'s INSERT), never regenerated.
ALTER TABLE `invoices`
  ADD COLUMN `view_token` VARCHAR(64) NULL DEFAULT NULL AFTER `created_by`,
  ADD UNIQUE KEY `uq_invoices_view_token` (`view_token`);
