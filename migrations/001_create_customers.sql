CREATE TABLE `customers` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `customer_name`    VARCHAR(255)    NOT NULL,
  `customer_taxid`   VARCHAR(64)     NULL DEFAULT NULL,
  `customer_contact` VARCHAR(255)    NULL DEFAULT NULL,
  `customer_phone`   VARCHAR(255)    NULL DEFAULT NULL,
  `customer_email`   VARCHAR(255)    NULL DEFAULT NULL,
  `customer_address` VARCHAR(255)    NULL DEFAULT NULL,
  `customer_info`    TEXT            NULL DEFAULT NULL,
  `ruler`            INT UNSIGNED    NULL DEFAULT NULL COMMENT 'Ruler ID (FK to ruler.id)',
  `created_at`       TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name`  (`customer_name`),
  KEY `idx_customers_taxid` (`customer_taxid`),
  KEY `idx_ruler`           (`ruler`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
