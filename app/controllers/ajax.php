<?php

class Ajax
{
    /**
     * Gets fields/columns of specified table and generates dropdown options
     */
    public static function gettablefields()
    {
        header('Content-Type: application/json');
        $table = $_POST['table'] ?? null;
        $response = ['status' => 'error', 'message' => 'Table name not provided.', 'fields' => []];

        if ($table) {
            try {
                // table columns
                // Ensure table name is quoted to prevent SQL injection, though it comes from client-side VQB selections
                $stmt = Flight::get('db')->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $fields = array_column($columns, 'Field');

                $response['status'] = 'success';
                $response['message'] = 'Fields retrieved successfully.';
                $response['fields'] = $fields;

            } catch (PDOException $e) {
                error_log("Error fetching fields for table $table: " . $e->getMessage());
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
        $tables = json_decode($tablesJSON, true) ?: [];
        $html = '';
    
        foreach ($tables as $table) {
            try {
                $stmt = Flight::get('db')->query("DESCRIBE `$table`");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $fields = array_column($columns, 'Field');
                
                $html .= '<optgroup label="'.$table.'">';
                foreach ($fields as $field) {
                    $html .= '<option value="'.$table.'.'.$field.'">'.$table.'.'.$field.'</option>';
                }
                $html .= '</optgroup>';
            } catch (PDOException $e) {
                error_log("Field fetch error for $table: ".$e->getMessage());
            }
        }
    
        echo $html;
    }

    public static function setDatabase()
    {
        $db = $_POST['db'];

        if ($db) {
            $_SESSION['db'] = $db;

            if ($_SESSION['db']) {
                echo 'ok';
            }
        }
    }

    public static function saveQuery()
    {
        header('Content-Type: application/json');
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        $query_id = $_POST['query_id'] ?? null;
        // Use null coalescing and provide default empty string for trim if not set
        $query_name = trim($_POST['query_name'] ?? '');
        $sql_query = $_POST['sql_query'] ?? null;
        $is_visual_query_provided = isset($_POST['is_visual_query']);
        $visual_params_provided = isset($_POST['visual_params']);

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

            // At least one field must be intended for update if query_id is present
            if (!$has_name_to_update && !$has_sql_to_update && !$has_visual_to_update) {
                 $response['message'] = 'No data provided to update for existing query.';
                 echo json_encode($response);
                 return;
            }

        } else { // This is a CREATE operation
            if (empty($query_name) || empty($sql_query)) {
                $response['message'] = 'For a new query, name and SQL query cannot be empty.';
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
                    if ($is_visual_query_provided) { // Update visual only if is_visual_query was part of the request
                        $saved_query->is_visual_query = $is_visual_query;
                        $saved_query->visual_params = $is_visual_query ? $visual_params : null;
                        $updated_fields_count++;
                    }

                    if ($updated_fields_count > 0) {
                        if ($saved_query->save()) {
                            $response['status'] = 'success';
                            $response['message'] = 'Query "' . htmlspecialchars($saved_query->query_name) . '" updated successfully!';
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
                $saved_query->is_visual_query = $is_visual_query;
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
}