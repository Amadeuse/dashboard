-- Same multi-tenant scoping product_types got in migrations/023 (4.133).
--
-- units was the last lookup table without a tenant: any signed-in user could
-- POST /units with another row's id and rename "ცალი" for every tenant in the
-- system — on their invoices, PDFs and products.
--
-- NULL ruler = a shared default. The six units 003 seeded stay NULL: every
-- tenant sees them (Unit::all() reads "ruler IS NULL OR ruler = ?"), no
-- tenant can rename them (Unit::update() matches "ruler = ?" only), and the
-- 29 invoice_items and 17 products that already reference them are left
-- exactly as they are — no per-tenant copies, no remapping. A unit a tenant
-- creates from now on carries their ruler and is theirs alone.
ALTER TABLE `units`
  ADD COLUMN `ruler` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tenant/owner id (Auth::tenantId()); NULL = shared default' AFTER `id`,
  ADD KEY `idx_units_ruler` (`ruler`);
