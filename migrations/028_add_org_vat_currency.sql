-- Per-tenant VAT rate (a display-only percentage shown on the invoice form —
-- prices are already entered VAT-inclusive, this never changes any stored
-- total) and currency unit (GEL/USD), shown next to prices wherever an
-- amount is displayed. Different tenants can legally have different VAT
-- rates (e.g. 18 vs 20), hence per-organization, not a global constant.
ALTER TABLE `organization`
  ADD COLUMN `vat_rate` DECIMAL(5,2) NOT NULL DEFAULT 18.00 AFTER `invoice_prefix`,
  ADD COLUMN `currency` ENUM('GEL','USD') NOT NULL DEFAULT 'GEL' AFTER `vat_rate`;
