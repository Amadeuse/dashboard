-- Brings `products` to its intended shape: a nullable product_type_id with a
-- foreign key, and none of the stock/image columns that used to live here.
--
-- Why this is written defensively instead of as a plain ALTER (4.115):
--
-- product_type_id, remaining_qty and image were all created by
-- 004_create_products.sql, relaxed by 006, and then dropped by the Warehouse
-- module's own 003_drop_products_extension_columns.sql once that module owned
-- them. This file then re-added product_type_id as a plain core field. That
-- chain worked exactly once — on installations that actually ran Warehouse's
-- migration. The Warehouse module was removed whole in 4.112, so on a fresh
-- database 004 creates the column and this file used to fail on it with
-- "Duplicate column name 'product_type_id'", leaving every new install of the
-- app broken at this migration.
--
-- Editing an already-applied migration is normally wrong, but it is the safe
-- option here precisely because it is already applied: every existing
-- installation has this filename in its ledger and will never execute it
-- again, so this can only ever run on a fresh database — the one case that
-- was broken. The guards make it reach the same end state from either
-- history, so a fresh install and an upgraded one end up with identical
-- schemas.
--
-- MySQL has no ADD/DROP COLUMN IF EXISTS (that is MariaDB), hence the
-- information_schema lookup and PREPARE around each change. `DO 0` is the
-- no-op branch.

-- ---------------------------------------------------------------------------
-- product_type_id — add it only if 004's copy is not already there
-- ---------------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'product_type_id') = 0,
  'ALTER TABLE `products` ADD COLUMN `product_type_id` INT UNSIGNED NULL AFTER `unit_id`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Nullable either way: 004 created it NOT NULL and 006 relaxed it, but a
-- database that never saw 006 in that shape still needs it relaxed here.
ALTER TABLE `products` MODIFY COLUMN `product_type_id` INT UNSIGNED NULL DEFAULT NULL;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
       AND CONSTRAINT_NAME = 'fk_products_type') = 0,
  'ALTER TABLE `products` ADD CONSTRAINT `fk_products_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- remaining_qty / image — stock and picture belong to whatever module owns
-- them, never to core products. Dropped here on any database where Warehouse's
-- own migration never ran, so fresh installs match existing ones.
-- ---------------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'remaining_qty') > 0,
  'ALTER TABLE `products` DROP COLUMN `remaining_qty`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'image') > 0,
  'ALTER TABLE `products` DROP COLUMN `image`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
