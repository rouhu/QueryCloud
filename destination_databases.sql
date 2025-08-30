CREATE TABLE `destination_databases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `connection_name` varchar(255) NOT NULL,
  `db_type` varchar(255) NOT NULL,
  `db_host` varchar(255) NOT NULL,
  `db_port` varchar(255) DEFAULT NULL,
  `db_name` varchar(255) NOT NULL,
  `db_user` varchar(255) NOT NULL,
  `db_password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
