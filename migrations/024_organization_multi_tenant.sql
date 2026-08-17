-- organization was a single hardcoded row (id=1, see migrations/011's own
-- comment) for the whole app. Going multi-tenant: `id` becomes a normal
-- AUTO_INCREMENT (one row per tenant now, not always 1), and `ruler` is
-- UNIQUE — a tenant has exactly one organization profile, never several.
ALTER TABLE `organization`
  MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ADD COLUMN `ruler` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Tenant/owner id (Auth::tenantId())' AFTER `id`,
  ADD UNIQUE KEY `uq_organization_ruler` (`ruler`);
