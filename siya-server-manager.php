<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 0.0.78
 * Author: Tafadzwa Makura
 * Text Domain: arsol
 */

// Include the Setup class
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-setup.php';

use Siya\Setup;


// Instantiate the Setup class
$siyaServerManager = new Setup();

// Initialize the plugin


echo 'HOYO>>>>>>>>>>>>>>>>>>>>>>>>';

$ssh_host = 'staging.portal.automatedretail.io';
$ssh_port = 22;

if (function_exists('ssh2_connect')) {
    try {
    echo 'Connected to ' . $ssh_host . ' on port ' . $ssh_port . PHP_EOL;
        $ssh_connection = ssh2_connect($ssh_host, $ssh_port);
        if ($ssh_connection) {
            echo 'Connected to ' . $ssh_host . ' on port ' . $ssh_port . PHP_EOL;
        } else {
            echo 'Connection to ' . $ssh_host . ' on port ' . $ssh_port . ' failed.' . PHP_EOL;
        }
    } catch (Exception $e) {
        echo 'An error occurred: ' . $e->getMessage() . PHP_EOL;
    }
} else {
    echo 'The ssh2_connect function is not available.' . PHP_EOL;
}


