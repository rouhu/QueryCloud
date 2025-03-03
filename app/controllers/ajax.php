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
}