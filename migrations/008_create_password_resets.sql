CREATE TABLE `password_resets` (
  `email`      VARCHAR(255) NOT NULL,
  `token_hash` CHAR(64)     NOT NULL,
  `expires_at` TIMESTAMP    NOT NULL,
  PRIMARY KEY (`email`),
  CONSTRAINT `fk_password_resets_email` FOREIGN KEY (`email`) REFERENCES `users` (`email`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
