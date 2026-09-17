-- Invoice-level discount (4.135) — one per invoice, applied to the whole
-- amount, not per line. Two columns rather than one resolved figure because
-- the invoice has to say what was agreed ("10%" or "100 ₾"), not a
-- back-computed equivalent.
--
-- `total` stays the final, discounted, VAT-inclusive amount every list and
-- report already reads; the subtotal before discount is Σ items, recomputed
-- where the lines are shown (form, preview, PDF). Prices here are
-- VAT-inclusive (see pdf/invoice.php), so the discount simply reduces the
-- total and the informational VAT line follows from it — there is no
-- "before or after VAT" question to answer.
--
-- Defaults make every existing invoice a zero-discount one, unchanged.
ALTER TABLE `invoices`
  ADD COLUMN `discount_type`  ENUM('percent','amount') NOT NULL DEFAULT 'percent' AFTER `total`,
  ADD COLUMN `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `discount_type`;
