-- Optimistic-locking token for concurrent-edit protection (see handoff.md's
-- multi-user section) — bumped automatically by MySQL on every UPDATE,
-- Invoice::save() compares it against the value the edit form was loaded with.
ALTER TABLE `invoices`
  ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
