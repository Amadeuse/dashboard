-- Same multi-tenant scoping as products.ruler (migrations/022) — each
-- tenant manages their own product-type list, not a shared global one.
ALTER TABLE `product_types`
  ADD COLUMN `ruler` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tenant/owner id (Auth::tenantId())' AFTER `id`,
  ADD KEY `idx_product_types_ruler` (`ruler`);
