<?php
// autoload.php
spl_autoload_register(function ($class) {
    $baseDir = __DIR__ . '/libraries/phpseclib/';

    // Replace namespace separator with directory separator
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);

    // Append '.php' to the class name
    $file = $baseDir . $class . '.php';

    // Include the file if it exists
    if (file_exists($file)) {
        require_once $file;
    }
});