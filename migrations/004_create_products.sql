CREATE TABLE `products` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(255)    NOT NULL,
  `product_type_id`  INT UNSIGNED    NOT NULL,
  `unit_id`          INT UNSIGNED    NOT NULL,
  `unit_price`       DECIMAL(12,2)   NOT NULL DEFAULT 0,
  `remaining_qty`    DECIMAL(12,3)   NOT NULL DEFAULT 0,
  `image`            VARCHAR(255)    NULL DEFAULT NULL,
  `created_at`       TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_type` (`product_type_id`),
  KEY `idx_products_unit` (`unit_id`),
  CONSTRAINT `fk_products_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`),
  CONSTRAINT `fk_products_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
