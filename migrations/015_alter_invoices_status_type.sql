ALTER TABLE `invoices`
  ADD COLUMN `status` ENUM('draft','final','due','paid') NOT NULL DEFAULT 'draft' AFTER `total`,
  ADD COLUMN `is_zero` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `is_recurring` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_zero`;
