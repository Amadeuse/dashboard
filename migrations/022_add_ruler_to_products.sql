-- Multi-tenant scoping, same pattern as customers.ruler (migrations/001) —
-- each product belongs to one tenant (App\Core\Auth::tenantId()). No real FK
-- (there is no "ruler"/tenant table, same as customers.ruler), index only.
ALTER TABLE `products`
  ADD COLUMN `ruler` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tenant/owner id (Auth::tenantId())' AFTER `id`,
  ADD KEY `idx_products_ruler` (`ruler`);
