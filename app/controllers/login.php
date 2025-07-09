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
        $username_input = $_POST['username'];
        $password_input = $_POST['password'];

        // get user login details from config file
        $config = Flight::get('config');
        $users = isset($config['users']) && is_array($config['users']) ? $config['users'] : [];

        $authenticated_user = null;

        foreach ($users as $user) {
            if (isset($user['username']) && $user['username'] === $username_input &&
                isset($user['password']) && $user['password'] === $password_input) {
                $authenticated_user = $user;
                break;
            }
        }

        if ($authenticated_user) {
            $_SESSION['logged'] = true;
            $_SESSION['username'] = $authenticated_user['username'];
            $_SESSION['user_type'] = $authenticated_user['user_type'];

            // Handle redirect_to parameter - prioritize POST, then GET
            $redirect_url_param = null;
            if (!empty($_POST['redirect_to'])) {
                $redirect_url_param = $_POST['redirect_to'];
            } elseif (!empty($_GET['redirect_to'])) {
                $redirect_url_param = $_GET['redirect_to'];
                error_log("INFO: redirect_to parameter obtained from GET on POST request. Source: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
            }

            $app_base_path = rtrim(Flight::get('base'), '/'); // e.g., /querycloud or empty if root
            $is_share_link_redirect = false;

            if ($redirect_url_param) {
                $redirect_path = parse_url($redirect_url_param, PHP_URL_PATH);
                $redirect_path = filter_var($redirect_path, FILTER_SANITIZE_URL);
                // Ensure leading slash for path comparison if it's a relative path
                if ($redirect_path && strpos($redirect_path, '/') !== 0 && strpos($redirect_url_param, 'http') !== 0) {
                    $redirect_path = '/' . $redirect_path;
                }

                // Normalize $app_base_path to ensure it starts with a slash if not empty, for consistent prefix checking
                $normalized_app_base_path = $app_base_path;
                if (!empty($normalized_app_base_path) && strpos($normalized_app_base_path, '/') !== 0) {
                    $normalized_app_base_path = '/' . $normalized_app_base_path;
                }

                $expected_share_path_prefix = $normalized_app_base_path . '/share/';
                // Normalize expected_share_path_prefix by ensuring it has a trailing slash for strpos comparison
                $normalized_expected_share_path_prefix = rtrim($expected_share_path_prefix, '/') . '/';

                // Check if the redirect path starts with the expected share path prefix
                if ($redirect_path && strpos($redirect_path, $normalized_expected_share_path_prefix) === 0 && strlen($redirect_path) > strlen($normalized_expected_share_path_prefix)) {
                    // Validate host if it's an absolute URL
                    if (strpos($redirect_url_param, 'http') === 0) {
                        $current_server_host_header = $_SERVER['HTTP_HOST'];
                        $current_host_name = parse_url('http://' . $current_server_host_header, PHP_URL_HOST) ?: $current_server_host_header;
                        $current_host_processed = str_replace('www.', '', strtolower($current_host_name));

                        $redirect_host_parsed = parse_url($redirect_url_param, PHP_URL_HOST);
                        $redirect_host_processed = $redirect_host_parsed ? str_replace('www.', '', strtolower($redirect_host_parsed)) : '';

                        if (empty($redirect_host_processed) || $redirect_host_processed !== $current_host_processed) {
                            error_log("Redirect target host ('$redirect_host_processed') does not match current host ('$current_host_processed') or is invalid. URL: '$redirect_url_param'. Overriding redirect for viewer.");
                            // This scenario is problematic; for viewers, we might deny, for admins, it might proceed to home.
                            // For now, let's mark it as not a valid share link redirect if hosts mismatch.
                            $is_share_link_redirect = false;
                        } else {
                            $is_share_link_redirect = true;
                        }
                    } else {
                         // Relative URL, assume it's for the current host
                        $is_share_link_redirect = true;
                    }
                }
            }

            // User Type specific redirection
            if ($_SESSION['user_type'] === 'viewer') {
                if ($is_share_link_redirect) {
                    error_log("DEBUG: Viewer redirecting to share link: " . $redirect_url_param);
                    Flight::redirect($redirect_url_param);
                } else {
                    setFlashMessage('Viewers can only access shared reports.');
                    error_log("DEBUG: Viewer attempting to access non-share page or invalid share link. Redirecting to login.");
                    Flight::redirect(rtrim(Flight::get('base'), '/') . '/login');
                }
                return; // Important to prevent further execution for viewers
            }

            // Admin user redirection (existing logic largely applies)
            if ($redirect_url_param) {
                // If it's a share link and validated, redirect there (even for admin)
                if ($is_share_link_redirect) {
                     error_log("DEBUG: Admin redirecting to share link: " . $redirect_url_param);
                     Flight::redirect($redirect_url_param);
                     return;
                }
                // For admin, if it's not a share link but a valid local redirect (e.g. from a protected page attempt)
                // The original complex host validation for non-share links:
                // This part needs to be carefully considered. The original code had extensive host validation.
                // For simplicity here, if it's not a share link, admins are redirected to home if the redirect_url_param seems problematic.
                // A more robust solution would re-integrate the full host validation if needed for admins for non-share redirects.
                // For now, if it's an admin and not a share link, check if it's an internal redirect.
                // A simple check: if it starts with '/' or the app's base URL.
                $site_url_parsed = parse_url(Flight::get('config')['site_url']);
                $site_host = $site_url_parsed['host'] ?? '';

                $redirect_host_parsed = parse_url($redirect_url_param, PHP_URL_HOST);

                if (strpos($redirect_url_param, 'http') === 0 && $redirect_host_parsed && strtolower($redirect_host_parsed) !== strtolower($site_host)) {
                     error_log("DEBUG: Admin redirect_url_param to different host ('$redirect_host_parsed'), defaulting to home.");
                     Flight::redirect(rtrim(Flight::get('base'), '/') . '/home');
                } else if (strpos($redirect_url_param, 'http') !== 0 && strpos($redirect_url_param, '/') !== 0) {
                    // Relative URL that doesn't start with a slash, e.g. "page.html" - potentially unsafe
                    error_log("DEBUG: Admin redirect_url_param is a relative path not starting with '/', defaulting to home: " . $redirect_url_param);
                    Flight::redirect(rtrim(Flight::get('base'), '/') . '/home');
                }
                else {
                    // Assumed to be a safe local redirect (either relative starting with / or absolute to same host)
                    error_log("DEBUG: Admin redirecting to (non-share) param: " . $redirect_url_param);
                    Flight::redirect($redirect_url_param);
                }
                return;
            }

            error_log("DEBUG: Admin, no redirect_url_param. Defaulting to home.");
            Flight::redirect(rtrim(Flight::get('base'), '/') . '/home'); // Default redirect for admin

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
        unset($_SESSION['username']); // Also unset username and user_type
        unset($_SESSION['user_type']);
        // Consider unsetting the entire $_SESSION array if nothing else needs to be preserved across logout
        // unset($_SESSION); // This was too broad if flash messages or other session data is used by Flight post-redirect

        // More targeted session destruction
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy(); // Destroys all data registered to a session

        // It's good practice to regenerate ID after destroying session,
        // though typically new session is started on next page load.
        // For immediate effect if session_start() is called again on this script run:
        session_regenerate_id(true); // true invalidates old session file immediately


        Flight::redirect(rtrim(Flight::get('base'), '/') . '/login');
    }
}
