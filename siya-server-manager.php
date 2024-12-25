<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 1.0.6
 * Author: Your Name
 * Text Domain: siya
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include necessary files
require_once plugin_dir_path( __FILE__ ) . 'includes/woocommerce-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/cpt-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/server-integration-functions.php';

// Hook into WooCommerce subscription activation
add_action( 'woocommerce_subscription_status_active', 'create_runcloud_server_on_activate' );

// Hook into the server status check
add_action( 'woocommerce_subscription_status_pending', 'force_on_hold_and_check_server_status' );
