-- Line items now record which unit they were sold in (invoices.php's new
-- "ერთეული" select, between quantity and price) — a snapshot at save time,
-- like unit_price/line_total already are, not a live join against
-- products.unit_id (a product's own default unit could change later
-- without altering an already-issued invoice's line). NULL on pre-existing
-- rows — this column didn't exist when they were created.
ALTER TABLE `invoice_items`
  ADD COLUMN `unit_id` INT UNSIGNED NULL DEFAULT NULL AFTER `product_id`,
  ADD KEY `idx_invoice_items_unit` (`unit_id`),
  ADD CONSTRAINT `fk_invoice_items_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);
