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

function is_ssh_available() {
    $output = [];
    $return_var = 0;

    exec('which ssh', $output, $return_var);

    return $return_var === 0;
}

if (is_ssh_available()) {
    echo "SSH is available.";
} else {
    echo "SSH is not available.";
}


