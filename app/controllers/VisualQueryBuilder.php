<?php

set_time_limit(3600);

class VisualQueryBuilder
{
    private static $icon = 'fa fa-database';

    public static function index($table = null)
    {
        // Checks whether or not user is logged in
        self::checkLogin();

        try {
            $currentTable = $table;
            $fields = '';
            $dataSourceId = null;
            $editMode = false;
            $queryId = null;
            $queryName = '';
            $visualParams = null;

            // Check if this is an edit request from POST data
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $editMode = isset($_POST['edit_mode']) && $_POST['edit_mode'] === 'true';
                $queryId = $_POST['query_id'] ?? null;
                $dataSourceId = $_POST['data_source_id'] ?? null;
                
                if (isset($_POST['visual_params']) && $_POST['visual_params']) {
                    $visualParams = json_decode($_POST['visual_params'], true);
                    if ($visualParams && !$currentTable && isset($visualParams['primaryTable'])) {
                        $currentTable = $visualParams['primaryTable'];
                    }
                }

                // Set the data source session if provided
                if ($dataSourceId) {
                    $_SESSION['selected_data_source'] = $dataSourceId;
                }
            }

            // Get current data source from session if not set
            if (!$dataSourceId && isset($_SESSION['selected_data_source'])) {
                $dataSourceId = $_SESSION['selected_data_source'];
            }

            // Get table fields if we have a table
            if ($currentTable) {
                // Store current table in session for form submissions
                $_SESSION['current_vqb_table'] = $currentTable;
                
                $connection_name = Table::get_data_source_connection_name($dataSourceId);
                $db = ORM::get_db($connection_name);
                $source = ORM::for_table('data_sources')->find_one($dataSourceId);
                $db_type = $source ? $source->db_type : 'mysql';

                try {
                    $table_fields_data = get_table_columns($db, $db_type, $currentTable);
                    $fields = getOptions(array_column($table_fields_data, 'Field'), false, $currentTable);
                } catch (PDOException $e) {
                    $fields = '<option value="">Error loading fields</option>';
                    error_log("VQB field loading error: " . $e->getMessage());
                }
            }

            // Get tables options for joins
            $tablesOptionsHTML = '<option value="">Choose Table</option>';
            if ($dataSourceId) {
                try {
                    $connection_name = Table::get_data_source_connection_name($dataSourceId);
                    $db = ORM::get_db($connection_name);
                    $source = ORM::for_table('data_sources')->find_one($dataSourceId);
                    $db_type = $source ? $source->db_type : 'mysql';
                    
                    $tableNames = get_tables($db, $db_type);
                    $tablesOptionsHTML = getOptions($tableNames, true);
                } catch (Exception $e) {
                    error_log("VQB table options error: " . $e->getMessage());
                }
            }

            // Set global data for the view
            Flight::set('masterTableOptionsHTML', $tablesOptionsHTML);

            Flight::render('visual_query_builder', array(
                'title' => 'Visual Query Builder',
                'icon' => self::$icon,
                'currentTable' => $currentTable,
                'fields' => $fields,
                'dataSourceId' => $dataSourceId,
                'editMode' => $editMode,
                'queryId' => $queryId,
                'queryName' => $queryName,
                'visualParams' => $visualParams,
                'tablesOptionsHTML' => $tablesOptionsHTML
            ));

        } catch (Exception $e) {
            Flight::set('error', 'Error loading Visual Query Builder: ' . $e->getMessage());
            Flight::render('error_page');
        }
    }

    public static function edit($query_id)
    {
        // Checks whether or not user is logged in
        self::checkLogin();

        try {
            // Get the saved query data
            $savedQuery = ORM::for_table('saved_queries')->find_one($query_id);
            
            if (!$savedQuery) {
                throw new Exception('Query not found with ID: ' . $query_id);
            }

            if (!$savedQuery->is_visual_query) {
                // Redirect to SQL editor for non-visual queries
                Flight::redirect(Flight::get('base') . '/table/' . ($savedQuery->primaryTable ?? 'custom_query') . '?edit_sql=' . $query_id);
                return;
            }

            // Parse visual parameters
            $visualParams = null;
            $currentTable = null;
            
            if ($savedQuery->visual_params) {
                $visualParams = json_decode($savedQuery->visual_params, true);
                $currentTable = $visualParams['primaryTable'] ?? null;
            }

            if (!$currentTable) {
                throw new Exception('Could not determine table for visual query');
            }

            // Set data source session
            if ($savedQuery->source_connection_id) {
                $_SESSION['selected_data_source'] = $savedQuery->source_connection_id;
            }

            $dataSourceId = $savedQuery->source_connection_id;

            // Get table fields
            $fields = '';
            if ($currentTable) {
                $connection_name = Table::get_data_source_connection_name($dataSourceId);
                $db = ORM::get_db($connection_name);
                $source = ORM::for_table('data_sources')->find_one($dataSourceId);
                $db_type = $source ? $source->db_type : 'mysql';

                try {
                    $table_fields_data = get_table_columns($db, $db_type, $currentTable);
                    $fields = getOptions(array_column($table_fields_data, 'Field'), false, $currentTable);
                } catch (PDOException $e) {
                    $fields = '<option value="">Error loading fields</option>';
                    error_log("VQB field loading error: " . $e->getMessage());
                }
            }

            // Get tables options for joins
            $tablesOptionsHTML = '<option value="">Choose Table</option>';
            if ($dataSourceId) {
                try {
                    $connection_name = Table::get_data_source_connection_name($dataSourceId);
                    $db = ORM::get_db($connection_name);
                    $source = ORM::for_table('data_sources')->find_one($dataSourceId);
                    $db_type = $source ? $source->db_type : 'mysql';
                    
                    $tableNames = get_tables($db, $db_type);
                    $tablesOptionsHTML = getOptions($tableNames, true);
                } catch (Exception $e) {
                    error_log("VQB table options error: " . $e->getMessage());
                }
            }

            // Set global data for the view
            Flight::set('masterTableOptionsHTML', $tablesOptionsHTML);

            Flight::render('visual_query_builder', array(
                'title' => 'Visual Query Builder - Edit: ' . $savedQuery->query_name,
                'icon' => self::$icon,
                'currentTable' => $currentTable,
                'fields' => $fields,
                'dataSourceId' => $dataSourceId,
                'editMode' => true,
                'queryId' => $query_id,
                'queryName' => $savedQuery->query_name,
                'visualParams' => $visualParams,
                'tablesOptionsHTML' => $tablesOptionsHTML
            ));

        } catch (Exception $e) {
            Flight::set('error', 'Error loading saved query for editing: ' . $e->getMessage());
            Flight::render('error_page');
        }
    }

    public static function run($table = null)
    {
        // Checks whether or not user is logged in
        self::checkLogin();

        try {
            $currentTable = $table;
            $queryId = $_POST['query_id'] ?? null;
            $queryName = $_POST['running_saved_query_name'] ?? '';

            // If table is numeric (from edit route), ignore it and get from POST or visual params
            if ($currentTable && is_numeric($currentTable)) {
                $currentTable = null;
            }

            // Try to get table from POST data or visual params
            if (!$currentTable) {
                if (isset($_POST['visual_params'])) {
                    $visualParamsFromPost = json_decode($_POST['visual_params'], true);
                    if ($visualParamsFromPost && isset($visualParamsFromPost['primaryTable'])) {
                        $currentTable = $visualParamsFromPost['primaryTable'];
                    }
                }
                
                // Try to extract from form data
                if (!$currentTable) {
                    // Look for table context in session or globals
                    if (isset($_SESSION['current_vqb_table'])) {
                        $currentTable = $_SESSION['current_vqb_table'];
                    }
                }
                
                // Extract from visual query fields - if we have joined tables, use the first available table
                if (!$currentTable && isset($_POST['fields']) && !empty($_POST['fields'])) {
                    $firstField = $_POST['fields'][0];
                    if (strpos($firstField, '.') !== false) {
                        $currentTable = explode('.', $firstField)[0];
                    }
                }
            }

            if (!$currentTable) {
                throw new Exception('Could not determine table for Visual Query Builder. Please ensure you have selected fields or set up the query properly.');
            }

            // Get data source info
            $dataSourceId = $_SESSION['selected_data_source'] ?? null;
            $connection_name = Table::get_data_source_connection_name($dataSourceId);
            $source = ORM::for_table('data_sources')->find_one($dataSourceId);
            $db_type = $source ? $source->db_type : 'mysql';
            $source_name = $source ? $source->name : 'Unknown';

            // Extract visual parameters and generate SQL
            $visualParams = self::extractVisualParams($_POST);
            $visualParams['primaryTable'] = $currentTable;
            
            $sql = Table::generateSqlFromVisualParams($_POST, $currentTable, $db_type);

            if (empty($sql)) {
                throw new Exception('Could not generate SQL from visual query parameters');
            }

            // Execute the query and get results
            $startTime = microtime(true);
            
            try {
                $db = ORM::get_db($connection_name);
                
                if ($db_type === 'mysql') {
                    // turn on query profiling for MySQL
                    $db->query('SET profiling = 1;');
                }
                
                $result = $db->query($sql);
                $data = $result->fetchAll(PDO::FETCH_ASSOC);
                
                if ($db_type === 'mysql') {
                    // find out execution time for MySQL
                    $exec_time_result = $db->query(
                       'SELECT query_id, SUM(duration) FROM information_schema.profiling GROUP BY query_id ORDER BY query_id DESC LIMIT 1;'
                    );
                    $exec_time_row = $exec_time_result->fetchAll(PDO::FETCH_NUM);
                    $timeTaken = $exec_time_row[0][1] ?? '0.00';
                } else {
                    $endTime = microtime(true);
                    $timeTaken = round($endTime - $startTime, 2);
                }
                
                // Handle table formatting if this is a saved query
                $original_header = [];
                $display_header = [];
                if (!empty($data) && isset($data[0])) {
                    $original_header = array_keys($data[0]);
                    $display_header = $original_header;
                    
                    // Apply table formatting if available
                    if ($queryId) {
                        $query_with_formatting = ORM::for_table('saved_queries')->find_one($queryId);
                        if ($query_with_formatting && !empty($query_with_formatting->table_formatting)) {
                            try {
                                $formatting_rules = json_decode($query_with_formatting->table_formatting, true);
                                if (json_last_error() === JSON_ERROR_NONE && isset($formatting_rules['column_titles'])) {
                                    $new_display_header = [];
                                    foreach ($original_header as $original_col_name) {
                                        $new_display_header[] = (!empty($formatting_rules['column_titles'][$original_col_name])) ? $formatting_rules['column_titles'][$original_col_name] : $original_col_name;
                                    }
                                    $display_header = $new_display_header;
                                }
                            } catch (Exception $e) {
                                error_log("Error decoding table formatting JSON: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Store data for export functionality
                    $_SESSION['tableData'] = array($display_header);
                    foreach ($data as $row) {
                        $_SESSION['tableData'][] = array_values($row);
                    }
                } else {
                    $_SESSION['tableData'] = array();
                }
                
                // Generate table HTML using the same method as Table controller
                $tableData = Presenter::listTableData($data, [], $display_header, $original_header);
                
            } catch (Exception $e) {
                throw new Exception('Error executing query: ' . $e->getMessage());
            }

            // Set the table segment for the results
            Flight::set('lastSegment', $currentTable);

            // Prepare view data
            $visualParamsJson = json_encode($visualParams);
            $isVisualQuery = !empty($visualParamsJson) && $visualParamsJson !== '[]' && $visualParamsJson !== '{}';
            
            // Check if this was a saved visual query
            $wasSavedVisual = false;
            if ($queryId && $isVisualQuery) {
                $savedQuery = ORM::for_table('saved_queries')->find_one($queryId);
                $wasSavedVisual = $savedQuery && $savedQuery->is_visual_query;
            }

            // For ad-hoc VQB queries without a query ID, create a temporary query record
            // so that ETL and Format Table buttons can function
            if (!$queryId && $isVisualQuery) {
                try {
                    $tempQuery = ORM::for_table('saved_queries')->create();
                    $tempQuery->query_name = 'VQB Temp Query - ' . $currentTable . ' (' . date('Y-m-d H:i:s') . ')';
                    $tempQuery->sql_query = $sql;
                    $tempQuery->source_connection_id = $dataSourceId;
                    $tempQuery->is_visual_query = true;
                    $tempQuery->visual_params = $visualParamsJson;
                    $tempQuery->created_at = date('Y-m-d H:i:s');
                    $tempQuery->is_temporary = true; // Flag for cleanup later if needed
                    $tempQuery->save();
                    
                    $queryId = $tempQuery->id;
                    $queryName = $tempQuery->query_name;
                    
                } catch (Exception $e) {
                    error_log("Error creating temporary query record: " . $e->getMessage());
                    // Continue without query ID if temp record creation fails
                }
            }

            // Render the VQB results view
            Flight::render('vqb_results', array(
                'title' => 'VQB Results - ' . strtoupper($currentTable),
                'icon' => self::$icon,
                'table_data' => $tableData,
                'query' => '<pre>' . htmlspecialchars($sql, ENT_QUOTES, 'UTF-8') . '</pre>',
                'timetaken' => $timeTaken,
                'printArray' => '',
                'visual_params_json' => $visualParamsJson,
                'executed_query_id' => $queryId,
                'executed_query_name' => $queryName,
                'executed_query_source_connection_id' => $dataSourceId,
                'executed_query_source_name' => $source_name,
                'executed_query_was_saved_visual' => $wasSavedVisual,
                'currentTable' => $currentTable
            ));

        } catch (Exception $e) {
            Flight::set('error', 'Error running Visual Query: ' . $e->getMessage());
            Flight::render('error_page');
        }
    }

    public static function getFieldsForTables()
    {
        // AJAX endpoint for getting fields for tables (used by JavaScript)
        try {
            $tables = json_decode($_POST['tables'] ?? '[]', true);
            $dataSourceId = $_POST['data_source_id'] ?? null;

            if (empty($tables)) {
                echo '<option value="">No tables specified</option>';
                return;
            }

            $connection_name = Table::get_data_source_connection_name($dataSourceId);
            $db = ORM::get_db($connection_name);
            $source = ORM::for_table('data_sources')->find_one($dataSourceId);
            $db_type = $source ? $source->db_type : 'mysql';

            $fieldsHtml = '';
            $allFields = [];

            foreach ($tables as $tableName) {
                try {
                    $table_fields_data = get_table_columns($db, $db_type, $tableName);
                    $tableFields = array_column($table_fields_data, 'Field');
                    
                    foreach ($tableFields as $field) {
                        $fieldValue = $tableName . '.' . $field;
                        $allFields[$tableName][] = [
                            'value' => $fieldValue,
                            'label' => $fieldValue
                        ];
                    }
                } catch (Exception $e) {
                    error_log("Error getting fields for table $tableName: " . $e->getMessage());
                    continue;
                }
            }

            // Build optgroups
            foreach ($allFields as $tableName => $tableFields) {
                $fieldsHtml .= '<optgroup label="' . htmlspecialchars($tableName) . '">';
                foreach ($tableFields as $field) {
                    $fieldsHtml .= '<option value="' . htmlspecialchars($field['value']) . '">' . 
                                  htmlspecialchars($field['label']) . '</option>';
                }
                $fieldsHtml .= '</optgroup>';
            }

            echo $fieldsHtml;

        } catch (Exception $e) {
            echo '<option value="">Error loading fields: ' . $e->getMessage() . '</option>';
        }
    }

    private static function extractVisualParams($postData)
    {
        $visualParams = [];
        
        // Extract all the visual parameters from POST data
        $arrayFields = [
            'fields', 'agg_field', 'agg_func', 'agg_alias', 
            'jointype', 'jointable', 'joinfield', 'joinfieldp', 
            'ftype', 'fname', 'fvalue', 'groupfields', 'orderfields', 
            'htype', 'hfname', 'hfvalue'
        ];
        
        foreach ($arrayFields as $field) {
            $visualParams[$field] = isset($postData[$field]) ? (array) $postData[$field] : [];
        }

        // Handle non-array fields
        if (isset($postData['chkDescending'])) {
            $visualParams['chkDescending'] = 'on';
        }
        
        if (isset($postData['limitStart'])) {
            $visualParams['limitStart'] = $postData['limitStart'];
        }
        
        if (isset($postData['limitNumRows'])) {
            $visualParams['limitNumRows'] = $postData['limitNumRows'];
        }

        return $visualParams;
    }

    private static function runQueryWithView($query, $fields, $printArray, $visual_query_params = null, $running_saved_query_name = null, $connection_name = null, $apply_limit = false, $source_connection_id = null)
    {
        // Use the existing Table class method
        return Table::runQueryWithView($query, $fields, $printArray, $visual_query_params, $running_saved_query_name, $connection_name, $apply_limit, $source_connection_id);
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
