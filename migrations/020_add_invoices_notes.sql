-- Free-text "additional information" field on the invoice form (invoices.php) —
-- was visual-only until now, this makes it actually persist.
ALTER TABLE `invoices`
  ADD COLUMN `notes` TEXT NULL AFTER `is_recurring`;
