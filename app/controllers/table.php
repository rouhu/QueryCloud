<?php

set_time_limit(3600);

class Table
{
    private static $icon = 'fa fa-table';

    public static function index($name)
    {

        $table = Flight::get('lastSegment',$name);
    
        try {
            // Get table structure
            $stmt = Flight::get('db')->query("DESCRIBE `$table`");
            $table_fields_data = $stmt->fetchAll(PDO::FETCH_ASSOC); // Renamed to avoid conflict with $fields later
            // Pass the table name to getOptions to generate qualified field names
            $fieldOptions = getOptions(array_column($table_fields_data, 'Field'), false, $table);
        } catch (PDOException $e) {
            $table_fields_data = []; // Renamed
            $fieldOptions = ''; // Initialize $fieldOptions in case of error
            error_log("Table structure error: ".$e->getMessage());
        }

        // This $fields array is used for $_SESSION['tableData'], keep it as simple field names
        $fields_for_session = array();
        // The $columns variable seems to be fetching the same DESCRIBE info again.
        // We can reuse $table_fields_data if it's already fetched and valid.
        // If $table_fields_data is empty due to an earlier error, this will also be empty.
        foreach ($table_fields_data as $values) {
            if (isset($values['Field'])) {
                $fields_for_session[] = $values['Field'];
            }
        }
        $_SESSION['tableData'] = array(); // Initialize

        // Checks whether or not user is logged in
        self::checkLogin();

        // enable query profiling
        Flight::get('db')->query('SET profiling = 1;');

        // get specified table data as array
        $records = ORM::for_table(Flight::get('lastSegment'))->find_array();
        //pretty_print($records);

        // find out time above query was ran for
        $exec_time_result = Flight::get('db')->query(
           'SELECT query_id, SUM(duration) FROM information_schema.profiling GROUP BY query_id ORDER BY query_id DESC LIMIT 1;'
        );

        $exec_time_row = $exec_time_result->fetchAll(PDO::FETCH_NUM);

        // $fields_for_session is already populated from $table_fields_data earlier
        // No need for the second DESCRIBE query for $columns or re-populating $fields.

        //pretty_print($fields_for_session);

        // store table fields/columns + data rows in session for exporting later
        // Ensure $fields_for_session is used here instead of an undefined $fields
        $_SESSION['tableData'] = array_merge($fields_for_session, $records);


        $fieldTypes = array();

        /*
        foreach ($columns as $values) {
            if (isset($values['Field'])) {
                $fieldTypes['type'][] = $values['Type'];
                $fieldTypes['primary'][] = $values['Key'];
            }
        }
        //pretty_print($fieldTypes, false);
        */

        $records = Presenter::listTableData($records, $fieldTypes);

        // Generate tablesOptionsHTML directly for this view rendering
        $_db = Flight::get('db');
        $tablesOptionsHtmlForView = '<option value="">Error loading tables (Controller Default)</option>'; // Default
        try {
            $allTablesStmt = $_db->query('SHOW TABLES');
            $allTablesResult = $allTablesStmt->fetchAll(PDO::FETCH_NUM);
            $tableNamesForOptions = [];
            foreach ($allTablesResult as $row) {
                $tableNamesForOptions[] = $row[0];
            }
            $currentTablesOptions = getOptions($tableNamesForOptions, true); // true for "Choose Table"
            if (!empty(trim($currentTablesOptions)) && strpos($currentTablesOptions, '<option') !== false) {
                $tablesOptionsHtmlForView = $currentTablesOptions;
            } else { // Handles case where getOptions might return only the default or empty
                $tablesOptionsHtmlForView = '<option value="">No tables found in DB</option>';
            }
        } catch (PDOException $e) {
            error_log("Error fetching table list for view_tables_options_html in Table::index: " . $e->getMessage());
            // $tablesOptionsHtmlForView remains as "Error loading tables..."
        }

        Flight::render(
           'table',
           array(
            'fields' => $fieldOptions, // These are qualified field names for the current $table
              'title' => Flight::get('lastSegment'), // This is $table
              'icon' => self::$icon,
              'table_data' => $records,
              'query' => SqlFormatter::format(ORM::get_last_query(ORM::DEFAULT_CONNECTION)),
              'timetaken' => $exec_time_row[0][1],
              'view_tables_options_html' => $tablesOptionsHtmlForView
           )
        );
    }

