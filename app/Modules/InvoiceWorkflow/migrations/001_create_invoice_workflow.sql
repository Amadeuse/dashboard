CREATE TABLE `invoice_workflow` (
  `invoice_id`    INT UNSIGNED NOT NULL,
  `payment_state` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `paid_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cancelled_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  CONSTRAINT `fk_invoice_workflow_invoice` FOREIGN KEY (`invoice_id`)
    REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
