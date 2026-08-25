ALTER TABLE `invoices`
  ADD COLUMN `document_state` ENUM('draft','final') NOT NULL DEFAULT 'draft' AFTER `status`,
  ADD COLUMN `payment_state`  ENUM('due','paid') NOT NULL DEFAULT 'due' AFTER `document_state`;

UPDATE `invoices` SET
  document_state = IF(status IN ('final','due','paid'), 'final', 'draft'),
  payment_state  = IF(status = 'paid', 'paid', 'due');

ALTER TABLE `invoices` DROP COLUMN `status`;
