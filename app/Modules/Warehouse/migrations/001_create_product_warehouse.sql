CREATE TABLE `product_warehouse` (
  `product_id`      INT UNSIGNED  NOT NULL,
  `product_type_id` INT UNSIGNED  NOT NULL,
  `remaining_qty`   DECIMAL(12,3) NOT NULL DEFAULT 0,
  `image`           VARCHAR(255)  NULL DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  KEY `idx_product_warehouse_type` (`product_type_id`),
  CONSTRAINT `fk_product_warehouse_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_warehouse_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
