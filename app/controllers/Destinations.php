<?php

class Destinations
{
    private static $icon = 'fa fa-database';

    public static function index()
    {
        self::checkLogin();

        $destinations = ORM::for_table('destination_databases')->order_by_asc('connection_name')->find_many();

        Flight::render(
            'destinations',
            array(
                'title' => 'Manage Destinations',
                'icon' => self::$icon,
                'destinations' => $destinations
            )
        );
    }

    public static function add()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $destination = ORM::for_table('destination_databases')->create();
            $destination->connection_name = $_POST['connection_name'];
            $destination->db_host = $_POST['db_host'];
            $destination->db_port = $_POST['db_port'];
            $destination->db_name = $_POST['db_name'];
            $destination->db_user = $_POST['db_user'];
            $destination->db_password = $_POST['db_password']; // Security Note: Storing plain text password
            $destination->save();

            setFlashMessage('Destination added successfully!');
        }

        Flight::redirect('/destinations');
    }

    public static function delete()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $destination = ORM::for_table('destination_databases')->find_one($_POST['id']);
            if ($destination) {
                $destination->delete();
                setFlashMessage('Destination deleted successfully!');
            } else {
                setFlashMessage('Error: Destination not found.', 'error');
            }
        }

        Flight::redirect('/destinations');
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
