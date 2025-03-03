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
Flight::route('GET /export/csv', 'Export::csv');
Flight::route('GET /export/excel', 'Export::excel');
Flight::route('GET /table/[a-zA-Z0-9-_?+]+', 'Table::index');
Flight::route('POST /table/[a-zA-Z0-9-_?+]+', 'Table::runquery');
//Flight::route('POST /ajax/[a-zA-Z0-9-_?+]+', array('Ajax', Flight::get('lastSegment')));
$lastSegment = Flight::get('lastSegment');
Flight::route('POST /ajax/[a-zA-Z0-9-_?+]+', 'Ajax::'.$lastSegment);
//Flight::route('POST /ajax/@action', 'Ajax::@action');