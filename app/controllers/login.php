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
                // Basic validation: ensure it's a relative path starting with /share/ or an absolute path for this app
                // A more robust validation might be needed depending on security requirements
                $app_base_url = rtrim(Flight::get('base'), '/');
                $is_valid_share_redirect = (strpos($redirect_url, '/share/') === 0) ||
                                           (strpos($redirect_url, $app_base_url . '/share/') === 0);

                if ($is_valid_share_redirect) {
                    // Ensure the redirect URL is not an external malicious URL by checking if it starts with base or is relative to /share/
                    // The check above is a basic one. For full security, one might parse the URL and check host, etc.
                    // Or, only allow relative /share/ paths.
                    // For now, if it contains /share/ and doesn't look obviously external, proceed.
                    // A simple way to ensure it's not an external http:// link if $app_base_url is empty or just '/':
                    if (strpos($redirect_url, 'http:') === 0 || strpos($redirect_url, 'https:') === 0) {
                        // If it's an absolute URL, it must match the app's base to be considered safe.
                        if (strpos($redirect_url, $app_base_url) !== 0) {
                            Flight::redirect('./home'); // Or an error, or just default redirect
                            return;
                        }
                    }
                    // If it's a relative path like /share/..., it's fine.
                    // If it's an absolute path that matched base, it's fine.
                    Flight::redirect($redirect_url);
                    return;
                }
            }
            Flight::redirect('./home'); // Default redirect if no valid redirect_to
        } else {
            setFlashMessage('Error: Invalid username or password!');
            Flight::redirect('./login');
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