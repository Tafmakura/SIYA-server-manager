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

// Custom Subscription Status Handler
class SIYA_Subscription_Status {
    public function __construct() {
        add_action('init', array($this, 'register_status'));
        add_filter('woocommerce_subscription_statuses', array($this, 'add_to_subscriptions'));
        add_filter('wc_order_statuses', array($this, 'add_to_admin'));
        add_action('admin_head', array($this, 'add_status_style'));
    }

    public function register_status() {
        register_post_status('wc-custom-status', array(
            'label'                     => _x('Custom Status', 'Subscription status', 'arsol'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop(
                'Custom Status <span class="count">(%s)</span>', 
                'Custom Status <span class="count">(%s)</span>', 
                'arsol'
            )
        ));
    }

    public function add_to_subscriptions($statuses) {
        $statuses['wc-custom-status'] = _x('Custom Status', 'Subscription status', 'arsol');
        return $statuses;
    }

    public function add_to_admin($order_statuses) {
        $order_statuses['wc-custom-status'] = _x('Custom Status', 'Order status', 'arsol');
        return $order_statuses;
    }

    public function add_status_style() {
        echo '<style>
            .widefat .column-order_status mark.custom-status:after {
                content: "\e011";
                color: #73a724;
            }
            .widefat .column-order_status mark.status-custom-status {
                background-color: #73a724;
                color: #ffffff;
            }
        </style>';
    }
}

// Initialize subscription status handler
new SIYA_Subscription_Status();


