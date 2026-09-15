-- 4.113: install and enable become two different things.
--
-- Installing puts a module's files on this server and runs its migrations —
-- one server-wide act, done by an admin. Enabling is per tenant: a customer
-- who buys the Warehouse module gets it turned on for their own `ruler`
-- only, and every other tenant on the same installation is unaffected. The
-- old single `enabled` flag couldn't express that — flipping it turned the
-- module on for everyone (see migrations/005, which has no ruler column).
CREATE TABLE `module_tenants` (
  `code`       VARCHAR(64)  NOT NULL,
  `ruler`      INT UNSIGNED NOT NULL COMMENT 'Tenant/owner id (Auth::tenantId())',
  `enabled_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`, `ruler`),
  KEY `idx_module_tenants_ruler` (`ruler`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `enabled` was the server-wide flag this table replaces. Nothing to carry
-- over: 037 removed both modules, so `modules` is empty.
ALTER TABLE `modules` DROP COLUMN `enabled`;
