<?php

class ETL
{
    private static $icon = 'fa fa-cogs';

    public static function index($query_id)
    {
        self::checkLogin();

        // Fetch the saved query
        $saved_query = ORM::for_table('saved_queries')->find_one($query_id);

        if (!$saved_query) {
            setFlashMessage('Error: Saved query not found.', 'error');
            Flight::redirect('/dashboard');
            return;
        }

        // Fetch all available destinations
        $destinations = ORM::for_table('destination_databases')->order_by_asc('connection_name')->find_many();

        // Decode existing ETL config to pass to the view
        $etl_config = array();
        if ($saved_query->etl_config) {
            $etl_config = json_decode($saved_query->etl_config, true);
        }

        Flight::render(
            'etl',
            array(
                'title' => 'ETL Configuration for ' . $saved_query->query_name,
                'icon' => self::$icon,
                'saved_query' => $saved_query,
                'destinations' => $destinations,
                'etl_config' => $etl_config
            )
        );
    }

    public static function save()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $query_id = $_POST['query_id'];
            $destination_id = $_POST['destination_id'];
            $destination_table = $_POST['destination_table'];

            $saved_query = ORM::for_table('saved_queries')->find_one($query_id);

            if ($saved_query) {
                $etl_config = array(
                    'destination_db_id' => $destination_id,
                    'destination_table_name' => $destination_table
                );

                $saved_query->etl_config = json_encode($etl_config);
                $saved_query->save();

                setFlashMessage('ETL configuration saved successfully!');
            } else {
                setFlashMessage('Error: Saved query not found.', 'error');
            }

            Flight::redirect('/etl/' . $query_id);
        } else {
            Flight::redirect('/dashboard');
        }
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
