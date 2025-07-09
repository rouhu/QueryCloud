<?php
//////////////////////////////////////////////
// Database Configuration
//////////////////////////////////////////////

// edit database settings
$config['database_host'] = '';
$config['database_user'] = '';
$config['database_password'] = '';
$config['database_dbname'] = '';

//////////////////////////////////////////////
// user details who can login - You can also specify more than one user
//////////////////////////////////////////////

// user 1
$config['username'][] = 'admin';
$config['password'][] = 'admin';

// user 2
$config['username'][] = 'admin2';
$config['password'][] = 'admin2';

// table to store saved queries
/*
CREATE TABLE saved_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_name VARCHAR(255) NOT NULL,
    sql_query TEXT NOT NULL,
    is_visual_query TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag to indicate if the query was created/saved via the visual builder',
    visual_params TEXT NULL COMMENT 'JSON string representing the visual builder parameters',
    table_formatting TEXT NULL COMMENT 'JSON string representing the table display formatting options',
    share_token VARCHAR(64) NULL DEFAULT NULL UNIQUE COMMENT 'Unique token for sharing query results publicly',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/
