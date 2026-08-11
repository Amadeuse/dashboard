-- One INSERT...SELECT is atomic per-statement in InnoDB — no explicit
-- transaction needed. If this fails (e.g. a product was created with a NULL
-- type in the window between core migration 006 and this module's install —
-- see handoff.md), migrate.php never records it as applied and it is safe
-- to fix the offending row and simply re-run.
INSERT INTO `product_warehouse` (`product_id`, `product_type_id`, `remaining_qty`, `image`)
SELECT `id`, `product_type_id`, `remaining_qty`, `image` FROM `products`
WHERE `product_type_id` IS NOT NULL;
