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
            $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fieldOptions = getOptions(array_column($fields, 'Field'));
        } catch (PDOException $e) {
            $fields = [];
            error_log("Table structure error: ".$e->getMessage());
        }

        $_SESSION['tableData'] = array();

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

        // store table fields/columns + data rows in session for exporting later
        $_SESSION['tableData'] = array_merge($fields, $records);


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

        // for custom query
        if ($query) {
            self::runQueryWithView($query, $fields, $printArray);

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
                        $query .= $_POST['jointable'][$key];
                        $query .= ' ON ' . $primaryTable . '.`' . $_POST['joinfieldp'][$key] . '` = ' . $_POST['jointable'][$key] . '.`' . $_POST['joinfield'][$key] . '`';
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

            // run query and render view
            self::runQueryWithView($query, $fields, $printArray);
        }

    }

    private static function runQueryWithView($query, $fields, $printArray)
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
              'fields' => getOptions($fields),
              'query' => SqlFormatter::format($query),
              'printArray' => $printArray,
              'timetaken' => $exec_time_row[0][1]
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