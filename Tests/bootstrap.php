<?php

/**
 * Bootstrap file for the test suite
 */

// Use SplClassLoader
require_once(__DIR__ . '/SplClassLoader.php');

spl_autoload_register(function ($class) {
    $separator = '\\';
    $extension = '.php';
    $namespace = 'Varspool\PygmentsBundle';
    $path = __DIR__ . '/..';

    if ($namespace . $separator === substr($class, 0, strlen($namespace . $separator))) {
        // Relative to this directory
        $class = substr($class, strlen($namespace . $separator));

        if (false !== ($lastNsPos = strripos($class, $separator))) {
            $namespace = substr($class, 0, $lastNsPos);
            $class = substr($class, $lastNsPos + 1);
            $file = str_replace($separator, DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        $file .= $class . $extension;
        require $path . DIRECTORY_SEPARATOR . $file;
    }
});
