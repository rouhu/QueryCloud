<?php

class Ajax
{
    /**
     * Gets fields/columns of specified table and generates dropdown options
     */
    public static function gettablefields()
    {
        $table = $_POST['table'];

        if ($table) {
            // table columns
            $stmt = Flight::get('db')->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            //pretty_print($columns);

            $fields = array();
            foreach ($columns as $values) {
                if (isset($values['Field'])) {
                    $fields[] = $values['Field'];
                }
            }
            //pretty_print($fields);

            echo getOptions($fields, true);
        }
    }

    /**
     * Gets fields/columns from specified tables and generates dropdown options
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
        header('Content-Type: application/json'); // Ensure JSON response
        $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

        if (empty($_POST['query_name']) || empty($_POST['sql_query'])) {
            $response['message'] = 'Query name and SQL query cannot be empty.';
            echo json_encode($response);
            return;
        }

        $query_name = trim($_POST['query_name']);
        $sql_query = $_POST['sql_query']; // SQL query is already a string from client

        try {
            $saved_query = ORM::for_table('saved_queries')->create();
            $saved_query->query_name = $query_name;
            $saved_query->sql_query = $sql_query;
            // created_at is handled by database default

            if ($saved_query->save()) {
                $response['status'] = 'success';
                $response['message'] = 'Query "' . htmlspecialchars($query_name) . '" saved successfully!';
            } else {
                $response['message'] = 'Failed to save query to the database.';
            }
        } catch (PDOException $e) {
            error_log("Error saving query: " . $e->getMessage()); // Log the actual error
            $response['message'] = 'Database error: Could not save the query. ' . $e->getMessage();
        } catch (Exception $e) {
            error_log("General error saving query: " . $e->getMessage());
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
                            ->select('sql_query') // Also fetch sql_query to be used by client-side run button
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