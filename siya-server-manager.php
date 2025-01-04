<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 0.0.34
 * Author: Tafadzwa Makura
 * Text Domain: arsol
 */

// Define plugin directory constant if not already defined
if (!defined('SIYA_PLUGIN_DIR')) {
    define('SIYA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Include the Setup class
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-setup.php';

use Siya\Setup;

// Instantiate the Setup class
$siyaServerManager = new Setup();