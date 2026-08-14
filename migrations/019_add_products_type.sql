-- product_type_id lived here before the Warehouse module split (migrations/006
-- relaxed it to nullable, Warehouse's own 003_drop_products_extension_columns.sql
-- later dropped it once Warehouse owned type/quantity/image). Re-added here as a
-- plain core field, independent of Warehouse (which may or may not be enabled) —
-- NULL for existing rows (no type assigned yet), required going forward via
-- Product::validate().
ALTER TABLE `products`
  ADD COLUMN `product_type_id` INT UNSIGNED NULL AFTER `unit_id`,
  ADD CONSTRAINT `fk_products_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`);
