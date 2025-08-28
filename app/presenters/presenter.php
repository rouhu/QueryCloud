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

    public static function listTableData(array $array, $fieldTypes = array(), $display_header = null, $original_header = null)
    {
        $html = '<table class="table table-striped table-bordered table-hover">' . "\n";
        $html .= '<thead>' . "\n";

        // If original_header is not provided, fallback to display_header for keys
        if ($original_header === null) {
            $original_header = $display_header;
        }

        // build headings
        if (!empty($display_header) && is_array($display_header)) {
            foreach ($display_header as $index => $head) {
                // Use the original header name for the data attribute
                $original_name_attr = '';
                if (isset($original_header[$index])) {
                    $original_name_attr = 'data-original-name="' . htmlspecialchars($original_header[$index], ENT_QUOTES, 'UTF-8') . '"';
                }
                $html .= "<th " . $original_name_attr . ">" . htmlspecialchars($head, ENT_QUOTES, 'UTF-8') . "</th>" . "\n";
            }
        } elseif (!empty($array) && isset($array[0]) && is_array($array[0])) {
            // Fallback to inferring from data keys if no header info is provided
            foreach (array_keys($array[0]) as $head) {
                // In this fallback, original and display names are the same
                $html .= "<th data-original-name=\"" . htmlspecialchars($head, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($head, ENT_QUOTES, 'UTF-8') . "</th>" . "\n";
            }
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