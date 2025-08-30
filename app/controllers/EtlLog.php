<?php

class EtlLog
{
    private static $icon = 'fa fa-history';

    public static function index()
    {
        self::checkLogin();

        $logs = ORM::for_table('etl_logs')
            ->select('etl_logs.*')
            ->select('saved_queries.query_name')
            ->join('saved_queries', array('etl_logs.saved_query_id', '=', 'saved_queries.id'))
            ->order_by_desc('execution_time')
            ->find_many();

        Flight::render(
            'etl_log',
            array(
                'title' => 'ETL Execution Log',
                'icon' => self::$icon,
                'logs' => $logs
            )
        );
    }

    private static function checkLogin()
    {
        if (!isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}
?>
