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

        Flight::render(
           'table',
           array(
            'fields' => $fieldOptions,
              'title' => Flight::get('lastSegment'),
              'icon' => self::$icon,
              'table_data' => $records,
              //'fields' => $fields,
              //'fields' => getOptions($fields),
              'query' => SqlFormatter::format(ORM::get_last_query(ORM::DEFAULT_CONNECTION)),
              'timetaken' => $exec_time_row[0][1]
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

            $select_parts = [];

            // Process non-aggregated fields
            if (!empty($_POST['fields'])) {
                $duplicateNameFields = []; // To handle potential duplicate field names if selected multiple times (though less likely with table.field format)
                foreach ($_POST['fields'] as $value) {
                    if ($value) {
                        // Basic protection for field names, assuming 'table.field' format
                        // More robust quoting might be needed if arbitrary values are allowed.
                        // The current UI generates table.field, so direct usage is mostly safe.
                        // For aliasing to avoid conflicts if the same simple field is selected multiple times (e.g. from different joins before full qualification was implemented)
                        // This logic might be less critical if all fields are fully qualified (table.field)
                        $baseValue = $value;
                        if (in_array($baseValue, $duplicateNameFields)) {
                            $fieldArray = explode('.', $baseValue);
                            if (count($fieldArray) === 2) {
                                $value = $baseValue . ' AS ' . $fieldArray[0] . '_' . $fieldArray[1];
                            }
                            // If it's already aliased or not in table.field format, use as is or consider more complex aliasing
                        }
                        $duplicateNameFields[] = $baseValue;
                        $select_parts[] = $value;
                    }
                }
            }

            // Process aggregated fields
            if (!empty($_POST['agg_field']) && is_array($_POST['agg_field'])) {
                foreach ($_POST['agg_field'] as $key => $field_name) {
                    if (empty($field_name) || empty($_POST['agg_func'][$key])) {
                        continue; // Skip if field name or function is missing
                    }

                    $func = strtoupper($_POST['agg_func'][$key]);
                    $alias = $_POST['agg_alias'][$key] ?? '';

                    // Basic validation for aggregate functions
                    $allowed_funcs = ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'];
                    if (!in_array($func, $allowed_funcs)) {
                        // Potentially log an error or skip
                        continue;
                    }

                    // Field name is expected to be table.field - quote it carefully if needed
                    // For now, assuming $field_name is like 'tablename.fieldname'
                    // Proper quoting: `table`.`field`
                    $field_parts = explode('.', $field_name, 2);
                    $quoted_field_name = $field_name; // Default to as-is if not 'table.field'
                    if (count($field_parts) == 2) {
                         // Basic quoting, can be improved for robustness
                        $quoted_field_name = "`" . str_replace("`", "``", $field_parts[0]) . "`.`" . str_replace("`", "``", $field_parts[1]) . "`";
                    } else {
                        // If it's a single word, assume it's a field from the primary table or already quoted
                        $quoted_field_name = "`" . str_replace("`", "``", $field_name) . "`";
                    }


                    $agg_string = $func . '(' . $quoted_field_name . ')';

                    if (!empty($alias)) {
                        // Sanitize alias: basic, allow alphanumeric and underscores
                        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
                        if (!empty($alias)) {
                            $agg_string .= ' AS `' . $alias . '`';
                        } else { // if alias became empty after sanitizing, generate default
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

            $query .= ' FROM `' . Flight::get('lastSegment') . '`';

            // find out which tables to JOIN
            if (array_key_exists('jointype', $_POST) && count($_POST['jointype'])) {
                $counter = 0;
                foreach ($_POST['jointype'] as $key => $value) {
                    $counter ++;

                    if (! $value) {
                        continue;
                    }

                    $primaryTable = Flight::get('lastSegment');
                    $query .= ' ' . $value . ' ';

                    // build ON table join clause
                    if ($_POST['jointable'][$key]) {
                        $query .= '`' . str_replace('`', '``', $_POST['jointable'][$key]) . '`'; // Quote the joined table name

                        // Primary table's join field
                        $primary_join_field = $_POST['joinfieldp'][$key];
                        if (strpos($primary_join_field, '.') !== false) {
                            // Field is already qualified (e.g., table.column)
                            list($pt_table, $pt_col) = explode('.', $primary_join_field, 2);
                            $primary_join_field_sql = '`' . str_replace('`', '``', $pt_table) . '`.`' . str_replace('`', '``', $pt_col) . '`';
                        } else {
                            // Field is not qualified, prepend primary table name
                            $primary_join_field_sql = '`' . str_replace('`', '``', $primaryTable) . '`.`' . str_replace('`', '``', $primary_join_field) . '`';
                        }

                        // Joined table's join field
                        $secondary_join_field = $_POST['joinfield'][$key];
                        if (strpos($secondary_join_field, '.') !== false) {
                            // Field is already qualified (e.g., table.column)
                            list($st_table, $st_col) = explode('.', $secondary_join_field, 2);
                            $secondary_join_field_sql = '`' . str_replace('`', '``', $st_table) . '`.`' . str_replace('`', '``', $st_col) . '`';
                        } else {
                            // Field is not qualified, prepend joined table name
                            $secondary_join_field_sql = '`' . str_replace('`', '``', $_POST['jointable'][$key]) . '`.`' . str_replace('`', '``', $secondary_join_field) . '`';
                        }

                        $query .= ' ON ' . $primary_join_field_sql . ' = ' . $secondary_join_field_sql;
                    }
                }
            }

            // find out which fields/conditions to put in in WHERE clause
            if (array_key_exists('fname', $_POST) && count($_POST['fname'])) {
                $total = count($_POST['fname']);
                $query .= ' WHERE ';

                $counter = 0;
                foreach ($_POST['fname'] as $key => $value) {
                    $counter ++;

                    if ($_POST['fvalue'][$key]) {
                        if ($total === $counter) {
                            $query .= $value . $_POST['fvalue'][$key];
                        } else {
                            $query .= $value . $_POST['fvalue'][$key] . ' ' . $_POST['ftype'][$key + 1] . ' ';
                        }
                    }

                }
            }

            // find out GROUP BY fields
            if (array_key_exists('groupfields', $_POST) && count($_POST['groupfields'])) {
                $query .= ' GROUP BY ';
                $query .= implode(', ', $_POST['groupfields']);
            }

            // find out which fields/conditions to put in HAVING clause
            if (!empty($_POST['hfname']) && is_array($_POST['hfname'])) {
                $having_conditions = [];
                $first_having_condition = true;
                foreach ($_POST['hfname'] as $key => $hfname_val) {
                    if (!empty($hfname_val) && isset($_POST['hfvalue'][$key]) && $_POST['hfvalue'][$key] !== '') {
                        $hcondition_string = '';
                        if (!$first_having_condition && isset($_POST['htype'][$key])) {
                            $hcondition_string .= ' ' . $_POST['htype'][$key] . ' ';
                        }
                        $first_having_condition = false;

                        // Quote field name/alias for HAVING. Aliases should not be table.field
                        // If hfname_val contains '.', it's likely a table.field from GROUP BY, otherwise an alias.
                        // For safety, we'll assume aliases do not contain '.' and fields from GROUP BY might.
                        // SQL standard allows aliases from SELECT to be used in HAVING.
                        // If it's an alias, it shouldn't be `table`.`alias`. Just `alias`.
                        if (strpos($hfname_val, '.') === false) {
                             // Likely an alias, quote it simply
                            $hcondition_string .= '`' . str_replace("`", "``", $hfname_val) . '`';
                        } else {
                            // Likely a table.field from GROUP BY. Quote accordingly.
                            $hfield_parts = explode('.', $hfname_val, 2);
                            if (count($hfield_parts) == 2) {
                                $hcondition_string .= "`" . str_replace("`", "``", $hfield_parts[0]) . "`.`" . str_replace("`", "``", $hfield_parts[1]) . "`";
                            } else { // Fallback for non-standard field name
                                $hcondition_string .= "`" . str_replace("`", "``", $hfname_val) . "`";
                            }
                        }

                        // The hfvalue is expected to contain operator and value, e.g., "> 100" or "= 'text'"
                        // This part needs to be robust. For now, direct concatenation.
                        // TODO: Separate operator and value in UI and process them safely here.
                        $hcondition_string .= ' ' . $_POST['hfvalue'][$key];
                        $having_conditions[] = $hcondition_string;
                    }
                }
                if (!empty($having_conditions)) {
                    $query .= ' HAVING ' . implode('', $having_conditions); // Conditions already include AND/OR
                }
            }


            // find out ORDER BY fields
            if (array_key_exists('orderfields', $_POST) && count($_POST['orderfields'])) {
                $query .= ' ORDER BY ';
                $query .= implode(', ', $_POST['orderfields']);

                if (array_key_exists('chkDescending', $_POST) && $_POST['chkDescending']) {
                    $query .= ' DESC ';
                }
            }

            // find out LIMIT clause details
            if (array_key_exists('limitStart', $_POST) && $_POST['limitStart']) {
                $query .= ' LIMIT ' . $_POST['limitStart'];

                if (array_key_exists('limitNumRows', $_POST) && $_POST['limitNumRows']) {
                    $query .= ', ' . $_POST['limitNumRows'];
                }
            }

            $query = self::fixQuery($query);

            // Collect visual parameters if this was a visual query build
            $visual_params_for_view = [];
            // These are the POST keys used by the visual builder UI in modals.php and processed in Table::runquery()
            $visual_param_keys = [
                'fields', 'jointype', 'jointable', 'joinfield', 'joinfieldp',
                'fname', 'fvalue', 'ftype',
                'groupfields', 'orderfields', 'chkDescending',
                'limitStart', 'limitNumRows',
                'agg_field', 'agg_func', 'agg_alias',
                'hfname', 'hfvalue', 'htype'
            ];
            foreach($visual_param_keys as $key) {
                if (isset($_POST[$key])) {
                    $visual_params_for_view[$key] = $_POST[$key];
                }
            }

            // run query and render view
            // Pass $running_saved_query_name which would be null here as this is VQB path
            self::runQueryWithView($query, $fields, $printArray, $visual_params_for_view, $running_saved_query_name);
        }

    }

    private static function runQueryWithView($query, $fields, $printArray, $visual_query_params = null, $running_saved_query_name = null)
    {
        $_SESSION['tableData'] = array();

        $exec_time_row = array();
        $records = '';

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
$header = array_keys($data[0]);
$_SESSION['tableData'] = array($header);  // Header row as array

foreach ($data as $row) {
    $_SESSION['tableData'][] = array_values($row);  // Data rows as arrays
}

            $records = Presenter::listTableData($data);

        } catch (PDOException $e) {
            setFlashMessage('Error: ' . $e->getMessage());
        }

        Flight::render(
           'table',
           array(
              'title' => Flight::get('lastSegment'),
              'icon' => self::$icon,
              'table_data' => $records,
              'fields' => getOptions($fields, false, Flight::get('lastSegment')), // Pass table name
              'query' => SqlFormatter::format($query),
              'printArray' => $printArray,
              'timetaken' => $exec_time_row[0][1],
              'visual_params_json' => $visual_query_params ? json_encode($visual_query_params) : '',
              'executed_query_name' => $running_saved_query_name // Pass the saved query name to the view
           )
        );
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