    /**
     * Runs Custom or Visual SQL query
     */
    public static function runquery()
    {
        $printArray = '';
        $query = $_POST['cquery'];

        if (isset($_POST['printArray'])) {
            $printArray = pretty_print($_POST, false, true);
        }

        // table columns
        $stmt = Flight::get('db')->query("DESCRIBE " . Flight::get('lastSegment'));
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        //pretty_print($columns);

        $fields = array();
        foreach ($columns as $values) {
            if (isset($values['Field'])) {
                $fields[] = $values['Field'];
            }
        }
        //pretty_print($fields);

        $running_saved_query_name = $_POST['running_saved_query_name'] ?? null;

        // for custom query
        if ($query) {
            self::runQueryWithView($query, $fields, $printArray, null, $running_saved_query_name);

        } // for visual query
        else {
            // Use $primaryTableName which is Flight::get('lastSegment') in this context
            $primaryTableName = Flight::get('lastSegment');
            $query = self::generateSqlFromVisualParams($_POST, $primaryTableName);

            // Collect visual parameters if this was a visual query build
            // Note: $_POST is passed directly to generateSqlFromVisualParams,
            // so $visual_params_for_view will be a subset if generateSqlFromVisualParams filters.
            // For consistency, it's better if generateSqlFromVisualParams takes a clean array
            // and we construct $visual_params_for_view based on expected keys.
            $visual_params_for_view = [];
            // These are the POST keys used by the visual builder UI in modals.php and processed in Table::runquery()
            $visual_param_keys = [
                'fields', 'jointype', 'jointable', 'joinfield', 'joinfieldp',
                'fname', 'fvalue', 'ftype',
                'groupfields', 'orderfields', 'chkDescending',
                'limitStart', 'limitNumRows',
                'agg_field', 'agg_func', 'agg_alias',
                'hfname', 'hfvalue', 'htype',
                // 'primaryTable' is not from POST here but added by JS for saving.
                // We'll ensure it's part of visual_params if available from POST (though it won't be for direct VQB run)
                // or use $primaryTableName for context.
            ];
            foreach($visual_param_keys as $key) {
                if (isset($_POST[$key])) {
                    $visual_params_for_view[$key] = $_POST[$key];
                }
            }
            // Add primaryTable to visual_params_for_view for consistency if it's used for display or later saving from this view
            $visual_params_for_view['primaryTable'] = $primaryTableName;


            // run query and render view

            // If running from VQB after editing a saved query, try to get its name
            // $running_saved_query_name is already initialized from $_POST['running_saved_query_name']
            // but that field is not typically part of the VQB form submission.
            // The VQB form submits 'visual_query_id_edit'.
            $query_id_from_vqb_edit = $_POST['visual_query_id_edit'] ?? null;
            $current_running_saved_query_name = $running_saved_query_name; // Preserve if it came from another source

            if (!empty($query_id_from_vqb_edit) && is_numeric($query_id_from_vqb_edit)) {
                // If VQB was editing a saved query, its ID is visual_query_id_edit.
                // We need to fetch its name to provide context to runQueryWithView.
                $saved_query_for_name = ORM::for_table('saved_queries')->find_one($query_id_from_vqb_edit);
                if ($saved_query_for_name) {
                    $current_running_saved_query_name = $saved_query_for_name->query_name;
                    // Note: runQueryWithView will use this name to re-fetch the ID.
                    // Alternatively, we could pass the ID directly if runQueryWithView is modified,
                    // but using the name aligns with its current design.
                }
            }

            self::runQueryWithView($query, $fields, $printArray, $visual_params_for_view, $current_running_saved_query_name);
        }

    }

