CREATE TABLE `modules` (
  `code`         VARCHAR(64)  NOT NULL,
  `version`      VARCHAR(32)  NOT NULL,
  `enabled`      TINYINT(1)   NOT NULL DEFAULT 1,
  `installed_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
