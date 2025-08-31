<?php

class Export
{
    /**
     * Export specified array in csv file.
     *
     * @return bool
     */
    public static function csv()
    {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=export.csv");

        $tableData = $_SESSION['tableData'];

        if (isset($_SESSION['unlimitedQuery']) && !empty($_SESSION['unlimitedQuery'])) {
            $connection_name = Table::get_data_source_connection_name();
            $db = ORM::get_db($connection_name);
            $stmt = $db->query($_SESSION['unlimitedQuery']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($data)) {
                $header = array_keys($data[0]);
                $tableData = array($header);
                foreach ($data as $row) {
                    $tableData[] = array_values($row);
                }
            }
        }
        
        if (empty($tableData)) {
            die("No data to export");
        }

        $nl = "\n";
        $tab = ",";
        $rows = '';

        foreach ($tableData as $rowArray) {
            if (is_array($rowArray)) {
                $rows .= $nl;

                foreach ($rowArray as $field) {
                    $rows .= '"' . str_replace('"', '""', $field) . '"' . $tab;
                }
                $rows = rtrim($rows, $tab);
            } else {
                $rows .= '"' . str_replace('"', '""', $rowArray) . '"' . $tab;
            }
        }

        echo ltrim($rows, $nl);
        exit;
    }

    /**
     * Export specified array in excel file.
     *
     * @return bool
     */
    public static function excel()
    {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-disposition: attachment; filename=export.xls");

        $tableData = $_SESSION['tableData'];

        if (isset($_SESSION['unlimitedQuery']) && !empty($_SESSION['unlimitedQuery'])) {
            $connection_name = Table::get_data_source_connection_name();
            $db = ORM::get_db($connection_name);
            $stmt = $db->query($_SESSION['unlimitedQuery']);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($data)) {
                $header = array_keys($data[0]);
                $tableData = array($header);
                foreach ($data as $row) {
                    $tableData[] = array_values($row);
                }
            }
        }

        if (! count($tableData)) {
            return false;
        }

        $nl = "\n";
        $tab = "\t";
        $rows = '';

        foreach ($tableData as $rowArray) {
            if (is_array($rowArray)) {
                $rows .= $nl;

                foreach ($rowArray as $field) {
                    $rows .= $field . $tab;
                }
            } else {
                $rows .= $rowArray . $tab;
            }
        }

        echo $rows;
    }

    /**
     * Converts an array to CSV format
     *
     * @param $data
     * @param string $delimiter
     * @param string $enclosure
     * @return string
     */
    private static function arrayToCsv($data, $delimiter = ',', $enclosure = '"')
    {
        $contents = '';
        $handle = fopen('php://temp', 'r+');

        foreach ($data as $line) {
            fputcsv($handle, $line, $delimiter, $enclosure);
        }

        rewind($handle);

        while (! feof($handle)) {
            $contents .= fread($handle, 8192);
        }

        fclose($handle);
        return $contents;
    }
}