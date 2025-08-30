<?php
//////////////////////////////////////////////
// Database Configuration
//////////////////////////////////////////////

// edit database settings
$config['database_host'] = '';
$config['database_user'] = '';
$config['database_password'] = '';
$config['database_dbname'] = '';

// Site URL - Update this to your application's full base URL
// e.g., http://localhost/querycloud or https://yourdomain.com/querycloud
$config['site_url'] = 'http://localhost/querycloud';

//////////////////////////////////////////////
// User details who can login
// user_type can be 'admin' or 'viewer'
// 'admin' users can access the full application.
// 'viewer' users can only access shared reports via /share/token URLs.
//////////////////////////////////////////////

$config['users'] = [
    [
        'username' => 'admin',
        'password' => 'adminpass', // Changed default password for clarity
        'user_type' => 'admin'
    ],
    [
        'username' => 'admin2',
        'password' => 'admin2pass', // Changed default password for clarity
        'user_type' => 'admin'
    ],
    [
        'username' => 'viewer1',
        'password' => 'viewerpass',
        'user_type' => 'viewer'
    ],
    [
        'username' => 'viewer2',
        'password' => 'viewerpass2',
        'user_type' => 'viewer'
    ]
];

// table to store saved queries
/*
CREATE TABLE `saved_queries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query_name` varchar(255) NOT NULL,
  `sql_query` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_visual_query` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Flag to indicate if the query was created/saved via the visual builder',
  `visual_params` text DEFAULT NULL COMMENT 'JSON string representing the visual builder parameters',
  `source_connection_id` int(11) DEFAULT NULL,
  `table_formatting` text DEFAULT NULL,
  `etl_config` text DEFAULT NULL,
  `share_token` varchar(64) DEFAULT NULL,
  `share_requires_login` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `etl_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `saved_query_id` INT NOT NULL,
  `execution_time` DATETIME NOT NULL,
  `ended_at` DATETIME,
  `status` VARCHAR(50) NOT NULL,
  `message` TEXT,
  FOREIGN KEY (`saved_query_id`) REFERENCES `saved_queries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE `data_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_name` varchar(255) NOT NULL,
  `db_type` varchar(255) NOT NULL,
  `db_host` varchar(255) NOT NULL,
  `db_port` varchar(255) DEFAULT NULL,
  `db_name` varchar(255) NOT NULL,
  `db_user` varchar(255) NOT NULL,
  `db_password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

*/
