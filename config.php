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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/
