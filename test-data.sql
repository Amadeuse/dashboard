-- Test fixture data for dashboard.loc — tenants 31 ("test1") and 32
-- ("test2") only. Contains NO real tenant data (tenant 1, the live
-- account) — every row here is synthetic (example.test emails, made-up
-- tax ids). Run `php migrate.php` on a fresh/empty database FIRST to
-- build the schema, then import this file. IDs are hardcoded (matches
-- the source DB's real auto-increment values) — only safe to import
-- into an empty database, not merge into one that already has rows.

-- units (global, no ruler column)
INSERT INTO `units` (`id`, `name`) VALUES (3, 'გრძივი მეტრი');
INSERT INTO `units` (`id`, `name`) VALUES (5, 'კგ');
INSERT INTO `units` (`id`, `name`) VALUES (4, 'კვ.მეტრი');
INSERT INTO `units` (`id`, `name`) VALUES (6, 'ლიტრი');
INSERT INTO `units` (`id`, `name`) VALUES (2, 'მეტრი');
INSERT INTO `units` (`id`, `name`) VALUES (1, 'ცალი');

-- modules (enables Warehouse etc.)
INSERT INTO `modules` (`code`, `version`, `enabled`, `installed_at`) VALUES ('Warehouse', '1.0.0', 0, '2026-08-07 02:50:25');

-- users (root tenants 31/32 only — neither has sub-users)
INSERT INTO `users` (`id`, `name`, `email`, `google_id`, `phone`, `avatar`, `role`, `created_by`, `password_hash`, `created_at`) VALUES (31, 'test1', 'test1@invoice.net', NULL, NULL, NULL, 'admin', NULL, '$2y$10$mNTzp4YyCEHk/bI1poho5uCw.E/3vOyfsIKRuJO7uOTunazmKFazq', '2026-08-16 11:00:18');
INSERT INTO `users` (`id`, `name`, `email`, `google_id`, `phone`, `avatar`, `role`, `created_by`, `password_hash`, `created_at`) VALUES (32, 'test2', 'test2@invoice.net', NULL, NULL, NULL, 'admin', NULL, '$2y$10$SJEtDdxb5.XaJa8O3lG8dOfpQwPINm7sFLv48/4U6G7BOUiaY52mC', '2026-08-16 11:00:51');

-- organization
INSERT INTO `organization` (`id`, `ruler`, `name`, `tax_id`, `email`, `website`, `phone`, `address`, `invoice_prefix`, `vat_rate`, `currency`, `bank_details`, `logo`, `signature`, `updated_at`) VALUES (2, 31, 'შპს ტესტ ვან', '400111222', 'info@test1.test', NULL, '+995555000031', 'თბილისი, ვაჟა-ფშაველას გამზ. 10', 'TS1', '18.00', 'GEL', 'GE70PC0183600100023047', 'bda9b76d32311bd629a1419d83e0fe9f.jpg', 'c2108feca456e4fd135a61cf79e006c2.jpg', '2026-08-17 00:14:05');
INSERT INTO `organization` (`id`, `ruler`, `name`, `tax_id`, `email`, `website`, `phone`, `address`, `invoice_prefix`, `vat_rate`, `currency`, `bank_details`, `logo`, `signature`, `updated_at`) VALUES (3, 32, 'შპს ტესტ ტუ', '400333444', 'info@test2.test', 'www.test2.test', '+995555000032', 'ბათუმი, რუსთაველის ქ. 25', 'TS2', '18.00', 'GEL', 'GE00TB0000000000032000', NULL, NULL, '2026-08-16 11:53:24');

-- customers
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1553, 'შპს ბეტა ჯგუფი', NULL, 'საკონტაქტო პირი 1', '565359086', 'client31.1@example.test', 'ქალაქი, ქუჩა 1', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1554, 'ააიპ განვითარების ცენტრი', '131117701', 'საკონტაქტო პირი 2', '567627167', 'client31.2@example.test', 'ქალაქი, ქუჩა 2', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1555, 'შპს გამა ლოჯისტიკი', '467549136', 'საკონტაქტო პირი 3', '554957435', 'client31.3@example.test', 'ქალაქი, ქუჩა 3', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1556, 'ი/მ ნინო კვარაცხელია', '364853169', 'საკონტაქტო პირი 4', '597625016', 'client31.4@example.test', 'ქალაქი, ქუჩა 4', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1557, 'სს დელტა ჰოლდინგი', NULL, 'საკონტაქტო პირი 5', '561419305', 'client31.5@example.test', 'ქალაქი, ქუჩა 5', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1558, 'შპს ეპსილონ ტრეიდი', '527071360', 'საკონტაქტო პირი 6', '587748533', 'client31.6@example.test', 'ქალაქი, ქუჩა 6', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1559, 'ინდ. მეწარმე დავით მამულაშვილი', '452502312', 'საკონტაქტო პირი 7', '577781021', 'client31.7@example.test', 'ქალაქი, ქუჩა 7', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1560, 'შპს ზეტა კონსალტინგი', '389617756', 'საკონტაქტო პირი 8', '563807169', 'client31.8@example.test', 'ქალაქი, ქუჩა 8', NULL, 31, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1561, 'შპს ბეტა ჯგუფი', NULL, 'საკონტაქტო პირი 1', '578724191', 'client32.1@example.test', 'ქალაქი, ქუჩა 1', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1562, 'ააიპ განვითარების ცენტრი', '549386780', 'საკონტაქტო პირი 2', '559298044', 'client32.2@example.test', 'ქალაქი, ქუჩა 2', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1563, 'შპს გამა ლოჯისტიკი', '402661701', 'საკონტაქტო პირი 3', '565630763', 'client32.3@example.test', 'ქალაქი, ქუჩა 3', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1564, 'ი/მ ნინო კვარაცხელია', '386978060', 'საკონტაქტო პირი 4', '556119497', 'client32.4@example.test', 'ქალაქი, ქუჩა 4', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1565, 'სს დელტა ჰოლდინგი', NULL, 'საკონტაქტო პირი 5', '589140687', 'client32.5@example.test', 'ქალაქი, ქუჩა 5', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1566, 'შპს ეპსილონ ტრეიდი', '300765407', 'საკონტაქტო პირი 6', '572702489', 'client32.6@example.test', 'ქალაქი, ქუჩა 6', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1567, 'ინდ. მეწარმე დავით მამულაშვილი', '218112782', 'საკონტაქტო პირი 7', '563427354', 'client32.7@example.test', 'ქალაქი, ქუჩა 7', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `customers` (`id`, `customer_name`, `customer_taxid`, `customer_contact`, `customer_phone`, `customer_email`, `customer_address`, `customer_info`, `ruler`, `created_at`, `updated_at`) VALUES (1568, 'შპს ზეტა კონსალტინგი', '406985157', 'საკონტაქტო პირი 8', '584890432', 'client32.8@example.test', 'ქალაქი, ქუჩა 8', NULL, 32, '2026-08-16 11:53:24', '2026-08-16 11:53:24');

-- product_types
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (8, 31, 'სამშენებლო მასალა');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (9, 31, 'ელექტროობა');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (10, 31, 'სანტექნიკა');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (11, 31, 'ხის მასალა');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (12, 32, 'საოფისე ტექნიკა');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (13, 32, 'ავეჯი');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (14, 32, 'საკანცელარიო');
INSERT INTO `product_types` (`id`, `ruler`, `name`) VALUES (15, 32, 'პროგრამული უზრუნველყოფა');

-- products
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (16, 31, 'ცემენტი 50კგ', 1, 11, '44.60', '2026-08-16 11:53:23', '2026-08-16 11:53:23');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (17, 31, 'კაბელი 3x2.5', 2, 10, '132.70', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (18, 31, 'მილი PVC 50მმ', 3, 8, '158.10', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (19, 31, 'ფიცარი 2მ', 4, 11, '55.30', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (20, 31, 'ლურსმანი 5სმ', 5, 10, '165.60', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (21, 32, 'ლეპტოპი 15\"', 1, 13, '159.40', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (22, 32, 'საწერი მაგიდა', 2, 12, '296.40', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (23, 32, 'კალამი (ყუთი)', 3, 13, '192.20', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (24, 32, 'ლიცენზია Office', 4, 15, '226.40', '2026-08-16 11:53:24', '2026-08-16 11:53:24');
INSERT INTO `products` (`id`, `ruler`, `name`, `unit_id`, `product_type_id`, `unit_price`, `created_at`, `updated_at`) VALUES (25, 32, 'სკამი ოფისის', 5, 12, '142.60', '2026-08-16 11:53:24', '2026-08-16 11:53:24');

-- product_warehouse
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (16, 31, 11, '137.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (17, 31, 10, '80.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (18, 31, 8, '318.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (19, 31, 11, '36.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (20, 31, 10, '77.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (21, 32, 13, '81.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (22, 32, 12, '309.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (23, 32, 13, '407.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (24, 32, 15, '266.000', NULL);
INSERT INTO `product_warehouse` (`product_id`, `ruler`, `product_type_id`, `remaining_qty`, `image`) VALUES (25, 32, 12, '178.000', NULL);

-- invoices (created_by = 31 or 32)
INSERT INTO `invoices` (`id`, `customer_id`, `issue_date`, `total`, `status`, `is_zero`, `is_recurring`, `notes`, `created_by`, `view_token`, `created_at`, `updated_at`) VALUES (12, 1560, '2026-08-16', '1656.00', 'draft', 0, 0, '', 31, '61a7c9d9d0ac693d15f979c3ff74f35da3926b51eb2f9f2f25102af16cddaeed', '2026-08-16 20:03:12', '2026-08-16 20:03:12');

-- invoice_items (for the invoices above)
INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `line_total`) VALUES (21, 12, 20, '10.000', '165.60', '1656.00');
