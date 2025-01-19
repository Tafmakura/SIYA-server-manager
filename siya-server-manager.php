<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 0.0.80
 * Author: Tafadzwa Makura
 * Text Domain: arsol
 */

// Include the Setup class
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-setup.php';

use Siya\Setup;



// Instantiate the Setup class
$siyaServerManager = new Setup();

// Include the Composer autoload to load phpseclib classes
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';






add_filter('wcs_view_subscription_columns', 'add_custom_hello_text_to_status_column', 10, 2);

function add_custom_hello_text_to_status_column($column, $subscription) {
    if ($column == 'status') {
        $status = wcs_get_subscription_status($subscription);
        $status_text = wcs_get_subscription_status_name($status);
        echo '<span>Hello, here is the status:</span><br>' . $status_text;
    }
}





