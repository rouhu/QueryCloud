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
                $etl_type = $_POST['etl_type'] ?? 'insert_only';
                $key_columns = $_POST['key_columns'] ?? [];

                // Filter out any columns that were not mapped
                $filtered_mapping = array_filter($column_mapping, function($value) {
                    return $value !== '';
                });

                // Get existing config to preserve settings not on this form (like last_run_at)
                $etl_config = $saved_query->etl_config ? json_decode($saved_query->etl_config, true) : [];

                // Always save these base settings
                $etl_config['destination_db_id'] = $destination_id;
                $etl_config['destination_table_name'] = $destination_table;
                $etl_config['etl_type'] = $etl_type;
                $etl_config['column_mapping'] = $filtered_mapping;
                $etl_config['key_columns'] = $key_columns;

                // --- New Scheduling Logic ---
                $schedule_type = $_POST['schedule_type'] ?? 'inactive';
                $etl_config['schedule_type'] = $schedule_type;

                // Unset all possible schedule-specific keys to ensure a clean save
                unset($etl_config['schedule_interval']);
                unset($etl_config['schedule_hours']);
                unset($etl_config['schedule_days']);

                // Set the specific schedule options based on the selected type
                switch ($schedule_type) {
                    case 'minutely':
                        $etl_config['schedule_interval'] = $_POST['schedule_interval'] ?? '5';
                        break;
                    case 'hourly':
                        $etl_config['schedule_hours'] = $_POST['schedule_hours'] ?? [];
                        break;
                    case 'daily':
                        $etl_config['schedule_days'] = $_POST['schedule_days'] ?? [];
                        break;
                    // For 'inactive' and 'weekly', no extra params are needed
                }

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

        $saved_query = ORM::for_table('saved_queries')->find_one($query_id);
        if (!$saved_query) {
            setFlashMessage("Error: Saved query not found.", 'error');
            Flight::redirect('/dashboard');
            return;
        }

        $result = self::executeEtlJob($saved_query);

        setFlashMessage($result['message'], $result['status']);
        Flight::redirect($redirect_url);
    }

    public static function executeEtlJob($saved_query)
    {
        $dest_pdo = null;
        try {
            $etl_config = json_decode($saved_query->etl_config, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($etl_config)) {
                throw new Exception("ETL configuration is missing or invalid for query ID {$saved_query->id}.");
            }

            $destination_id = $etl_config['destination_db_id'] ?? null;
            $destination_table = $etl_config['destination_table_name'] ?? null;

            if (!$destination_id || !$destination_table) {
                throw new Exception("Destination DB or table not configured.");
            }

            $destination_db_details = ORM::for_table('destination_databases')->find_one($destination_id);
            if (!$destination_db_details) {
                throw new Exception("Destination database configuration not found.");
            }

            $decrypted_password = toggleEncryption($destination_db_details->db_password);
            $dsn = "mysql:host={$destination_db_details->db_host};port={$destination_db_details->db_port};dbname={$destination_db_details->db_name};charset=utf8";
            $dest_pdo = new PDO($dsn, $destination_db_details->db_user, $decrypted_password);
            $dest_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
                return ['status' => 'info', 'message' => 'Source query returned no data. Nothing to process.'];
            }

            $column_mapping = $etl_config['column_mapping'] ?? [];
            $etl_type = $etl_config['etl_type'] ?? 'insert_only';
            $key_columns_source = $etl_config['key_columns'] ?? [];

            if (empty($column_mapping)) {
                throw new Exception("No column mapping defined in the configuration.");
            }
            if ($etl_type === 'update_or_insert' && empty($key_columns_source)) {
                throw new Exception("ETL type is 'Update or Insert' but no key columns are specified.");
            }

            $dest_pdo->beginTransaction();
            $inserted_count = 0;
            $updated_count = 0;

            if ($etl_type === 'update_or_insert') {
                foreach ($source_data as $row) {
                    $where_clauses = [];
                    $where_values = [];
                    foreach ($key_columns_source as $source_key) {
                        $dest_key = $column_mapping[$source_key];
                        $where_clauses[] = "`{$dest_key}` = ?";
                        $where_values[] = $row[$source_key];
                    }
                    $where_sql = implode(' AND ', $where_clauses);

                    $select_sql = "SELECT COUNT(*) FROM `{$destination_table}` WHERE {$where_sql}";
                    $select_stmt = $dest_pdo->prepare($select_sql);
                    $select_stmt->execute($where_values);
                    $record_exists = $select_stmt->fetchColumn() > 0;

                    $update_clauses = [];
                    $update_values = [];
                    $insert_cols = [];
                    $insert_placeholders = [];
                    $insert_values = [];

                    foreach ($column_mapping as $source_col => $dest_col) {
                        if (!in_array($source_col, $key_columns_source)) {
                            $update_clauses[] = "`{$dest_col}` = ?";
                            $update_values[] = $row[$source_col];
                        }
                        $insert_cols[] = "`{$dest_col}`";
                        $insert_placeholders[] = '?';
                        $insert_values[] = $row[$source_col];
                    }

                    if ($record_exists) {
                        $update_sql = "UPDATE `{$destination_table}` SET " . implode(', ', $update_clauses) . " WHERE {$where_sql}";
                        $update_stmt = $dest_pdo->prepare($update_sql);
                        $update_stmt->execute(array_merge($update_values, $where_values));
                        $updated_count++;
                    } else {
                        $insert_sql = "INSERT INTO `{$destination_table}` (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_placeholders) . ")";
                        $insert_stmt = $dest_pdo->prepare($insert_sql);
                        $insert_stmt->execute($insert_values);
                        $inserted_count++;
                    }
                }
            } else { // Insert Only
                $mapped_source_columns = array_keys($column_mapping);
                $destination_columns = array_values($column_mapping);
                $column_list = '`' . implode('`, `', $destination_columns) . '`';
                $placeholders = rtrim(str_repeat('?,', count($destination_columns)), ',');
                $insert_sql = "INSERT INTO `{$destination_table}` ({$column_list}) VALUES ({$placeholders})";
                $insert_stmt = $dest_pdo->prepare($insert_sql);

                foreach ($source_data as $row) {
                    $ordered_row_values = [];
                    foreach ($mapped_source_columns as $source_col) {
                        $ordered_row_values[] = $row[$source_col];
                    }
                    $insert_stmt->execute($ordered_row_values);
                }
                $inserted_count = count($source_data);
            }

            $dest_pdo->commit();

            return [
                'status' => 'success',
                'message' => "ETL process completed. Inserted {$inserted_count} rows, updated {$updated_count} rows."
            ];

        } catch (Exception $e) {
            if (isset($dest_pdo) && $dest_pdo->inTransaction()) {
                $dest_pdo->rollBack();
            }
            return ['status' => 'error', 'message' => "ETL Error: " . $e->getMessage()];
        }
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
