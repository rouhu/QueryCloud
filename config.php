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
CREATE TABLE saved_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_name VARCHAR(255) NOT NULL,
    sql_query TEXT NOT NULL,
    is_visual_query TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag to indicate if the query was created/saved via the visual builder',
    visual_params TEXT NULL COMMENT 'JSON string representing the visual builder parameters',
    table_formatting TEXT NULL COMMENT 'JSON string representing the table display formatting options',
    share_token VARCHAR(64) NULL DEFAULT NULL UNIQUE COMMENT 'Unique token for sharing query results publicly',
    share_requires_login TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'If true, accessing the share_token link requires user login',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/
