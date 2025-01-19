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
        // Load after WooCommerce Subscriptions
        add_action('woocommerce_subscriptions_loaded', array($this, 'init'), 10);
    }

    public function init() {
        // Register status
        $this->register_status();
        
        // Add status to lists
        add_filter('wcs_subscription_statuses', array($this, 'add_status'));
        add_filter('woocommerce_subscription_statuses', array($this, 'add_status'));
        add_filter('wc_order_statuses', array($this, 'add_status'));
        
        // Add bulk actions
        add_filter('bulk_actions-edit-shop_subscription', array($this, 'add_bulk_actions'));
        add_filter('woocommerce_subscription_bulk_actions', array($this, 'add_bulk_actions'));
        
        // Add status styling
        add_action('admin_head', array($this, 'add_status_style'));
    }

    public function register_status() {
        register_post_status('wc-custom-status', array(
            'label' => 'Custom Status',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Custom Status <span class="count">(%s)</span>', 
                                   'Custom Status <span class="count">(%s)</span>', 
                                   'arsol')
        ));
    }

    public function add_status($statuses) {
        $new_statuses = array();
        
        // Add custom status after 'active'
        foreach ($statuses as $key => $label) {
            $new_statuses[$key] = $label;
            if ($key === 'wc-active') {
                $new_statuses['wc-custom-status'] = _x('Custom Status', 'Subscription status', 'arsol');
            }
        }
        
        return $new_statuses;
    }

    public function add_bulk_actions($actions) {
        $actions['custom-status'] = __('Change status to Custom Status', 'arsol');
        return $actions;
    }

    public function add_status_style() {
        echo '<style>
            .widefat .column-order_status mark.custom-status:after,
            .widefat .column-order_status mark.status-custom-status:after {
                font-family: WooCommerce;
                speak: none;
                font-weight: 400;
                font-variant: normal;
                text-transform: none;
                line-height: 1;
                -webkit-font-smoothing: antialiased;
                margin: 0;
                text-indent: 0;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                text-align: center;
            }
            .widefat .column-order_status mark.status-custom-status {
                background: #73a724;
                color: #fff;
            }
        </style>';
    }
}

// Initialize subscription status handler
add_action('plugins_loaded', function() {
    new SIYA_Subscription_Status();
}, 11);


