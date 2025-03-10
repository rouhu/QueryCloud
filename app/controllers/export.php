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
        
        if (empty($_SESSION['tableData'])) {
            die("No data to export");
        }

        $nl = "\n";
        $tab = ",";
        $rows = '';

        foreach ($_SESSION['tableData'] as $rowArray) {
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

        if (! count($_SESSION['tableData'])) {
            return false;
        }

        $nl = "\n";
        $tab = "\t";
        $rows = '';

        foreach ($_SESSION['tableData'] as $rowArray) {
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