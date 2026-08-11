UPDATE `users` SET `phone` = CONCAT('+', `phone`) WHERE `phone` IS NOT NULL AND `phone` NOT LIKE '+%';
