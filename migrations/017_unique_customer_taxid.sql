-- customer_taxid uniqueness was app-level only (Customer::taxIdTaken(), a
-- check-then-insert with a real race window under concurrent requests — see
-- handoff.md's multi-user section). MySQL UNIQUE indexes allow any number of
-- NULLs, so this doesn't affect walk-in customers with no tax id (NULL after
-- migrations/016's backfill).
ALTER TABLE `customers` ADD UNIQUE INDEX `uq_customers_taxid` (`customer_taxid`);
