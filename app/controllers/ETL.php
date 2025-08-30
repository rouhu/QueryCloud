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
                $column_mapping = $_POST['column_mapping'] ?? [];
                // Filter out any columns that were not mapped
                $filtered_mapping = array_filter($column_mapping, function($value) {
                    return $value !== '';
                });

                $etl_config = array(
                    'destination_db_id' => $destination_id,
                    'destination_table_name' => $destination_table,
                    'column_mapping' => $filtered_mapping
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
            $source_db_details = ORM::for_table('data_sources')->find_one($saved_query->source_connection_id);
            if (!$source_db_details) {
                throw new Exception("Source database configuration for this query not found.");
            }

            $source_decrypted_password = toggleEncryption($source_db_details->db_password);
            $source_dsn = "mysql:host={$source_db_details->db_host};port={$source_db_details->db_port};dbname={$source_db_details->db_name};charset=utf8";
            $source_pdo = new PDO($source_dsn, $source_db_details->db_user, $source_decrypted_password);
            $source_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $source_stmt = $source_pdo->prepare($saved_query->sql_query);
            $source_stmt->execute();
            $source_data = $source_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($source_data)) {
                setFlashMessage('Source query returned no data. Nothing to insert.', 'info');
                Flight::redirect($redirect_url);
                return;
            }

            // 4. Prepare and Insert Data into Destination
            $etl_config = json_decode($saved_query->etl_config, true);
            $column_mapping = $etl_config['column_mapping'] ?? [];

            if (empty($column_mapping)) {
                throw new Exception("No column mapping defined. Please save a configuration.");
            }

            // Filter source data to only include columns that are in the mapping
            $mapped_source_columns = array_keys($column_mapping);
            $destination_columns = array_values($column_mapping);

            $column_list = '`' . implode('`, `', $destination_columns) . '`';
            $placeholders = rtrim(str_repeat('?,', count($destination_columns)), ',');

            $insert_sql = "INSERT INTO `{$destination_table}` ({$column_list}) VALUES ({$placeholders})";

            $dest_pdo->beginTransaction();
            $insert_stmt = $dest_pdo->prepare($insert_sql);

            foreach ($source_data as $row) {
                $ordered_row_values = [];
                foreach ($mapped_source_columns as $source_col) {
                    $ordered_row_values[] = $row[$source_col];
                }
                $insert_stmt->execute($ordered_row_values);
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
