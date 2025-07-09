<?php

class Dashboard
{
    public static $title = 'Welcome!';
    public static $icon = 'glyphicon-home';

    public static function index()
    {
        // Checks whether or not user is logged in
        self::checkLogin();

        $saved_queries = [];
        try {
            $queries = ORM::for_table('saved_queries')
                            ->select('id')
                            ->select('query_name')
                            ->select('sql_query')
                            ->select('is_visual_query')
                            ->select('visual_params')
                            ->select('created_at')
                            ->order_by_desc('created_at')
                            ->find_array();
            if ($queries !== false) {
                $saved_queries = $queries;
            }
        } catch (Exception $e) {
            // Log error or handle gracefully
            error_log("Error fetching saved queries for dashboard: " . $e->getMessage());
            // $saved_queries will remain empty, view should handle this
        }

        Flight::render(
           'dashboard',
           array(
              'title' => self::$title,
              'icon' => self::$icon,
              'saved_queries' => $saved_queries
           )
        );
    }

    /**
     * Checks whether or not user is logged in. Redirects to login page if not.
     */
    private static function checkLogin()
    {
        // session stuff
        if (!isset($_SESSION['logged'])) {
            setFlashMessage('You must be logged in to view this page.');
            Flight::redirect(rtrim(Flight::get('base'), '/') . '/login');
            exit; // Ensure no further code execution after redirect
        }

        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            setFlashMessage('Access denied. You need admin privileges to view this page.');
            // Redirect to login, or a more appropriate page if one exists for non-admins
            Flight::redirect(rtrim(Flight::get('base'), '/') . '/login');
            exit; // Ensure no further code execution after redirect
        }
    }
}