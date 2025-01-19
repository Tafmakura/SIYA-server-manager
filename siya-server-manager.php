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






add_filter('manage_edit-wcs_subscription_columns', 'add_custom_status_column');
add_action('manage_wcs_subscription_posts_custom_column', 'render_custom_status_column', 10, 2);

function add_custom_status_column($columns) {
    $columns['subscription_status'] = __('Subscription Status', 'text_domain');
    return $columns;
}

function render_custom_status_column($column, $post_id) {
    if ($column == 'subscription_status') {
        $subscription = wcs_get_subscription($post_id);
        $status = $subscription->get_status();
        $status_text = wcs_get_subscription_status_name($status);
        echo '<span>Hello, here is the status:</span> ' . $status_text;
    }
}





