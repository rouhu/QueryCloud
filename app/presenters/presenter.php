<?php

class Presenter
{
    public static function listTables(array $array)
    {
        $html = '';

        $base = Flight::get('base');

        $counter = 0;
        foreach ($array as $arrayitem) {
            $counter ++;

            $html .= <<< HTML
            <li><a href="$base/table/$arrayitem">$arrayitem</a></li>
HTML;
        }

        return $html;
    }

    public static function listTableData(array $array, $fieldTypes = array(), $header_row = null)
    {
        //$fieldTypes = convertFieldTypesEditable($fieldTypes);

        $html = '<table class="table table-striped table-bordered table-hover">' . "\n";
        $html .= '<thead>' . "\n";

        // build headings
        if (!empty($header_row) && is_array($header_row)) {
            // Use provided header row
            foreach ($header_row as $head) {
                $html .= "<th>" . htmlspecialchars($head, ENT_QUOTES, 'UTF-8') . "</th>" . "\n";
            }
        } elseif (!empty($array) && isset($array[0]) && is_array($array[0])) {
            // Fallback to inferring from data keys if no header_row or if data is present
            foreach (array_keys($array[0]) as $head) {
                $html .= "<th>" . htmlspecialchars($head, ENT_QUOTES, 'UTF-8') . "</th>" . "\n";
            }
        } else {
            // No data and no header row, perhaps render an empty header or a message
            // For now, just an empty thead if no headers can be determined.
        }


        $html .= '</thead>' . "\n";

        // build body
        $html .= '<tbody>' . "\n";

        //pretty_print($array);

        foreach ($array as $subArray) {
            $html .= '<tr>' . "\n";

            foreach ($subArray as $value) {
                $html .= '<td style="white-space: nowrap !important;">' . $value . '</td>' . "\n";
            }

            $html .= '</tr>' . "\n";
        }

        $html .= '</tbody>' . "\n";
        $html .= '</table>' . "\n";

        return $html;
    }
}