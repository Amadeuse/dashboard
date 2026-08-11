-- idx_products_type auto-drops with product_type_id (single-column index).
ALTER TABLE `products`
  DROP FOREIGN KEY `fk_products_type`,
  DROP COLUMN `product_type_id`,
  DROP COLUMN `remaining_qty`,
  DROP COLUMN `image`;
