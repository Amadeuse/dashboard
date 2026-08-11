ALTER TABLE `users`
  MODIFY COLUMN `password_hash` VARCHAR(255) NULL,
  ADD COLUMN `google_id` VARCHAR(255) NULL AFTER `email`,
  ADD COLUMN `phone` VARCHAR(32) NULL AFTER `google_id`,
  ADD UNIQUE KEY `uq_users_google_id` (`google_id`),
  ADD UNIQUE KEY `uq_users_phone` (`phone`);
