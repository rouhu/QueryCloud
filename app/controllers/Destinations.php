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
            $destination->destination_type = $_POST['destination_type'];
            $destination->db_host = $_POST['db_host'];
            $destination->db_user = $_POST['db_user'];
            $destination->db_password = toggleEncryption($_POST['db_password']);

            if ($_POST['destination_type'] === 'database') {
                $destination->db_type = $_POST['db_type'];
                $destination->db_port = $_POST['db_port'];
                $destination->db_name = $_POST['db_name'];
            } else if ($_POST['destination_type'] === 'sftp') {
                $destination->db_type = 'sftp';
                $destination->db_port = $_POST['sftp_port'] ?: '22';
                $destination->db_name = null; // Not applicable for SFTP
            } else if ($_POST['destination_type'] === 's3') {
                $destination->db_type = 's3';
                $destination->db_host = $_POST['s3_bucket']; // Use db_host field for bucket name
                $destination->db_name = $_POST['s3_region']; // Use db_name field for region
                $destination->db_port = null; // Not applicable for S3
            }

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
