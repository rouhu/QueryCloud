<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2013, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

require_once __DIR__.'/core/Loader.php';

\flight\core\Loader::autoload(true, dirname(__DIR__));

spl_autoload_register(function($class) {
    $paths = array(
        'app/controllers/',
        'app/models/'
    );
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            error_log("Loading class: $class from $file"); // Debug line
            require_once $file;
            return;
        }
    }
    error_log("Class $class not found in paths: " . implode(', ', $paths)); // Debug missing classes
});