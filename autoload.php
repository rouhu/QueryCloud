<?php
/**
 * Created by PhpStorm.
 * User: SARFRAZ
 * Date: 1/19/14
 * Time: 10:23 PM
 */

// autoload classes from controllers/classes/presenters folders
function autoloadController($className) {
	if (false !== strpos($className, 'flight')) return;

	$className = strtolower($className);
    $filename = "app/controllers/" . $className . ".php";
    
	if (is_readable($filename)) {
        require $filename;
    }
}

function autoloadClass($className) {
	if (false !== strpos($className, 'flight')) return;

    // Preserve original class name for filename lookup
    $originalClassName = $className;
    $filename = "app/classes/" . $originalClassName . ".php";
    
	if (is_readable($filename)) {
        require $filename;
    }
}

function autoloadPresenter($className) {
	if (false !== strpos($className, 'flight')) return;

    // Preserve original class name for filename lookup (assuming Presenters also follow PascalCase)
    $originalClassName = $className;
    $filename = "app/presenters/" . $originalClassName . ".php";
	
    if (is_readable($filename)) {
        require $filename;
    }
}

spl_autoload_register('autoloadController');
spl_autoload_register('autoloadPresenter');
spl_autoload_register('autoloadClass');