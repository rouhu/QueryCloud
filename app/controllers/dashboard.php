<?php

class Dashboard
{
    public static $title = 'Welcome!';
    public static $icon = 'glyphicon-home';

    public static function index()
    {
        // Checks whether or not user is logged in
        self::checkLogin();

        Flight::render(
           'dashboard',
           array(
              'title' => self::$title,
              'icon' => self::$icon
           )
        );
    }

    /**
     * Checks whether or not user is logged in. Redirects to login page if not.
     */
    private static function checkLogin()
    {
        // session stuff
        if (! isset($_SESSION['logged'])) {
            Flight::redirect('./login');
        }
    }
}