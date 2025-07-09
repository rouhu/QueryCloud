<?php

set_time_limit(3600); // Potentially long queries

class Share
{
    public static function viewReport($token)
    {
        if (empty($token)) {
            Flight::render('error_page', ['message' => 'Share token is missing.']);
            return;
        }

        // Start session if not already started, as we need it for export.
        // This needs to be carefully managed on a public route.
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $saved_query = ORM::for_table('saved_queries')
                        ->where('share_token', $token)
                        ->find_one();

        if (!$saved_query) {
            Flight::render('error_page', ['message' => 'This share link is invalid or the report no longer exists.']);
            return;
        }

        // Check if this shared query requires login
        if ($saved_query->share_requires_login) {
            if (!isset($_SESSION['logged']) || !$_SESSION['logged']) {
                // User is not logged in, redirect to login page with a redirect_to parameter
                $current_share_url = rtrim(Flight::get('base'), '/') . '/share/' . $token;
                Flight::redirect(rtrim(Flight::get('base'), '/') . '/login?redirect_to=' . urlencode($current_share_url));
                return; // Stop further execution
            }
            // If user is logged in, proceed
        }

        $sql_query = $saved_query->sql_query;
        $table_formatting_json = $saved_query->table_formatting;
        $query_name = $saved_query->query_name;

        $_SESSION['tableData'] = array(); // Initialize for export
        $data_for_view = [];
        $header_for_view = [];
        $timetaken = '0.00';
        $error_message = null;

        try {
            Flight::get('db')->query('SET profiling = 1;');
            $stmt = Flight::get('db')->query($sql_query);

            $exec_time_result = Flight::get('db')->query(
                'SELECT query_id, SUM(duration) FROM information_schema.profiling GROUP BY query_id ORDER BY query_id DESC LIMIT 1;'
            );
            $exec_time_row = $exec_time_result->fetchAll(PDO::FETCH_NUM);
            $timetaken = $exec_time_row[0][1] ?? '0.00';

            $data_for_view = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($data_for_view) && isset($data_for_view[0])) {
                $header_for_view = array_keys($data_for_view[0]); // Original headers

                // Apply table formatting (column titles)
                if (!empty($table_formatting_json)) {
                    $formatting_rules = json_decode($table_formatting_json, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($formatting_rules['column_titles'])) {
                        $custom_header = [];
                        foreach ($header_for_view as $original_col_name) {
                            $custom_header[] = (!empty($formatting_rules['column_titles'][$original_col_name])) ? $formatting_rules['column_titles'][$original_col_name] : $original_col_name;
                        }
                        $header_for_view = $custom_header; // Use formatted headers
                    }
                }
                // For export: uses the (potentially custom) $header_for_view
                $_SESSION['tableData'][] = $header_for_view;
                foreach ($data_for_view as $row) {
                    $_SESSION['tableData'][] = array_values($row);
                }
            } else {
                $_SESSION['tableData'] = [['No results to display.']]; // For export
            }
            // Set flag for export context
            $_SESSION['is_shared_export_context'] = true;

        } catch (PDOException $e) {
            error_log("Error executing shared query (token $token): " . $e->getMessage());
            $error_message = "An error occurred while running the report: " . htmlspecialchars($e->getMessage());
            $_SESSION['tableData'] = [['Error executing query.']];
            // Even on error, set the flag if session is used for error message export
            $_SESSION['is_shared_export_context'] = true;
        }

        $table_html = '';
        if ($error_message) {
            $table_html = "<div class='alert alert-danger'>" . $error_message . "</div>";
        } elseif (empty($data_for_view)) {
            $table_html = "<div class='alert alert-info'>No results found for this report.</div>";
        } else {
            // Pass the (potentially custom) $header_for_view to Presenter
            $table_html = Presenter::listTableData($data_for_view, [], $header_for_view);
        }

        // Base URL for assets
        $base_url = Flight::get('base');
        if (substr($base_url, -1) !== '/') {
            $base_url .= '/';
        }


        Flight::render(
            'share_report',
            array(
                'report_title' => $query_name,
                'table_data_html' => $table_html,
                'timetaken' => $timetaken,
                'base_url' => $base_url, // Pass base_url to the view for assets
                'token' => $token // Pass token for potential re-use in view/JS if needed later
            )
        );
    }
}
