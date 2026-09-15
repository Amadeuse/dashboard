-- 4.112: the first module system is removed whole — both modules and every
-- trace of them. They were written before the module contract existed, so
-- they reached straight into core (lang keys in app/lang/*.php, markup inline
-- in orders.php/invoices.php, guarded branches in InvoiceController) — the
-- opposite of what a third-party module has to be. The replacement system is
-- built against a written contract instead.
--
-- product_warehouse held 15 real rows; they are exported, schema included, to
-- storage/backups/product_warehouse_2026-09-09.sql before this runs, so a
-- rebuilt Warehouse module can restore them. invoice_workflow was empty.
--
-- `products` is unaffected: Warehouse's own migration 003 dropped
-- product_type_id/remaining_qty/image from it, but core migration 019 added
-- product_type_id back as a plain core field. remaining_qty/image lived only
-- in product_warehouse — that is what the export above is for.
DROP TABLE IF EXISTS `product_warehouse`;
DROP TABLE IF EXISTS `invoice_workflow`;

-- Registry rows and the migration ledger entries their own SQL wrote, so a
-- future module named Warehouse/InvoiceWorkflow starts from a clean slate
-- instead of silently skipping its own 001 because the old name is recorded.
DELETE FROM `modules` WHERE `code` IN ('Warehouse', 'InvoiceWorkflow');
DELETE FROM `migrations` WHERE `name` LIKE 'Warehouse/%' OR `name` LIKE 'InvoiceWorkflow/%';
