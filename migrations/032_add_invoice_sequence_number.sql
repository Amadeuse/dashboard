ALTER TABLE `organization`
  ADD COLUMN `invoice_start_number` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `invoice_prefix`;

ALTER TABLE `invoices`
  ADD COLUMN `sequence_number` INT UNSIGNED NULL AFTER `id`;

UPDATE `invoices` i
JOIN (
  SELECT inv.id,
         ROW_NUMBER() OVER (PARTITION BY COALESCE(u.created_by, u.id) ORDER BY inv.id) AS rn
    FROM invoices inv
    JOIN users u ON u.id = inv.created_by
) ranked ON ranked.id = i.id
SET i.sequence_number = ranked.rn;
