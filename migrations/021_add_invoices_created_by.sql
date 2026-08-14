-- "შეკვეთის მიმღები" (orders.php) — whoever was logged in when the invoice
-- was created. NULL for invoices created before this column existed, and
-- for the (rare, ungated) case of creating one without a session — invoices
-- has no auth gate, see handoff.md's multi-user section.
ALTER TABLE `invoices`
  ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `notes`,
  ADD CONSTRAINT `fk_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
