<?php
//Flight::route('GET /', array('Dashboard', 'index'));
//Flight::route('GET /home', array('Dashboard', 'index'));
//Flight::route('GET /login', array('Login', 'index'));
//Flight::route('POST /login', array('Login', 'loginuser'));
//Flight::route('GET /login/logout', array('Login', 'logout'));

Flight::route('GET /', 'Dashboard::index');
Flight::route('GET /home', 'Dashboard::index');
Flight::route('GET /login', 'Login::index');
Flight::route('POST /login', 'Login::loginuser');
Flight::route('GET /login/logout', 'Login::logout');

Flight::route('GET /dashboard', 'Dashboard::index');

// Destination Management
Flight::route('GET /destinations', 'Destinations::index');
Flight::route('POST /destinations/add', 'Destinations::add');
Flight::route('POST /destinations/delete', 'Destinations::delete');

// ETL Configuration
Flight::route('GET /etl/@query_id:[0-9]+', 'ETL::index');
Flight::route('POST /etl/save', 'ETL::save');

Flight::route('GET /export/csv', 'Export::csv');
Flight::route('GET /export/excel', 'Export::excel');
Flight::route('GET /table/[a-zA-Z0-9-_?+]+', 'Table::index');
Flight::route('POST /table/[a-zA-Z0-9-_?+]+', 'Table::runquery');
//Flight::route('POST /ajax/[a-zA-Z0-9-_?+]+', array('Ajax', Flight::get('lastSegment')));
$lastSegment = Flight::get('lastSegment');
Flight::route('POST /ajax/[a-zA-Z0-9-_?+]+', 'Ajax::'.$lastSegment);
Flight::route('GET /ajax/getSavedQueries', 'Ajax::getSavedQueries'); // Route for fetching saved queries
Flight::route('POST /ajax/saveTableFormatting', 'Ajax::saveTableFormatting'); // Route for saving table formatting
Flight::route('GET /ajax/getTableFormatting/@query_id', 'Ajax::getTableFormatting'); // Route for fetching specific table formatting
Flight::route('GET /ajax/getShareToken/@query_id', 'Ajax::getShareToken'); // Route for getting/generating a share token
Flight::route('POST /ajax/updateShareSettings', 'Ajax::updateShareSettings'); // Route for updating share settings (e.g. require_login)
//Flight::route('POST /ajax/@action', 'Ajax::@action');

// Public share route
Flight::route('GET /share/@token', 'Share::viewReport');