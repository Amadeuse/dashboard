-- Lets core stop writing product_type_id/remaining_qty independently of
-- whether/when the Warehouse module gets installed — see handoff.md.
ALTER TABLE `products`
  MODIFY COLUMN `product_type_id` INT UNSIGNED NULL DEFAULT NULL,
  MODIFY COLUMN `remaining_qty`   DECIMAL(12,3) NULL DEFAULT NULL;
