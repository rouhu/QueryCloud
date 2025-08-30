<?php

class Ajax
{
    public static function set_data_source()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        if (isset($_POST['data_source_id'])) {
            $_SESSION['selected_data_source'] = $_POST['data_source_id'];
            $response['status'] = 'success';
            $response['message'] = 'Data source set successfully.';
        } else {
            $response['message'] = 'Data source ID not provided.';
        }

        echo json_encode($response);
    }

    public static function get_tables_for_data_source()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.', 'tables' => []];

        if (isset($_POST['data_source_id'])) {
            $data_source_id = $_POST['data_source_id'];
            $source = ORM::for_table('data_sources')->find_one($data_source_id);

            if ($source) {
                try {
                    $password = toggleEncryption($source->db_password);

                    // Create a temporary connection to the data source
                    ORM::configure('mysql:host=' . $source->db_host . ';dbname=' . $source->db_name, null, 'temp_connection');
                    ORM::configure('username', $source->db_user, 'temp_connection');
                    ORM::configure('password', $password, 'temp_connection');

                    $db = ORM::get_db('temp_connection');
                    $stmt = $db->query('SHOW TABLES');
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $response['status'] = 'success';
                    $response['message'] = 'Tables retrieved successfully.';
                    $response['tables'] = $tables;

                } catch (PDOException $e) {
                    $response['message'] = 'Database connection failed: ' . $e->getMessage();
                }
            } else {
                $response['message'] = 'Data source not found.';
            }
        } else {
            $response['message'] = 'Data source ID not provided.';
        }

        echo json_encode($response);
    }

    /**
     * Gets fields/columns of specified table and generates dropdown options
     */
    public static function gettablefields()
    {
        header('Content-Type: application/json');
        $table = $_POST['table'] ?? null;
        $dataSourceId = $_POST['data_source_id'] ?? null;
        $response = ['status' => 'error', 'message' => 'Table name not provided.', 'fields' => []];

        if ($table) {
            try {
                $connection_name = Table::get_data_source_connection_name($dataSourceId);
                $db = ORM::get_db($connection_name);

                if (!$db) {
                    throw new Exception("Could not establish database connection.");
                }

                // table columns
                // Ensure table name is quoted to prevent SQL injection, though it comes from client-side VQB selections
                $stmt = $db->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $fields = array_column($columns, 'Field');

                $response['status'] = 'success';
                $response['message'] = 'Fields retrieved successfully.';
                $response['fields'] = $fields;

            } catch (Exception $e) {
                error_log("Error in gettablefields for table '$table': " . $e->getMessage());
                $response['message'] = "Database error fetching fields for table: " . htmlspecialchars($table);
            }
        }

        echo json_encode($response);
    }

    /**
     * Gets fields/columns from specified tables and generates dropdown options
     * This method is used for populating the main VQB field selectors and still returns HTML.
     */
    public static function getselectfields() {
        $tablesJSON = $_POST['tables'] ?? '[]';
        $dataSourceId = $_POST['data_source_id'] ?? null;
        $tables = json_decode($tablesJSON, true) ?: [];
        $html = '';

        // Use the reliable, reusable method from the Table class to get the connection
        $connection_name = Table::get_data_source_connection_name($dataSourceId);
        $db = ORM::get_db($connection_name);

        if (!$db) {
            error_log("getselectfields: Could not establish a database connection using connection name '$connection_name'.");
            echo ''; // Return empty string on catastrophic failure
            return;
        }
    
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $fields = array_column($columns, 'Field');
                
                $html .= '<optgroup label="'.$table.'">';
                foreach ($fields as $field) {
                    $html .= '<option value="'.$table.'.'.$field.'">'.$table.'.'.$field.'</option>';
                }
                $html .= '</optgroup>';
            } catch (PDOException $e) {
                error_log("Field fetch error for table '$table' in getselectfields on connection '$connection_name': ".$e->getMessage());
            }
        }
    
        echo $html;
    }

    public static function getSqlFromVisualParams()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        $visual_params_json = $_POST['visual_params'] ?? null;
        $primary_table_name = $_POST['primary_table_name'] ?? null;

        if (!$visual_params_json || !$primary_table_name) {
            $response['message'] = 'Missing required parameters (visual_params or primary_table_name).';
            echo json_encode($response);
            return;
        }

        $params_array = json_decode($visual_params_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $response['message'] = 'Invalid JSON in visual_params.';
            echo json_encode($response);
            return;
        }

        try {
            // Ensure Table class is available
            if (class_exists('Table')) {
                $generated_sql = Table::generateSqlFromVisualParams($params_array, $primary_table_name);
                $response['status'] = 'success';
                $response['message'] = 'SQL generated successfully.';
                $response['sql_query'] = $generated_sql;
            } else {
                $response['message'] = 'Table class not found.';
                error_log("Ajax::getSqlFromVisualParams - Table class not found.");
            }
        } catch (Exception $e) {
            $response['message'] = 'Error during SQL generation: ' . $e->getMessage();
            error_log("Ajax::getSqlFromVisualParams - Exception: " . $e->getMessage());
        }

        echo json_encode($response);
    }

    public static function saveQuery()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        $query_id = $_POST['query_id'] ?? null;
        // Use null coalescing and provide default empty string for trim if not set
        $query_name = trim($_POST['query_name'] ?? '');
        $sql_query = $_POST['sql_query'] ?? null;
        $source_connection_id = $_POST['source_connection_id'] ?? null;
        $is_visual_query_provided = isset($_POST['is_visual_query']);
        $visual_params_provided = isset($_POST['visual_params']);

        // If no ID is provided, check if a query with the same name exists to perform an update.
        if (!$query_id && !empty($query_name)) {
            $existing_query = ORM::for_table('saved_queries')->where('query_name', $query_name)->find_one();
            if ($existing_query) {
                $query_id = $existing_query->id;
            }
        }

        if ($query_id && is_numeric($query_id)) { // This is an UPDATE operation
            $has_name_to_update = isset($_POST['query_name']); // Check if 'query_name' key was sent
            $has_sql_to_update = isset($_POST['sql_query']);   // Check if 'sql_query' key was sent
            $has_visual_to_update = $is_visual_query_provided; // Check if 'is_visual_query' was sent

            // If query_name is explicitly sent, it must not be empty
            if ($has_name_to_update && empty($query_name)) {
                $response['message'] = 'Query name cannot be empty when provided for an update.';
                echo json_encode($response);
                return;
            }

            // If sql_query is explicitly sent, it must not be empty
            if ($has_sql_to_update && empty($sql_query)) {
                $response['message'] = 'SQL query cannot be empty when provided for an update.';
                echo json_encode($response);
                return;
            }

            $has_source_connection_to_update = isset($_POST['source_connection_id']);

            // At least one field must be intended for update if query_id is present
            if (!$has_name_to_update && !$has_sql_to_update && !$has_visual_to_update && !$has_source_connection_to_update) {
                 $response['message'] = 'No data provided to update for existing query.';
                 echo json_encode($response);
                 return;
            }

        } else { // This is a CREATE operation
            if (empty($query_name) || empty($sql_query) || empty($source_connection_id)) {
                $response['message'] = 'For a new query, name, SQL query and data source cannot be empty.';
                echo json_encode($response);
                return;
            }
        }

        $is_visual_query = $is_visual_query_provided ? filter_var($_POST['is_visual_query'], FILTER_VALIDATE_BOOLEAN) : false;
        $visual_params = ($is_visual_query && $visual_params_provided) ? $_POST['visual_params'] : null;

        try {
            if ($query_id && is_numeric($query_id)) {
                // Update existing query
                $saved_query = ORM::for_table('saved_queries')->find_one($query_id);
                if ($saved_query) {
                    $updated_fields_count = 0;
                    if (isset($_POST['query_name'])) {
                        $saved_query->query_name = $query_name; // Already trimmed
                        $updated_fields_count++;
                    }
                    if (isset($_POST['sql_query'])) {
                        $saved_query->sql_query = $sql_query;
                        $updated_fields_count++;
                    }
                    if (isset($_POST['source_connection_id'])) {
                        $saved_query->source_connection_id = $source_connection_id;
                        $updated_fields_count++;
                    }
                    if ($is_visual_query_provided) { // Update visual only if is_visual_query was part of the request
                        $saved_query->is_visual_query = (int)$is_visual_query;
                        $saved_query->visual_params = $is_visual_query ? $visual_params : null;
                        $updated_fields_count++;

                        // If visual_params were updated for a visual query, regenerate the SQL
                        if ($is_visual_query && $visual_params) {
                            $paramsArray = json_decode($visual_params, true);
                            if (is_array($paramsArray) && isset($paramsArray['primaryTable'])) {
                                $primaryTableName = $paramsArray['primaryTable'];
                                // Ensure Table class is available (it should be due to autoloading)
                                if (class_exists('Table')) {
                                    $new_sql_query = Table::generateSqlFromVisualParams($paramsArray, $primaryTableName);
                                    $saved_query->sql_query = $new_sql_query;
                                    // $updated_fields_count++; // sql_query is implicitly updated if visual params are.
                                                             // Or, if sql_query was also sent, this overwrites it.
                                                             // If sql_query was NOT sent, this adds it to the update.
                                    if (!isset($_POST['sql_query'])) { // If sql_query was not part of original POST, this counts as a new field to update.
                                        // This ensures the $updated_fields_count logic still works if only visual_params was sent.
                                        // However, the outer $updated_fields_count for $is_visual_query_provided already covers this.
                                        // No need to increment again unless we want to be very specific about sql_query being a *separate* update.
                                    }
                                } else {
                                    error_log("Ajax::saveQuery - Table class not found for SQL regeneration.");
                                    // Decide: fail, or save visual params but not SQL? For now, visual params save, SQL doesn't update.
                                }
                            } else {
                                error_log("Ajax::saveQuery - Failed to decode visual_params or primaryTable missing for SQL regeneration.");
                                // Decide behavior: fail, or save visual params but not SQL?
                            }
                        }
                    }

                    if ($updated_fields_count > 0) {
                        if ($saved_query->save()) {
                            $response['status'] = 'success';
                            $response['message'] = 'Query "' . htmlspecialchars($saved_query->query_name) . '" updated successfully!';
                            // Optionally, return the new SQL if it was regenerated
                            // if (isset($new_sql_query)) {
                            //    $response['new_sql_query'] = $new_sql_query;
                            // }
                        } else {
                            $response['message'] = 'Failed to update query in the database.';
                        }
                    } else {
                         // This case should ideally be caught by earlier validation,
                         // but as a safeguard if somehow fields were present but not set on $saved_query.
                        $response['message'] = 'No changes detected to save for the query.';
                        $response['status'] = 'info'; // Or 'success' if no change is not an error
                    }
                } else {
                    $response['message'] = 'Query not found for update.';
                }
            } else {
                // Create new query
                $saved_query = ORM::for_table('saved_queries')->create();
                $saved_query->query_name = $query_name; // Already trimmed
                $saved_query->sql_query = $sql_query; // Must be present due to earlier check
                $saved_query->source_connection_id = $source_connection_id;
                $saved_query->is_visual_query = (int)$is_visual_query;
                $saved_query->visual_params = $is_visual_query ? $visual_params : null;
                // created_at is handled by database default

                if ($saved_query->save()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Query "' . htmlspecialchars($query_name) . '" saved successfully!';
                    if (isset($saved_query->id)) { // Check if ID is available after save
                        $response['new_query_id'] = $saved_query->id();
                    }
                } else {
                    $response['message'] = 'Failed to save new query to the database.';
                }
            }
        } catch (PDOException $e) {
            error_log("Error saving/updating query: " . $e->getMessage());
            $response['message'] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error saving/updating query: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function getSavedQueries()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'Could not retrieve saved queries.', 'queries' => []];

        try {
            $queries = ORM::for_table('saved_queries')
                            ->select('id')
                            ->select('query_name')
                            ->select('sql_query')
                            ->select('source_connection_id')
                            ->select('is_visual_query')
                            ->select('visual_params')
                            ->select('created_at')
                            ->order_by_desc('created_at')
                            ->find_array();

            if ($queries !== false) { // find_array returns false on failure with some ORM configurations
                $response['status'] = 'success';
                $response['queries'] = $queries;
                $response['message'] = 'Saved queries retrieved successfully.';
            } else {
                // This path might not be hit if an exception is thrown first for actual DB errors
                $response['message'] = 'Failed to retrieve queries or no queries found.';
            }
        } catch (PDOException $e) {
            error_log("Error fetching saved queries: " . $e->getMessage());
            $response['message'] = 'Database error: Could not retrieve saved queries. ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error fetching saved queries: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function deleteQuery()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred while deleting the query.'];

        if (empty($_POST['query_id']) || !is_numeric($_POST['query_id'])) {
            $response['message'] = 'Invalid Query ID provided.';
            echo json_encode($response);
            return;
        }

        $query_id = $_POST['query_id'];

        try {
            $query_to_delete = ORM::for_table('saved_queries')->find_one($query_id);

            if ($query_to_delete) {
                if ($query_to_delete->delete()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Query deleted successfully.';
                } else {
                    $response['message'] = 'Failed to delete query from the database.';
                }
            } else {
                $response['message'] = 'Query not found or already deleted.';
                // Optionally set status to success if not finding it means it's "deleted" from user perspective
                // For now, keeping it as an error if not found.
            }
        } catch (PDOException $e) {
            error_log("Error deleting query: " . $e->getMessage());
            $response['message'] = 'Database error: Could not delete the query. ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error deleting query: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function saveTableFormatting()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        $query_id = $_POST['query_id'] ?? null;
        $table_formatting_json = $_POST['table_formatting'] ?? null;

        if (empty($query_id) || !is_numeric($query_id)) {
            $response['message'] = 'Invalid Query ID provided.';
            echo json_encode($response);
            return;
        }

        // Validate if table_formatting_json is a valid JSON string or null
        if (!is_null($table_formatting_json)) {
            json_decode($table_formatting_json);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $response['message'] = 'Invalid JSON string provided for table formatting.';
                echo json_encode($response);
                return;
            }
        } else {
            // Allow saving null to clear formatting
            $table_formatting_json = null;
        }

        try {
            $saved_query = ORM::for_table('saved_queries')->find_one($query_id);

            if ($saved_query) {
                $saved_query->table_formatting = $table_formatting_json;
                if ($saved_query->save()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Table formatting saved successfully!';
                } else {
                    $response['message'] = 'Failed to save table formatting to the database.';
                }
            } else {
                $response['message'] = 'Query not found. Cannot save formatting.';
            }
        } catch (PDOException $e) {
            error_log("Error saving table formatting: " . $e->getMessage());
            $response['message'] = 'Database error: Could not save table formatting. ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error saving table formatting: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function getTableFormatting($query_id)
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.', 'table_formatting' => null];

        if (empty($query_id) || !is_numeric($query_id)) {
            $response['message'] = 'Invalid Query ID provided.';
            echo json_encode($response);
            return;
        }

        try {
            $saved_query = ORM::for_table('saved_queries')
                            ->select('table_formatting')
                            ->find_one($query_id);

            if ($saved_query) {
                $response['status'] = 'success';
                $response['message'] = 'Table formatting retrieved successfully.';
                $response['table_formatting'] = $saved_query->table_formatting ? json_decode($saved_query->table_formatting, true) : null;
                if (json_last_error() !== JSON_ERROR_NONE && !is_null($saved_query->table_formatting)) {
                    // If there was an error decoding, but it wasn't null, log it and return null for formatting
                    error_log("Error decoding table_formatting JSON for query_id: $query_id. JSON: " . $saved_query->table_formatting);
                    $response['table_formatting'] = null;
                    $response['message'] = 'Formatting data is corrupted. Please save again.';
                    // $response['status'] could be set to 'error' or keep 'success' but with null data.
                }
            } else {
                $response['message'] = 'Query not found.';
                // Status remains 'error' or could be 'success' with null formatting if not finding is not an error for this call
            }
        } catch (PDOException $e) {
            error_log("Error fetching table formatting: " . $e->getMessage());
            $response['message'] = 'Database error: Could not retrieve table formatting. ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error fetching table formatting: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function getShareToken($query_id)
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.', 'token' => null, 'requires_login' => false];

        if (empty($query_id) || !is_numeric($query_id)) {
            $response['message'] = 'Invalid Query ID provided.';
            echo json_encode($response);
            return;
        }

        try {
            $saved_query = ORM::for_table('saved_queries')->find_one($query_id);

            if (!$saved_query) {
                $response['message'] = 'Query not found.';
                echo json_encode($response);
                return;
            }

            $config = Flight::get('config');
            $site_url_from_config = ''; // Default to empty string

            if (is_array($config) && isset($config['site_url']) && is_string($config['site_url']) && !empty(trim($config['site_url']))) {
                $site_url_from_config = $config['site_url'];
            } else {
                error_log("WARNING: config['site_url'] is not properly set in config.php. Share URLs may be incomplete.");
                // If site_url is critical and must be absolute, you might choose to return an error here:
                // $response['message'] = "Configuration error: Site URL not set. Cannot generate share links.";
                // echo json_encode($response);
                // return;
            }

            $site_url = rtrim($site_url_from_config, '/');

            if (!empty($saved_query->share_token)) {
                $response['status'] = 'success';
                $response['message'] = 'Existing share token retrieved.';
                $response['token'] = $saved_query->share_token;
                $response['share_url'] = $site_url . '/share/' . $saved_query->share_token;
                $response['requires_login'] = (bool)$saved_query->share_requires_login;
            } else {
                // No token exists yet. Client will decide if/when to generate via updateShareSettings or if getShareToken should force it.
                // For now, getShareToken just reports current state.
                $response['status'] = 'success'; // Success in fetching info, even if no token
                $response['message'] = 'No active share link. Settings can be updated.';
                $response['token'] = null;
                $response['share_url'] = null;
                $response['requires_login'] = (bool)$saved_query->share_requires_login;
                // Old logic to auto-generate if not exists:
                /* $token = null;
                $max_attempts = 5; // Prevent infinite loop in extremely unlikely collision scenario
                for ($i = 0; $i < $max_attempts; $i++) {
                    $potential_token = bin2hex(random_bytes(32)); // 64 characters
                    $exists = ORM::for_table('saved_queries')->where('share_token', $potential_token)->count();
                    if ($exists == 0) {
                        $token = $potential_token;
                        break;
                    }
                }

                if ($token) {
                    $saved_query->share_token = $token;
                    if ($saved_query->save()) {
                        $response['status'] = 'success';
                        $response['message'] = 'New share token generated and saved.';
                        $response['token'] = $token;
                    } else {
                        $response['message'] = 'Failed to save new share token to the database.';
                        error_log("Failed to save new share token for query_id: $query_id");
                    }
                } else {
                    $response['message'] = 'Failed to generate a unique share token after multiple attempts.';
                    error_log("Failed to generate unique share token for query_id: $query_id after $max_attempts attempts.");
                }
                */ // Correctly terminate the comment block here
            }
        } catch (PDOException $e) {
            error_log("Database error in getShareToken for query_id $query_id: " . $e->getMessage());
            $response['message'] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error in getShareToken for query_id $query_id: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function updateShareSettings()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        $query_id = $_POST['query_id'] ?? null;
        $require_login_input = $_POST['require_login'] ?? null; // Expected 'true' or 'false' as string

        if (empty($query_id) || !is_numeric($query_id)) {
            $response['message'] = 'Invalid Query ID provided.';
            echo json_encode($response);
            return;
        }

        if ($require_login_input === null) {
            $response['message'] = 'Require login status not provided.';
            echo json_encode($response);
            return;
        }

        $require_login = filter_var($require_login_input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($require_login === null) {
            $response['message'] = 'Invalid value for require_login. Must be true or false.';
            echo json_encode($response);
            return;
        }

        try {
            $saved_query = ORM::for_table('saved_queries')->find_one($query_id);

            if (!$saved_query) {
                $response['message'] = 'Query not found.';
                echo json_encode($response);
                return;
            }

            $saved_query->share_requires_login = $require_login ? 1 : 0;
            $token_generated_or_existed = !empty($saved_query->share_token);

            // If requiring login and no token exists, generate one
            if ($require_login && empty($saved_query->share_token)) {
                $token = null;
                $max_attempts = 5;
                for ($i = 0; $i < $max_attempts; $i++) {
                    $potential_token = bin2hex(random_bytes(32));
                    if (ORM::for_table('saved_queries')->where('share_token', $potential_token)->count() == 0) {
                        $token = $potential_token;
                        break;
                    }
                }
                if ($token) {
                    $saved_query->share_token = $token;
                    $token_generated_or_existed = true;
                    $response['new_token'] = $token; // Inform client a new token was generated
                } else {
                    $response['message'] = 'Failed to generate a unique share token. Settings not fully saved.';
                    error_log("Failed to generate unique share token during updateShareSettings for query_id: $query_id");
                    echo json_encode($response);
                    return;
                }
            }

            if ($saved_query->save()) {
                $response['status'] = 'success';
                $response['message'] = 'Share settings updated successfully.';
                if (isset($response['new_token'])) {
                    $response['message'] .= ' A new share token was generated.';
                }
                $response['requires_login'] = (bool)$saved_query->share_requires_login;
                $response['token'] = $saved_query->share_token;

                $config = Flight::get('config');
                $site_url_from_config = ''; // Default to empty string
                if (is_array($config) && isset($config['site_url']) && is_string($config['site_url']) && !empty(trim($config['site_url']))) {
                    $site_url_from_config = $config['site_url'];
                } else {
                    error_log("WARNING: config['site_url'] is not properly set in config.php. Share URLs may be incomplete in updateShareSettings response.");
                }
                $site_url = rtrim($site_url_from_config, '/');

                if ($saved_query->share_token) {
                    $response['share_url'] = $site_url . '/share/' . $saved_query->share_token;
                } else {
                    $response['share_url'] = null; // No token, no URL
                }
            } else {
                $response['message'] = 'Failed to update share settings in the database.';
            }
        } catch (PDOException $e) {
            error_log("Database error in updateShareSettings for query_id $query_id: " . $e->getMessage());
            $response['message'] = 'Database error: ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error in updateShareSettings for query_id $query_id: " . $e->getMessage());
            $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        }

        echo json_encode($response);
    }

    public static function get_destination_tables()
    {
        header('Content-Type: application/json');

        if (!isset($_POST['destination_id']) || empty($_POST['destination_id'])) {
            echo json_encode(array('status' => 'error', 'message' => 'Destination ID is required.'));
            return;
        }

        $destination_id = $_POST['destination_id'];

        try {
            $destination_db = ORM::for_table('destination_databases')->find_one($destination_id);

            if (!$destination_db) {
                throw new Exception("Destination database not found.");
            }

            $decrypted_password = toggleEncryption($destination_db->db_password);
            $dsn = "mysql:host={$destination_db->db_host};port={$destination_db->db_port};dbname={$destination_db->db_name};charset=utf8";
            $pdo = new PDO($dsn, $destination_db->db_user, $decrypted_password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode(array('status' => 'success', 'tables' => $tables));

        } catch (Exception $e) {
            echo json_encode(array('status' => 'error', 'message' => 'Error: ' . $e->getMessage()));
        }
    }
}