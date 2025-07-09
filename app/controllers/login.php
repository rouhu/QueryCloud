<?php

class Login
{
    public static function index()
    {
        Flight::render('login');
    }

    /**
     * Attempt to login user based on credentials specified in config file.
     */
    public static function loginuser()
    {
        $username_ok = false;
        $password_ok = false;

        $username = $_POST['username'];
        $password = $_POST['password'];

        // get user login details from config file
        $config = Flight::get('config');

        // go through array and match username and password
        foreach ($config as $key => $value) {
            if ($key === 'username') {
                if (is_array($value)) {
                    foreach ($value as $value2) {
                        if ($username === $value2) {
                            $username_ok = true;
                        }
                    }
                } else {
                    if ($username === $value) {
                        $username_ok = true;
                    }
                }
            } elseif ($key === 'password') {
                if (is_array($value)) {
                    foreach ($value as $value2) {
                        if ($password === $value2) {
                            $password_ok = true;
                        }
                    }
                } else {
                    if ($password === $value) {
                        $password_ok = true;
                    }
                }
            }
        }

        if ($username_ok && $password_ok) {
            $_SESSION['logged'] = true;

            // Handle redirect_to parameter
            $redirect_url = $_GET['redirect_to'] ?? null;
            if ($redirect_url) {
                $config = Flight::get('config');
                $site_url_from_config = '';
                if (is_array($config) && isset($config['site_url']) && is_string($config['site_url']) && !empty(trim($config['site_url']))) {
                    $site_url_from_config = $config['site_url'];
                } else {
                    error_log("WARNING: config['site_url'] is not properly set for LoginController redirect validation. Redirect might be less secure or default.");
                }
                $site_url = rtrim($site_url_from_config, '/');
                $app_base_path = rtrim(Flight::get('base'), '/'); // Path like /querycloud or empty if root

                $share_path_segment = '/share/';

                // Normalize redirect_url if it's relative to the app base path for comparison with site_url
                $absolute_redirect_url = $redirect_url;
                if (strpos($redirect_url, 'http') !== 0) { // If it's not already absolute
                    if (strpos($redirect_url, '/') === 0) { // Starts with / e.g. /share/token or /appbase/share/token
                        if (!empty($app_base_path) && strpos($redirect_url, $app_base_path) === 0) {
                            // Already contains base path, e.g. /querycloud/share/token. Can be used as is for local redirect.
                            // For comparison with site_url, we might need to prepend scheme and host if site_url is full.
                            // However, Flight::redirect can handle paths relative to app root.
                        } else if (!empty($app_base_path)) {
                             // Relative to app root but doesn't include base, e.g. /share/token when base is /querycloud
                             // This case is tricky. Flight::redirect($redirect_url) might work if app is at domain root.
                             // If app is in subdir, $app_base_path . $redirect_url would be needed for some redirect methods.
                             // For now, let's assume Flight::redirect handles paths starting with '/' from actual domain root.
                        }
                    } else { // Truly relative e.g. share/token - less likely from urlencode
                        $absolute_redirect_url = $app_base_path . '/' . $redirect_url;
                    }
                }

                // Check if the (potentially now absolute) redirect_url starts with the site_url + share_path_segment
                // Or if the original redirect_url (if relative) starts with the share_path_segment
                $is_valid_absolute_share_redirect = !empty($site_url) && strpos($absolute_redirect_url, $site_url . $share_path_segment) === 0;
                $is_valid_relative_share_redirect = strpos($redirect_url, $share_path_segment) === 0 &&
                                                    (strpos($redirect_url, '//') === false && strpos($redirect_url, 'http:') !== 0 && strpos($redirect_url, 'https:') !== 0);

                if ($is_valid_absolute_share_redirect || $is_valid_relative_share_redirect) {
                    Flight::redirect($redirect_url); // Flight::redirect should handle both absolute and relative-to-base paths
                    return;
                }
                // If not a valid share redirect, fall through to default.
            }
            Flight::redirect(rtrim(Flight::get('base'), '/') . '/home'); // Default redirect
        } else {
            setFlashMessage('Error: Invalid username or password!');
            Flight::redirect(rtrim(Flight::get('base'), '/') . '/login');
        }
    }

    /**
     * Logout logged user
     */
    public static function logout()
    {
        unset($_SESSION['db']);
        unset($_SESSION['logged']);
        unset($_SESSION);
        @session_destroy();
        session_regenerate_id();

        Flight::redirect('/login');
    }
}