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
require_once plugin_dir_path(__FILE__) . 'includes/libraries/phpseclib/manual-autoload.php';

use Siya\Setup;
use phpseclib3\Net\SSH2;

// Instantiate the Setup class
$siyaServerManager = new Setup();

$ssh_host = '137.184.156.94';
$ssh_port = 22;

$ssh = new SSH2($ssh_host, $ssh_port);
if (!$ssh->login('username', 'password')) {
    error_log('Failed to establish SSH connection to ' . $ssh_host . ' on port ' . $ssh_port);
    echo 'Failed to establish SSH connection to ' . $ssh_host . ' on port ' . $ssh_port;
} else {
    error_log('SSH connection successful to ' . $ssh_host);
    echo 'SSH connection successful to ' . $ssh_host;
}

