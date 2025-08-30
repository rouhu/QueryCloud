<?php

error_reporting(E_ERROR);
//ini_set('display_errors', 1);

// Start session with check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();
try {
// Add debug header
header('X-Debug: Enabled');

// autoload dependencies automatically via magical composer autoload
require_once 'vendor/autoload.php';

// website configuration file
require_once 'boot.php';

// db configuration file
require_once 'config.php';

// flight framework
require 'core/flight/Flight.php';

// custom functions
require_once 'func.php';

// autoload classes
require_once('autoload.php');


    // Debug: Check if Dashboard class exists
    if (!class_exists('Dashboard')) {
        throw new Exception('Dashboard controller class not found');
    }

    // Debug: Verify routes
    Flight::route('GET /debug', function(){
        echo '<pre>';
        print_r(Flight::router()->getRoutes());
        echo 'Current URL: '.Flight::request()->url;
        exit;
    });


// error logging
if ($config['log_errors']) {
    Flight::set('flight.log_errors', true);
    $logFile = fopen($config['log_path'] . 'applog.log', 'a+');

    Flight::map(
       'error',
       function (Exception $ex) use ($logFile) {
           $message = date('d-m-Y h:i:s') . PHP_EOL . $ex->getTraceAsString() . PHP_EOL . str_repeat(
                 '-',
                 80
              ) . PHP_EOL . PHP_EOL;

           fwrite($logFile, $message);
           fclose($logFile);
       }
    );
}

// set config
Flight::set('config', $config);

// view path
Flight::set('flight.views.path', 'app/views/');

// set base path variable to be used in setting css js files in views
$request = (array) Flight::request();
$base = rtrim($request['base'], '/');
Flight::set('base', $base === '/' ? '' : $base);
Flight::set('controller', $request['url']);
//Flight::set('lastSegment', end(explode('/', $request['url'])));

$urlParts = explode('/', $request['url']);
Flight::set('lastSegment', end($urlParts));

// connect configuration
$database = $config['database_dbname'];
ORM::configure('mysql:host=' . $config['database_host'] . ';dbname=' . $database);
ORM::configure('username', $config['database_user']);
ORM::configure('password', $config['database_password']);
$db = ORM::get_db();

// enable query logging
ORM::configure('logging', true);

Flight::set('db', $db);
Flight::set('dbname', $database);

// Initialize table data as empty
$data = [];

// Check if a data source is selected in the session
if (isset($_SESSION['selected_data_source']) && $_SESSION['selected_data_source']) {
    $data_source_id = $_SESSION['selected_data_source'];
    $source = ORM::for_table('data_sources')->find_one($data_source_id);

    if ($source) {
        $connection_name = 'data_source_index_' . $source->id;
        try {
            $password = toggleEncryption($source->db_password);

            // Configure a temporary, distinct connection for fetching tables
            ORM::configure(get_dsn($source), null, $connection_name);
            ORM::configure('username', $source->db_user, $connection_name);
            ORM::configure('password', $password, $connection_name);
            ORM::configure('logging', true, $connection_name);

            $source_db = ORM::get_db($connection_name);

            // Fetch tables from the selected data source
            $data = get_tables($source_db, $source->db_type);
            $data = arrayFlatten($data);

        } catch (PDOException $e) {
            // Log error, and data remains an empty array
            error_log("Error connecting to data source ID $data_source_id in index.php: " . $e->getMessage());
            // Optionally, set a flash message for the user
            // setFlashMessage('Could not connect to the selected data source to list tables.', 'error');
        }
    }
}

// Generate HTML options for all tables to be used globally, especially in modals
// The `true` for getOptions prepends a "Choose Table" or similar default option.
$masterTableOptionsHTML = getOptions($data, true);
Flight::set('masterTableOptionsHTML', $masterTableOptionsHTML);

// create table names json for autocompletion
$json = array();
foreach ($data as $datakey => $datavalue) {
    $json[]['word'] = $datavalue;
}
Flight::set('tableNamesJson', json_encode($json));

$table_options = Presenter::listTablesAsOptions($data);
Flight::set('table_options', $table_options);
//Flight::set('tables', ''); // Deprecate the old list view

if (false !== strpos($_SERVER['REQUEST_URI'], '/table')) {
    $currentTableKey = array_search(Flight::get('lastSegment'), $data, true);
    unset($data[$currentTableKey]); // remove current table

    // make dropdown options
    Flight::set('tablesOptions', getOptions($data));
}

// setup custom 404 page
//Flight::map(
//   'notFound',
//   function () {
       //include 'errors/404.html';
//       header("HTTP/1.0 404 Not Found");
//       exit('404 Not Found');
//   }
//);

// set global variables
Flight::set('appname', $config['appname']);

// Get data sources for dropdown
$data_sources = ORM::for_table('data_sources')->order_by_asc('source_name')->find_many();
$dataSourceOptions = '';
foreach ($data_sources as $source) {
    $selected = (isset($_SESSION['selected_data_source']) && $_SESSION['selected_data_source'] == $source->id) ? 'selected' : '';
    $dataSourceOptions .= "<option value=\"{$source->id}\" {$selected}>{$source->source_name}</option>";
}
Flight::set('dataSourceOptions', $dataSourceOptions);

// Also create a generic, unselected list of data sources for modals
$dataSourceOptionsUnselected = '<option value="">-- Choose Data Source --</option>';
foreach ($data_sources as $source) {
    $dataSourceOptionsUnselected .= "<option value=\"{$source->id}\">{$source->source_name}</option>";
}
Flight::set('dataSourceOptionsUnselected', $dataSourceOptionsUnselected);


///////// setup routes /////////////
require_once 'routes.php';

// auto-logout after inactivity for 10 minutes
timeoutLogout(60);

// flight now
Flight::start();
} catch (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    die("Fatal Error: ".$e->getMessage()." in ".$e->getFile()." on line ".$e->getLine());
}
//TODO: dropdown in header to select a different database
//TODO: ability to edit recordset in place
//TODO: tutorial of VisualQuery