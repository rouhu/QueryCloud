CREATE TABLE `etl_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `saved_query_id` INT NOT NULL,
  `execution_time` DATETIME NOT NULL,
  `ended_at` DATETIME,
  `status` VARCHAR(50) NOT NULL,
  `message` TEXT,
  FOREIGN KEY (`saved_query_id`) REFERENCES `saved_queries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
