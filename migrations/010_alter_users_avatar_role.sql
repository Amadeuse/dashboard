ALTER TABLE `users`
  ADD COLUMN `avatar`     VARCHAR(255) NULL AFTER `phone`,
  ADD COLUMN `role`       ENUM('admin', 'manager', 'viewer') NOT NULL DEFAULT 'admin' AFTER `avatar`,
  ADD COLUMN `created_by` INT UNSIGNED NULL AFTER `role`,
  ADD CONSTRAINT `fk_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
