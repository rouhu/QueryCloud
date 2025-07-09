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
                $site_url = rtrim($config['site_url'], '/');
                $share_path_segment = '/share/';

                // Check if the redirect_url starts with the site_url + share_path_segment
                // Or if it's a relative URL starting with the share_path_segment (more robust for same-origin redirects)
                $is_valid_absolute_share_redirect = strpos($redirect_url, $site_url . $share_path_segment) === 0;
                $is_valid_relative_share_redirect = strpos($redirect_url, $share_path_segment) === 0 &&
                                                    (strpos($redirect_url, '//') === false && strpos($redirect_url, 'http:') !== 0 && strpos($redirect_url, 'https:') !== 0);


                if ($is_valid_absolute_share_redirect) {
                    // It's an absolute URL matching our site and share path
                    Flight::redirect($redirect_url);
                    return;
                } elseif ($is_valid_relative_share_redirect) {
                    // It's a relative path like /share/TOKEN. Prepend site_url if base Flight::redirect needs it,
                    // or let Flight::redirect handle relative paths correctly.
                    // Flight::redirect usually handles relative paths from app root.
                    Flight::redirect($redirect_url);
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