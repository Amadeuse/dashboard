-- One table, owned entirely by this module. Two columns earn their place here
-- and are worth copying into your own migration:
--
--   ruler  — the tenant that owns the row. Every query this module runs must
--            filter on it, or one customer sees another's data.
--   FK     — if a row belongs to a core row (an invoice, a product), declare
--            the foreign key with ON DELETE CASCADE so deleting the core row
--            cleans up after this module automatically.
CREATE TABLE `template_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruler`      INT UNSIGNED NOT NULL COMMENT 'Tenant/owner id (Auth::tenantId())',
  `label`      VARCHAR(255) NOT NULL,
  `note`       TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_template_items_ruler` (`ruler`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
