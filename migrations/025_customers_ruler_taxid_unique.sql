-- customer_taxid uniqueness (migrations/017) was global — wrong once
-- multi-tenant, since two different tenants may each legitimately have
-- their own customer record for the same external tax id. Replace the
-- single-column UNIQUE with a composite one scoped per tenant.
ALTER TABLE `customers`
  DROP INDEX `uq_customers_taxid`,
  ADD UNIQUE INDEX `uq_customers_ruler_taxid` (`ruler`, `customer_taxid`);
