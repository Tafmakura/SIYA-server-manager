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

function is_ssh2_enabled() {
    return function_exists('ssh2_connect');
}

if (is_ssh2_enabled()) {
    echo "SSH2 is enabled on this server.";
} else {
    echo "SSH2 is not enabled. Please install the PHP SSH2 extension.";
}
