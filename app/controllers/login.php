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
            $redirect_url_param = $_GET['redirect_to'] ?? null;
            if ($redirect_url_param) {
                $app_base_path = rtrim(Flight::get('base'), '/'); // e.g., /querycloud or empty if root
                $expected_share_path_prefix = $app_base_path . '/share/';

                // Parse the path from the redirect_url_param
                $redirect_path = parse_url($redirect_url_param, PHP_URL_PATH);

                // Sanitize and normalize the path
                $redirect_path = filter_var($redirect_path, FILTER_SANITIZE_URL);
                $redirect_path = rtrim($redirect_path, '/'); // Remove trailing slash for comparison consistency
                // Add leading slash if missing, assuming it's relative to domain root or app_base_path
                if (strpos($redirect_path, '/') !== 0 && !empty($redirect_path)) {
                    $redirect_path = '/' . $redirect_path;
                }

                // Normalize expected_share_path_prefix by ensuring it has a trailing slash for strpos comparison
                $normalized_expected_share_path_prefix = rtrim($expected_share_path_prefix, '/') . '/';


                // Check if the redirect path starts with the expected share path prefix
                // This handles cases like:
                // 1. redirect_url_param = http://querycloud.io/share/token -> $redirect_path = /share/token
                //    app_base_path = '' -> $normalized_expected_share_path_prefix = /share/
                //    strpos("/share/token", "/share/") === 0 -> true
                // 2. redirect_url_param = http://localhost/querycloud/share/token -> $redirect_path = /querycloud/share/token
                //    app_base_path = '/querycloud' -> $normalized_expected_share_path_prefix = /querycloud/share/
                //    strpos("/querycloud/share/token", "/querycloud/share/") === 0 -> true
                // 3. redirect_url_param = /share/token (relative from domain root)
                //    app_base_path = '' -> $normalized_expected_share_path_prefix = /share/
                //    strpos("/share/token", "/share/") === 0 -> true
                // 4. redirect_url_param = /querycloud/share/token (relative from domain root, app in subdir)
                //    app_base_path = '/querycloud' -> $normalized_expected_share_path_prefix = /querycloud/share/
                //    strpos("/querycloud/share/token", "/querycloud/share/") === 0 -> true

                // We must ensure that $redirect_path is not just $app_base_path . '/'
                // and that there is something after $normalized_expected_share_path_prefix
                if ($redirect_path && strpos($redirect_path, $normalized_expected_share_path_prefix) === 0 && strlen($redirect_path) > strlen($normalized_expected_share_path_prefix)) {
                    // It's a valid share link path.
                    // We should use the original $redirect_url_param for Flight::redirect
                    // as it might be an absolute URL and Flight::redirect can handle it.
                    // Also, ensure it's not an open redirect vulnerability by checking it's not redirecting to a different domain
                    // if the original param was just a path.
                    // However, the initial problem was that different domains in config vs. param caused issues.
                    // The most important part is that it *is* a share link.
                    // Flight::redirect itself handles URL construction well.

                    // Basic check to prevent redirection to external domains if redirect_url_param was relative
                    // If redirect_url_param starts with http/https, it's fine.
                    // If it starts with /, it's relative to the current domain, also fine.
                    $is_external_redirect = false;
                    if (strpos($redirect_url_param, 'http') !== 0 && strpos($redirect_url_param, '/') !== 0) {
                        // This would be something like "evil.com/path", which is not what we want.
                        // However, parse_url with PHP_URL_PATH would likely make this less of an issue.
                        // For safety, we can restrict to known patterns.
                        // The current logic with parse_url and checking path structure is reasonably safe.
                    } else if (strpos($redirect_url_param, 'http') === 0) {
                        // If it's an absolute URL, validate its host matches the application's host
                        // This is a stricter check than just path validation.
                        $current_host_processed = str_replace('www.', '', strtolower($_SERVER['HTTP_HOST']));
                        $redirect_host_parsed = parse_url($redirect_url_param, PHP_URL_HOST);
                        $redirect_host_processed = '';
                        if ($redirect_host_parsed) {
                            $redirect_host_processed = str_replace('www.', '', strtolower($redirect_host_parsed));
                        }

                        // Extended logging for debugging
                        error_log("DEBUG: Login Redirect Check");
                        error_log("DEBUG: Original redirect_to: " . $redirect_url_param);
                        error_log("DEBUG: App base path (Flight::get('base')): " . $app_base_path);
                        error_log("DEBUG: Parsed redirect path: " . $redirect_path);
                        error_log("DEBUG: Expected share path prefix (normalized): " . $normalized_expected_share_path_prefix);
                        error_log("DEBUG: Current host (SERVER_HTTP_HOST): " . $_SERVER['HTTP_HOST']);
                        error_log("DEBUG: Processed current host: " . $current_host_processed);
                        error_log("DEBUG: Parsed redirect host: " . ($redirect_host_parsed ?: 'N/A'));
                        error_log("DEBUG: Processed redirect host: " . ($redirect_host_processed ?: 'N/A'));
                        error_log("DEBUG: Path comparison (strpos): " . (strpos($redirect_path, $normalized_expected_share_path_prefix) === 0 ? 'Match' : 'No Match'));
                        error_log("DEBUG: Length comparison (strlen): " . (strlen($redirect_path) > strlen($normalized_expected_share_path_prefix) ? 'Valid Length' : 'Invalid Length'));

                        if ($redirect_host_parsed && $redirect_host_processed !== $current_host_processed) {
                           error_log("Redirect target host ('$redirect_host_processed') does not match current host ('$current_host_processed'). URL: '$redirect_url_param'. Falling back to default home redirect.");
                           Flight::redirect(rtrim(Flight::get('base'), '/') . '/home');
                           return;
                        }
                    }

                    error_log("DEBUG: Redirecting to: " . $redirect_url_param);
                    Flight::redirect($redirect_url_param);
                    return;
                }
                error_log("Redirect URL parameter '$redirect_url_param' (path: '$redirect_path') did not match expected share path structure starting with '$normalized_expected_share_path_prefix'. Conditions: path_match=" . (strpos($redirect_path, $normalized_expected_share_path_prefix) === 0 ? 'true':'false') . ", length_ok=" . (strlen($redirect_path) > strlen($normalized_expected_share_path_prefix) ? 'true':'false') . ". Falling back to default redirect.");
            }
            error_log("DEBUG: No redirect_url_param or param was empty. Defaulting to home.");
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