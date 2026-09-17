-- is_zero ("ნულოვანი") was stored on every invoice and read by nothing —
-- a flag with a label and no effect (4.136). The user gave it a meaning and
-- a name to match: show_vat. On, the VAT line appears on the PDF, the
-- preview, the share page and the form's own summary; off, it doesn't.
--
-- The sense inverts (a "zero" invoice is one WITHOUT VAT), so the values
-- do too: show_vat = NOT is_zero. Every existing invoice keeps the meaning
-- it had — the ones flagged zero stay VAT-less, the rest show VAT — and a
-- new invoice defaults to showing it, which is what nearly every invoice
-- wants.
ALTER TABLE `invoices`
  CHANGE COLUMN `is_zero` `show_vat` TINYINT(1) NOT NULL DEFAULT 1;

UPDATE `invoices` SET `show_vat` = NOT `show_vat`;
