QueryCloud - SQL Query Builder and ETL Tool

Installation:
- Save the files to your webroot
- Modify the config.php file with your repository database credentials and administrator logins
- Run "Create Table" commands in config.php file for your repository database
- Schedule /cron.php with crontab to run ETLs with a schedule

Requirements:
- PHP 8.x
- MySQL/MariaDB Database
- If needed install PSQL support extension for PHP
- Allocate enough memory for PHP for large data transfers

Made using:
 - flightPHP framework - a light-weight php framework (http://flightphp.com)
 - idiorm - ORM and query builder (https://github.com/j4mie/idiorm
 - Twitter Bootstrap - UI CSS Framework (http://getbootstrap.com)
 - Credits: Clone of old VisualQuery repository and updated to work with PHP 8.x
