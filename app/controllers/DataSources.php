<?php

class DataSources
{
    private static $icon = 'fa fa-database';

    public static function index()
    {
        self::checkLogin();

        $sources = ORM::for_table('data_sources')->order_by_asc('source_name')->find_many();

        Flight::render(
            'datasources',
            array(
                'title' => 'Manage Data Sources',
                'icon' => self::$icon,
                'sources' => $sources
            )
        );
    }

    public static function add()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $source = ORM::for_table('data_sources')->create();
            $source->source_name = $_POST['source_name'];
            $source->db_type = $_POST['db_type'];
            $source->db_host = $_POST['db_host'];
            $source->db_port = $_POST['db_port'];
            $source->db_name = $_POST['db_name'];
            $source->db_user = $_POST['db_user'];
            $source->db_password = toggleEncryption($_POST['db_password']);
            $source->save();

            setFlashMessage('Data source added successfully!');
        }

        Flight::redirect('/datasources');
    }

    public static function delete()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $source = ORM::for_table('data_sources')->find_one($_POST['id']);
            if ($source) {
                $source->delete();
                setFlashMessage('Data source deleted successfully!');
            } else {
                setFlashMessage('Error: Data source not found.', 'error');
            }
        }

        Flight::redirect('/datasources');
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
