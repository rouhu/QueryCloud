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

    public static function run()
    {
        self::checkLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Flight::redirect('/dashboard');
            return;
        }

        $query_id = $_POST['query_id'];
        $redirect_url = Flight::get('base') . '/etl/' . $query_id;

        try {
            // 1. Fetch Query and Destination Details from the form POST
            // This ensures we run with the config currently on the screen, even if not saved.
            $destination_id = $_POST['destination_id'];
            $destination_table = $_POST['destination_table'];

            $saved_query = ORM::for_table('saved_queries')->find_one($query_id);
            if (!$saved_query) {
                throw new Exception("Saved query not found.");
            }

            $destination_db_details = ORM::for_table('destination_databases')->find_one($destination_id);
            if (!$destination_db_details) {
                throw new Exception("Destination database configuration not found.");
            }

            // 2. Establish Destination Connection
            $decrypted_password = toggleEncryption($destination_db_details->db_password);
            $dsn = "mysql:host={$destination_db_details->db_host};port={$destination_db_details->db_port};dbname={$destination_db_details->db_name};charset=utf8";
            $dest_pdo = new PDO($dsn, $destination_db_details->db_user, $decrypted_password);
            $dest_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 3. Fetch Source Data
            $source_pdo = Flight::get('db');
            $source_stmt = $source_pdo->prepare($saved_query->sql_query);
            $source_stmt->execute();
            $source_data = $source_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($source_data)) {
                setFlashMessage('Source query returned no data. Nothing to insert.', 'info');
                Flight::redirect($redirect_url);
                return;
            }

            // 4. Prepare and Insert Data into Destination
            $columns = array_keys($source_data[0]);
            $column_list = '`' . implode('`, `', $columns) . '`';
            $placeholders = rtrim(str_repeat('?,', count($columns)), ',');

            $insert_sql = "INSERT INTO `{$destination_table}` ({$column_list}) VALUES ({$placeholders})";

            $dest_pdo->beginTransaction();

            $insert_stmt = $dest_pdo->prepare($insert_sql);

            foreach ($source_data as $row) {
                $insert_stmt->execute(array_values($row));
            }

            $dest_pdo->commit();

            $rowCount = count($source_data);
            setFlashMessage("Successfully inserted {$rowCount} rows into `{$destination_table}`.");

        } catch (Exception $e) {
            if (isset($dest_pdo) && $dest_pdo->inTransaction()) {
                $dest_pdo->rollBack();
            }
            setFlashMessage("ETL Error: " . $e->getMessage(), 'error');
        }

        Flight::redirect($redirect_url);
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