    public static function generateSqlFromVisualParams(array $params, string $primaryTableName): string
    {
        $select_parts = [];
        $query = '';

        // Process non-aggregated fields
        if (!empty($params['fields'])) {
            $duplicateNameFields = [];
            foreach ($params['fields'] as $value) {
                if ($value) {
                    $baseValue = $value;
                    if (in_array($baseValue, $duplicateNameFields)) {
                        $fieldArray = explode('.', $baseValue);
                        if (count($fieldArray) === 2) {
                            $value = $baseValue . ' AS ' . $fieldArray[0] . '_' . $fieldArray[1];
                        }
                    }
                    $duplicateNameFields[] = $baseValue;
                    $select_parts[] = $value;
                }
            }
        }

        // Process aggregated fields
        if (!empty($params['agg_field']) && is_array($params['agg_field'])) {
            foreach ($params['agg_field'] as $key => $field_name) {
                if (empty($field_name) || empty($params['agg_func'][$key])) {
                    continue;
                }

                $func = strtoupper($params['agg_func'][$key]);
                $alias = $params['agg_alias'][$key] ?? '';
                $allowed_funcs = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];
                if (!in_array($func, $allowed_funcs)) {
                    continue;
                }

                $field_parts = explode('.', $field_name, 2);
                $quoted_field_name = $field_name;
                if (count($field_parts) == 2) {
                    $quoted_field_name = "`" . str_replace("`", "``", $field_parts[0]) . "`.`" . str_replace("`", "``", $field_parts[1]) . "`";
                } else {
                    $quoted_field_name = "`" . str_replace("`", "``", $field_name) . "`";
                }

                $agg_string = $func . '(' . $quoted_field_name . ')';

                if (!empty($alias)) {
                    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
                    if (!empty($alias)) {
                        $agg_string .= ' AS `' . $alias . '`';
                    } else {
                        $default_alias = strtolower($func . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $field_parts[1] ?? $field_name));
                        $agg_string .= ' AS `' . $default_alias . '`';
                    }
                } else {
                    $default_alias = strtolower($func . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $field_parts[1] ?? $field_name));
                    $agg_string .= ' AS `' . $default_alias . '`';
                }
                $select_parts[] = $agg_string;
            }
        }

        if (empty($select_parts)) {
            $query = 'SELECT *';
        } else {
            $query = 'SELECT ' . implode(', ', $select_parts);
        }

        $query .= ' FROM `' . str_replace("`", "``", $primaryTableName) . '`';

        // Joins
        if (!empty($params['jointype']) && is_array($params['jointype'])) {
            foreach ($params['jointype'] as $key => $value) {
                if (!$value || empty($params['jointable'][$key])) { // Ensure join table is also present
                    continue;
                }
                $query .= ' ' . $value . ' '; // $value is jointype
                $query .= '`' . str_replace('`', '``', $params['jointable'][$key]) . '`';

                if (!empty($params['joinfieldp'][$key]) && !empty($params['joinfield'][$key])) {
                    $primary_join_field = $params['joinfieldp'][$key];
                    if (strpos($primary_join_field, '.') !== false) {
                        list($pt_table, $pt_col) = explode('.', $primary_join_field, 2);
                        $primary_join_field_sql = '`' . str_replace('`', '``', $pt_table) . '`.`' . str_replace('`', '``', $pt_col) . '`';
                    } else {
                        $primary_join_field_sql = '`' . str_replace('`', '``', $primaryTableName) . '`.`' . str_replace('`', '``', $primary_join_field) . '`';
                    }

                    $secondary_join_field = $params['joinfield'][$key];
                    // Note: joinfield from VQB is usually not qualified, it's a field from jointable[$key]
                     $secondary_join_field_sql = '`' . str_replace('`', '``', $params['jointable'][$key]) . '`.`' . str_replace('`', '``', $secondary_join_field) . '`';

                    $query .= ' ON ' . $primary_join_field_sql . ' = ' . $secondary_join_field_sql;
                }
            }
        }

        // WHERE clause
        if (!empty($params['fname']) && is_array($params['fname'])) {
            $where_conditions = [];
            foreach ($params['fname'] as $key => $value) {
                if (!empty($params['fvalue'][$key])) { // Ensure there's a value for the condition
                    $condition = $value . $params['fvalue'][$key]; // $value is field name, fvalue has operator + value
                    if (isset($params['ftype'][$key]) && $key > 0 && count($where_conditions) > 0) { // ftype is for condition linking (AND/OR)
                         // ftype is actually for the *next* condition, so it should be used for $params['ftype'][$key] to link to previous
                         // This part of original logic might be slightly off. For safety, let's assume ftype[$key] links the current to previous.
                         // A more robust way is to ensure ftype array is correctly aligned or build conditions step-by-step.
                         // The original code uses $params['ftype'][$key + 1] which is problematic if $key is the last one.
                         // Let's assume $params['ftype'][$key] links the condition at $key to the one at $key-1.
                         // The first condition won't have a preceding ftype.
                        if (isset($params['ftype'][$key]) && !empty($where_conditions) ) { // Check if ftype for current index exists
                            $where_conditions[] = ($params['ftype'][$key] ?? 'AND') . ' ' . $condition;
                        } else {
                            $where_conditions[] = $condition;
                        }
                    } else {
                         $where_conditions[] = $condition;
                    }
                }
            }
            if (!empty($where_conditions)) {
                 // Rebuild WHERE clause carefully
                $query .= ' WHERE ';
                $first_where = true;
                foreach ($params['fname'] as $key => $value) {
                    if (!empty($params['fvalue'][$key])) {
                        if (!$first_where && isset($params['ftype'][$key])) { // ftype links current to previous
                            $query .= ' ' . ($params['ftype'][$key] ?? 'AND') . ' ';
                        }

                        // Properly quote the field name ($value is fname)
                        $quoted_field_name = $value;
                        if (strpos($value, '.') !== false) {
                            list($table_part, $column_part) = explode('.', $value, 2);
                            $quoted_field_name = '`' . str_replace('`', '``', $table_part) . '`.`' . str_replace('`', '``', $column_part) . '`';
                        } else {
                            // If no table part, assume it's a column of the primary table or an alias that's already correctly named.
                            // VQB usually provides qualified names (table.column) for fname.
                            $quoted_field_name = '`' . str_replace('`', '``', $value) . '`';
                        }

                        // Append quoted field name, a space, and then the fvalue (which should contain operator + value)
                        $query .= $quoted_field_name . ' ' . $params['fvalue'][$key];
                        $first_where = false;
                    }
                }
            }
        }

        // GROUP BY fields
        if (!empty($params['groupfields']) && is_array($params['groupfields']) && count(array_filter($params['groupfields'])) > 0) {
            $query .= ' GROUP BY ';
            $query .= implode(', ', array_filter($params['groupfields']));
        }

        // HAVING clause
        if (!empty($params['hfname']) && is_array($params['hfname'])) {
            $having_conditions_parts = [];
            foreach ($params['hfname'] as $key => $hfname_val) {
                if (!empty($hfname_val) && isset($params['hfvalue'][$key]) && $params['hfvalue'][$key] !== '') {
                    $hcondition_string = '';
                    if (count($having_conditions_parts) > 0 && isset($params['htype'][$key])) { // htype links current to previous
                        $hcondition_string .= ' ' . ($params['htype'][$key] ?? 'AND') . ' ';
                    }

                    if (strpos($hfname_val, '.') === false) {
                        $hcondition_string .= '`' . str_replace("`", "``", $hfname_val) . '`';
                    } else {
                        $hfield_parts = explode('.', $hfname_val, 2);
                        if (count($hfield_parts) == 2) {
                            $hcondition_string .= "`" . str_replace("`", "``", $hfield_parts[0]) . "`.`" . str_replace("`", "``", $hfield_parts[1]) . "`";
                        } else {
                            $hcondition_string .= "`" . str_replace("`", "``", $hfname_val) . "`";
                        }
                    }
                    $hcondition_string .= ' ' . $params['hfvalue'][$key];
                    $having_conditions_parts[] = $hcondition_string;
                }
            }
            if (!empty($having_conditions_parts)) {
                $query .= ' HAVING ' . implode(' ', $having_conditions_parts);
            }
        }

        // ORDER BY fields
        if (!empty($params['orderfields']) && is_array($params['orderfields']) && count(array_filter($params['orderfields'])) > 0) {
            $query .= ' ORDER BY ';
            $query .= implode(', ', array_filter($params['orderfields']));
            if (isset($params['chkDescending']) && ($params['chkDescending'] === 'on' || $params['chkDescending'] === true)) {
                $query .= ' DESC ';
            }
        }

        // LIMIT clause
        if (!empty($params['limitStart']) && is_numeric($params['limitStart'])) {
            $query .= ' LIMIT ' . (int)$params['limitStart'];
            if (!empty($params['limitNumRows']) && is_numeric($params['limitNumRows'])) {
                $query .= ', ' . (int)$params['limitNumRows'];
            }
        }

        return self::fixQuery($query);
    }


    private static function runQueryWithView($query, $fields, $printArray, $visual_query_params = null, $running_saved_query_name = null)
    {
        $_SESSION['tableData'] = array();

        $exec_time_row = array();
        $records = '';

        // Ensure tablesOptions is available for the view context by generating it directly
        $_db = Flight::get('db');
        $tablesOptionsHtmlForView = '<option value="">Error loading tables (Controller Default)</option>'; // Default
        try {
            $allTablesStmt = $_db->query('SHOW TABLES');
            $allTablesResult = $allTablesStmt->fetchAll(PDO::FETCH_NUM);
            $tableNamesForOptions = [];
            foreach ($allTablesResult as $row) {
                $tableNamesForOptions[] = $row[0];
            }
            $currentTablesOptions = getOptions($tableNamesForOptions, true); // true for "Choose Table"
            if (!empty(trim($currentTablesOptions)) && strpos($currentTablesOptions, '<option') !== false) {
                $tablesOptionsHtmlForView = $currentTablesOptions;
            } else { // Handles case where getOptions might return only the default or empty
                $tablesOptionsHtmlForView = '<option value="">No tables found in DB</option>';
            }
        } catch (PDOException $e) {
            error_log("Error fetching table list for view_tables_options_html in runQueryWithView: " . $e->getMessage());
            // $tablesOptionsHtmlForView remains as "Error loading tables..."
        }

        try {
            // turn on query profiling
            Flight::get('db')->query('SET profiling = 1;');

            $stmt = Flight::get('db')->query($query);

            // find out time above query was ran for
            $exec_time_result = Flight::get('db')->query(
               'SELECT query_id, SUM(duration) FROM information_schema.profiling GROUP BY query_id ORDER BY query_id DESC LIMIT 1;'
            );

            $exec_time_row = $exec_time_result->fetchAll(PDO::FETCH_NUM);

            // run query and fetch array
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // store table fields/columns + data rows in session for exporting later
//           $_SESSION['tableData'] = array_merge($fields, $data);
// With proper header + data structure:
// Extract column names from DESCRIBE result
//print_r($data);
            if (!empty($data) && isset($data[0])) {
                $original_header = array_keys($data[0]); // <-- Keep the original headers
                $display_header = $original_header; // By default, display header is the original

                // --- Apply Table Formatting ---
                $query_id_for_formatting_lookup = null;
                if (isset($_POST['executed_query_id']) && !empty($_POST['executed_query_id'])) {
                     $query_id_for_formatting_lookup = $_POST['executed_query_id'];
                } else if ($running_saved_query_name) {
                    $sq_details = ORM::for_table('saved_queries')->where('query_name', $running_saved_query_name)->find_one();
                    if ($sq_details) $query_id_for_formatting_lookup = $sq_details->id;
                }

                if ($query_id_for_formatting_lookup) {
                    $query_with_formatting = ORM::for_table('saved_queries')->find_one($query_id_for_formatting_lookup);
                    if ($query_with_formatting && !empty($query_with_formatting->table_formatting)) {
                        try {
                            $formatting_rules = json_decode($query_with_formatting->table_formatting, true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($formatting_rules['column_titles'])) {
                                $new_display_header = [];
                                foreach ($original_header as $original_col_name) {
                                    $new_display_header[] = (!empty($formatting_rules['column_titles'][$original_col_name])) ? $formatting_rules['column_titles'][$original_col_name] : $original_col_name;
                                }
                                $display_header = $new_display_header; // Set the display header to the formatted one
                            }
                        } catch (Exception $e) {
                            error_log("Error decoding table formatting JSON: " . $e->getMessage());
                        }
                    }
                }
                // --- End Apply Table Formatting ---

                $_SESSION['tableData'] = array($display_header);  // Use display_header for session/export data

                foreach ($data as $row) {
                    $_SESSION['tableData'][] = array_values($row);
                }
            } else {
                // Handle empty result set
                $original_header = [];
                $display_header = [];
                $_SESSION['tableData'] = array();
                $data = [];
            }

            // Pass both original and display headers to Presenter
            $records = Presenter::listTableData($data, [], $display_header, $original_header);


        } catch (PDOException $e) {
            setFlashMessage('Error: ' . $e->getMessage());
            $header = []; // Ensure header is defined for Presenter in error case
            // Ensure variables are set for the view in case of an error
            $data = []; // Set $data to empty array
            $_SESSION['tableData'] = array(); // Clear or set session data appropriately
            $records = Presenter::listTableData($data); // Presenter should handle empty data
            // $exec_time_row might also need a default if error happens before its calculation
            if (empty($exec_time_row)) { // Check if it was not set due to early error
                $exec_time_row = [[null, '0.00']]; // Provide a default structure
            }
        }

        $view_data = array(
            'title' => Flight::get('lastSegment'),
            'icon' => self::$icon,
            'table_data' => $records,
            'fields' => getOptions($fields, false, Flight::get('lastSegment')), // Pass table name for field dropdowns
            'query' => SqlFormatter::format($query),
            'printArray' => $printArray,
            'timetaken' => $exec_time_row[0][1] ?? '0.00', // Ensure timetaken has a default
            'view_tables_options_html' => $tablesOptionsHtmlForView // Use the locally generated one
        );

        // If a saved query was run, fetch its details to pass to the view for the "Edit Query" button
        if ($running_saved_query_name) {
            $saved_query_details = ORM::for_table('saved_queries')
                                    ->where('query_name', $running_saved_query_name)
                                    // ->where('user_id', $_SESSION['user_id']) // If multi-user, scope by user
                                    ->find_one();

            if ($saved_query_details) {
                $view_data['executed_query_id'] = $saved_query_details->id;
                $view_data['executed_query_name'] = $saved_query_details->query_name;
                $view_data['executed_query_was_saved_visual'] = (bool)$saved_query_details->is_visual_query;
                // If it was a saved visual query, its visual_params should be used for editing,
                // overriding any ad-hoc VQB params that might have been used to run it (though less likely for "Run Saved Query")
                if ($saved_query_details->is_visual_query && $saved_query_details->visual_params) {
                    $view_data['visual_params_json'] = $saved_query_details->visual_params;
                } else if ($visual_query_params){ // Ad-hoc VQB query was run (not a saved one)
                     $view_data['visual_params_json'] = json_encode($visual_query_params);
                } else {
                     $view_data['visual_params_json'] = ''; // No visual params
                }
            } else {
                 // If running_saved_query_name was set but not found (edge case), clear related fields
                $view_data['executed_query_id'] = '';
                $view_data['executed_query_name'] = $running_saved_query_name; // Keep name for display
                $view_data['executed_query_was_saved_visual'] = false;
                $view_data['visual_params_json'] = $visual_query_params ? json_encode($visual_query_params) : ''; // Fallback to current VQB params
            }
        } else if ($visual_query_params) { // An ad-hoc VQB query (not saved yet)
            $view_data['visual_params_json'] = json_encode($visual_query_params);
            $view_data['executed_query_id'] = '';
            $view_data['executed_query_name'] = ''; // No name for ad-hoc query yet
            $view_data['executed_query_was_saved_visual'] = true; // It's visual by nature of being from VQB
        } else { // A custom SQL query (not VQB, not saved)
            $view_data['visual_params_json'] = '';
            $view_data['executed_query_id'] = '';
            $view_data['executed_query_name'] = '';
            $view_data['executed_query_was_saved_visual'] = false;
        }

        // This was for the main list of tables for join table dropdowns, now handled by masterTableOptionsHTML
        // $view_data['view_tables_options_html'] = Flight::get('tablesOptions'); // This might be stale or incorrect context

        Flight::render('table', $view_data);
    }

    /**
     * Fix invalid queries
     *
     * @param $query
     * @return mixed|string
     */
    private static function fixQuery($query)
    {
        $query = str_replace('SELECT FROM', 'SELECT * FROM', $query);
        $query = str_replace('SELECT  FROM', 'SELECT * FROM', $query);

        $query = rtrim($query, ' OR ');
        $query = rtrim($query, ' OR');
        $query = rtrim($query, ' AND ');
        $query = rtrim($query, ' AND');
        $query = rtrim($query, ' WHERE');
        $query = rtrim($query, ' WHERE ');
        $query = rtrim($query, ',,');
        $query = rtrim($query, ',');
        $query = rtrim($query, ', ');
        $query = rtrim($query, ',  ');
        $query = rtrim($query, ' INNER');
        $query = rtrim($query, ' INNER JOI');
        $query = rtrim($query, ' INNER JOIN');
        $query = rtrim($query, ' LEFT');
        $query = rtrim($query, ' LEFT JOIN');
        $query = rtrim($query, ' RIGH');
        $query = rtrim($query, ' RIGHT JOIN');
        $query = rtrim($query, ' FU');
        $query = rtrim($query, ' FULL');
        $query = rtrim($query, ' FULL JOIN');

        return $query;
    }

    private static function checkLogin()
    {
        if (! isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}