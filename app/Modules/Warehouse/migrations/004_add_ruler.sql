-- Same multi-tenant scoping as core products.ruler (migrations/022) —
-- denormalized onto product_warehouse directly (rather than always joining
-- through products) per the explicit ask to give this table its own field
-- too, matching customers.ruler.
ALTER TABLE `product_warehouse`
  ADD COLUMN `ruler` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tenant/owner id (Auth::tenantId())' AFTER `product_id`,
  ADD KEY `idx_product_warehouse_ruler` (`ruler`);
