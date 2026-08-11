CREATE TABLE `organization` (
  `id`             INT UNSIGNED NOT NULL,
  `name`           VARCHAR(255) NOT NULL DEFAULT '',
  `tax_id`         VARCHAR(64)  NULL,
  `email`          VARCHAR(255) NULL,
  `website`        VARCHAR(255) NULL,
  `phone`          VARCHAR(32)  NULL,
  `address`        VARCHAR(255) NULL,
  `invoice_prefix` VARCHAR(16)  NULL,
  `bank_details`   TEXT         NULL,
  `logo`           VARCHAR(255) NULL,
  `signature`      VARCHAR(255) NULL,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-org app: one row, always id=1. Organization::get()/save() enforce this.
INSERT INTO `organization` (`id`) VALUES (1);
