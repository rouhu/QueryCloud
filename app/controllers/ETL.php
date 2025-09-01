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

                // Get destination details to determine type
                $destination_db_details = ORM::for_table('destination_databases')->find_one($destination_id);
                $dest_type = isset($destination_db_details->destination_type) ? $destination_db_details->destination_type : 'database';

                // Always save these base settings
                $etl_config['destination_db_id'] = $destination_id;
                $etl_config['notification_email'] = $_POST['notification_email'] ?? '';
                
                if ($dest_type === 'sftp') {
                    // For SFTP destinations, save CSV separator instead of table/mapping
                    $etl_config['csv_separator'] = $_POST['csv_separator'] ?? ',';
                    // Remove database-specific settings
                    unset($etl_config['destination_table_name']);
                    unset($etl_config['etl_type']);
                    unset($etl_config['column_mapping']);
                    unset($etl_config['key_columns']);
                } else if ($dest_type === 's3') {
                    // For S3 destinations, save CSV separator and optional folder path
                    $etl_config['csv_separator'] = $_POST['csv_separator'] ?? ',';
                    $etl_config['s3_folder_path'] = $_POST['s3_folder_path'] ?? '';
                    // Remove database-specific settings
                    unset($etl_config['destination_table_name']);
                    unset($etl_config['etl_type']);
                    unset($etl_config['column_mapping']);
                    unset($etl_config['key_columns']);
                } else {
                    // For database destinations, save traditional settings
                    $etl_config['destination_table_name'] = $destination_table;
                    $etl_config['etl_type'] = $etl_type;
                    $etl_config['column_mapping'] = $filtered_mapping;
                    $etl_config['key_columns'] = $key_columns;
                    // Remove SFTP-specific settings
                    unset($etl_config['csv_separator']);
                }

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
            if (!$destination_id) {
                throw new Exception("Destination not configured.");
            }

            $destination_db_details = ORM::for_table('destination_databases')->find_one($destination_id);
            if (!$destination_db_details) {
                throw new Exception("Destination configuration not found.");
            }

            // Get source data first (common for both database and SFTP)
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

            // Determine destination type
            $dest_type = isset($destination_db_details->destination_type) ? $destination_db_details->destination_type : 'database';

            if ($dest_type === 'sftp') {
                return self::executeEtlToSftp($source_data, $destination_db_details, $etl_config, $saved_query);
            } else if ($dest_type === 's3') {
                return self::executeEtlToS3($source_data, $destination_db_details, $etl_config, $saved_query);
            } else {
                return self::executeEtlToDatabase($source_data, $destination_db_details, $etl_config, $saved_query);
            }

        } catch (Exception $e) {
            if (isset($dest_pdo) && $dest_pdo->inTransaction()) {
                $dest_pdo->rollBack();
            }
            return ['status' => 'error', 'message' => "ETL Error: " . $e->getMessage()];
        }
    }

    private static function executeEtlToSftp($source_data, $destination_db_details, $etl_config, $saved_query)
    {
        try {
            // Try to use built-in PHP SSH2 extension first (if available)
            if (function_exists('ssh2_connect') && function_exists('ssh2_sftp')) {
                return self::executeEtlToSftpNative($source_data, $destination_db_details, $etl_config, $saved_query);
            }
            
            // Load phpseclib classes using autoloader
            if (file_exists('vendor/autoload.php')) {
                require_once 'vendor/autoload.php';
            } else {
                throw new Exception("Composer autoloader not found and PHP SSH2 extension not available. Please run 'composer install' or install php-ssh2 extension.");
            }
            
            // Try different phpseclib versions
            $sftpClass = null;
            
            // Check if classes are already loaded via autoloader
            if (class_exists('\phpseclib3\Net\SFTP')) {
                $sftpClass = '\phpseclib3\Net\SFTP';
            } elseif (class_exists('\phpseclib\Net\SFTP')) {
                $sftpClass = '\phpseclib\Net\SFTP';
            } elseif (class_exists('Net_SFTP')) {
                $sftpClass = 'Net_SFTP';
            } else {
                throw new Exception("No compatible SFTP library found. Please ensure phpseclib is properly installed via Composer or install the php-ssh2 extension.");
            }
            
            $csv_separator = $etl_config['csv_separator'] ?? ',';
            
            // Generate CSV content
            $csv_content = '';
            if (!empty($source_data)) {
                // Add header row
                $headers = array_keys($source_data[0]);
                $csv_content .= implode($csv_separator, $headers) . "\n";
                
                // Add data rows
                foreach ($source_data as $row) {
                    $csv_row = [];
                    foreach ($row as $value) {
                        // Escape quotes and wrap in quotes if necessary
                        $escaped_value = str_replace('"', '""', $value);
                        if (strpos($escaped_value, $csv_separator) !== false || strpos($escaped_value, '"') !== false || strpos($escaped_value, "\n") !== false) {
                            $csv_row[] = '"' . $escaped_value . '"';
                        } else {
                            $csv_row[] = $escaped_value;
                        }
                    }
                    $csv_content .= implode($csv_separator, $csv_row) . "\n";
                }
            }

            // Connect to SFTP server
            $sftp = new $sftpClass($destination_db_details->db_host, $destination_db_details->db_port ?: 22);
            
            $decrypted_password = toggleEncryption($destination_db_details->db_password);
            if (!$sftp->login($destination_db_details->db_user, $decrypted_password)) {
                throw new Exception("SFTP login failed.");
            }

            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "query_{$saved_query->id}_{$timestamp}.csv";
            
            // Upload CSV file
            if (!$sftp->put($filename, $csv_content)) {
                throw new Exception("Failed to upload CSV file to SFTP server.");
            }

            return [
                'status' => 'success',
                'message' => "ETL process completed. Generated CSV with " . count($source_data) . " rows and uploaded to SFTP as '{$filename}'."
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "SFTP ETL Error: " . $e->getMessage()];
        }
    }

    private static function executeEtlToDatabase($source_data, $destination_db_details, $etl_config, $saved_query)
    {
        $dest_pdo = null;
        try {
            $destination_table = $etl_config['destination_table_name'] ?? null;
            if (!$destination_table) {
                throw new Exception("Destination table not configured.");
            }

            $decrypted_password = toggleEncryption($destination_db_details->db_password);
            $dsn = "mysql:host={$destination_db_details->db_host};port={$destination_db_details->db_port};dbname={$destination_db_details->db_name};charset=utf8";
            $dest_pdo = new PDO($dsn, $destination_db_details->db_user, $decrypted_password);
            $dest_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
            throw $e;
        }
    }

    private static function executeEtlToS3($source_data, $destination_db_details, $etl_config, $saved_query)
    {
        try {
            // Load AWS SDK using correct relative path from app/controllers/ directory
            $autoloader_path = __DIR__ . '/../../vendor/autoload.php';
            if (file_exists($autoloader_path)) {
                require $autoloader_path;
            } else {
                // Try alternative paths if the first one doesn't work
                $alternative_paths = [
                    dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php',
                    realpath(dirname(__FILE__) . '/../../vendor/autoload.php'),
                    'vendor/autoload.php'
                ];
                
                $loaded = false;
                foreach ($alternative_paths as $alt_path) {
                    if (file_exists($alt_path)) {
                        require $alt_path;
                        $loaded = true;
                        break;
                    }
                }
                
                if (!$loaded) {
                    throw new Exception("Composer autoloader not found. Tried paths: $autoloader_path, " . implode(', ', $alternative_paths) . ". Please run 'composer install' to install AWS SDK.");
                }
            }

            // Verify S3Client class is available
            if (!class_exists('\Aws\S3\S3Client')) {
                throw new Exception("AWS SDK S3Client class not found. Please ensure AWS SDK is properly installed and autoloader is working.");
            }

            $csv_separator = $etl_config['csv_separator'] ?? ',';
            
            // Generate CSV content
            $csv_content = '';
            if (!empty($source_data)) {
                // Add header row
                $headers = array_keys($source_data[0]);
                $csv_content .= implode($csv_separator, $headers) . "\n";
                
                // Add data rows
                foreach ($source_data as $row) {
                    $csv_row = [];
                    foreach ($row as $value) {
                        // Escape quotes and wrap in quotes if necessary
                        $escaped_value = str_replace('"', '""', $value);
                        if (strpos($escaped_value, $csv_separator) !== false || strpos($escaped_value, '"') !== false || strpos($escaped_value, "\n") !== false) {
                            $csv_row[] = '"' . $escaped_value . '"';
                        } else {
                            $csv_row[] = $escaped_value;
                        }
                    }
                    $csv_content .= implode($csv_separator, $csv_row) . "\n";
                }
            }

            // Get S3 configuration
            $bucket_name = $destination_db_details->db_host; // Bucket name stored in db_host field
            $region = $destination_db_details->db_name; // Region stored in db_name field
            $access_key = $destination_db_details->db_user; // Access Key ID stored in db_user field
            $secret_key = toggleEncryption($destination_db_details->db_password); // Secret Key stored in db_password field

            // Create S3 client
            $s3Client = new \Aws\S3\S3Client([
                'version' => 'latest',
                'region' => $region,
                'credentials' => [
                    'key' => $access_key,
                    'secret' => $secret_key,
                ],
            ]);

            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "query_{$saved_query->id}_{$timestamp}.csv";
            
            // Add folder path if specified
            $folder_path = trim($etl_config['s3_folder_path'] ?? '');
            if (!empty($folder_path)) {
                // Ensure folder path ends with /
                $folder_path = rtrim($folder_path, '/') . '/';
                $s3_key = $folder_path . $filename;
            } else {
                $s3_key = $filename;
            }

            // Upload CSV to S3
            try {
                $result = $s3Client->putObject([
                    'Bucket' => $bucket_name,
                    'Key' => $s3_key,
                    'Body' => $csv_content,
                    'ContentType' => 'text/csv',
                    'Metadata' => [
                        'query_id' => (string)$saved_query->id,
                        'query_name' => $saved_query->query_name,
                        'rows_count' => (string)count($source_data),
                        'generated_at' => date('c'),
                    ],
                ]);

                $s3_url = $result['ObjectURL'] ?? "s3://{$bucket_name}/{$s3_key}";

                return [
                    'status' => 'success',
                    'message' => "ETL process completed. Generated CSV with " . count($source_data) . " rows and uploaded to S3 bucket '{$bucket_name}' as '{$s3_key}'."
                ];

            } catch (\Aws\Exception\AwsException $e) {
                throw new Exception("AWS S3 Error: " . $e->getMessage());
            }

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "S3 ETL Error: " . $e->getMessage()];
        }
    }

    private static function executeEtlToSftpNative($source_data, $destination_db_details, $etl_config, $saved_query)
    {
        try {
            $csv_separator = $etl_config['csv_separator'] ?? ',';
            
            // Generate CSV content
            $csv_content = '';
            if (!empty($source_data)) {
                // Add header row
                $headers = array_keys($source_data[0]);
                $csv_content .= implode($csv_separator, $headers) . "\n";
                
                // Add data rows
                foreach ($source_data as $row) {
                    $csv_row = [];
                    foreach ($row as $value) {
                        // Escape quotes and wrap in quotes if necessary
                        $escaped_value = str_replace('"', '""', $value);
                        if (strpos($escaped_value, $csv_separator) !== false || strpos($escaped_value, '"') !== false || strpos($escaped_value, "\n") !== false) {
                            $csv_row[] = '"' . $escaped_value . '"';
                        } else {
                            $csv_row[] = $escaped_value;
                        }
                    }
                    $csv_content .= implode($csv_separator, $csv_row) . "\n";
                }
            }

            // Connect to SSH server using native extension
            $connection = ssh2_connect($destination_db_details->db_host, $destination_db_details->db_port ?: 22);
            if (!$connection) {
                throw new Exception("Failed to connect to SSH server.");
            }

            $decrypted_password = toggleEncryption($destination_db_details->db_password);
            if (!ssh2_auth_password($connection, $destination_db_details->db_user, $decrypted_password)) {
                throw new Exception("SSH authentication failed.");
            }

            // Initialize SFTP subsystem
            $sftp = ssh2_sftp($connection);
            if (!$sftp) {
                throw new Exception("Failed to initialize SFTP subsystem.");
            }

            // Generate filename with timestamp
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "query_{$saved_query->id}_{$timestamp}.csv";
            
            // Upload CSV file using native SSH2 functions
            $stream = fopen("ssh2.sftp://{$sftp}/{$filename}", 'w');
            if (!$stream) {
                throw new Exception("Failed to open SFTP file stream for writing.");
            }
            
            if (fwrite($stream, $csv_content) === false) {
                fclose($stream);
                throw new Exception("Failed to write CSV content to SFTP file.");
            }
            
            fclose($stream);

            return [
                'status' => 'success',
                'message' => "ETL process completed using native SSH2. Generated CSV with " . count($source_data) . " rows and uploaded to SFTP as '{$filename}'."
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => "Native SFTP ETL Error: " . $e->getMessage()];
        }
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
