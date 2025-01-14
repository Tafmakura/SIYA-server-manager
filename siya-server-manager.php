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

$ssh_host = '137.184.156.94';
$ssh_port = 22;


$ssh_connection = ssh2_connect($ssh_host, $ssh_port);
if (!$ssh_connection) {
    error_log('Failed to establish SSH connection to ' . $ssh_host . ' on port ' . $ssh_port);
    echo 'Failed to establish SSH connection to ' . $ssh_host . ' on port ' . $ssh_port;
} else {
    error_log('SSH connection successful to ' . $ssh_host);
    echo 'SSH connection successful to ' . $ssh_host;
}

