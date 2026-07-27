CREATE TABLE `units` (
  `id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_units_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `units` (`name`) VALUES
  ('ცალი'), ('მეტრი'), ('გრძივი მეტრი'), ('კვ.მეტრი'), ('კგ'), ('ლიტრი');
