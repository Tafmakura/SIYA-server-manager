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
        add_filter('wc_order_statuses', array($this, 'add_status_to_order'));
        add_filter('wcs_subscription_statuses', array($this, 'add_status_to_subscription'));
        add_filter('woocommerce_subscription_statuses', array($this, 'add_status_to_subscription'));
        add_filter('woocommerce_subscriptions_renewal_statuses', array($this, 'add_status_to_subscription'));
        add_filter('woocommerce_composite_subscriptions_statuses', array($this, 'add_status_to_subscription'));
        add_filter('woocommerce_admin_order_actions', array($this, 'add_subscription_actions'), 10, 2);
    }

    public function register_status() {
        if(!get_term_by('slug', 'custom-status', 'shop_subscription_status')) {
            wp_insert_term('Custom Status', 'shop_subscription_status', array('slug' => 'custom-status'));
        }
        
        register_post_status('wc-custom-status', array(
            'label' => __('Custom Status', 'arsol'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Custom Status <span class="count">(%s)</span>', 
                                   'Custom Status <span class="count">(%s)</span>', 
                                   'arsol')
        ));
    }

    public function add_status_to_subscription($statuses) {
        $new_statuses = array();
        // Add status after active
        foreach ($statuses as $key => $status) {
            $new_statuses[$key] = $status;
            if ($key === 'wc-active') {
                $new_statuses['wc-custom-status'] = _x('Custom Status', 'Subscription status', 'arsol');
            }
        }
        return $new_statuses;
    }

    public function add_status_to_order($order_statuses) {
        $order_statuses['wc-custom-status'] = _x('Custom Status', 'Order status', 'arsol');
        return $order_statuses;
    }

    public function add_subscription_actions($actions, $subscription) {
        if ($subscription->get_type() === 'shop_subscription') {
            if ($subscription->get_status() !== 'custom-status') {
                $actions['custom_status'] = array(
                    'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woocommerce_subscription_mark_custom-status&subscription_id=' . $subscription->get_id())),
                    'name' => __('Custom Status', 'arsol'),
                    'action' => 'custom-status'
                );
            }
        }
        return $actions;
    }
}

// Initialize the status handler
add_action('woocommerce_init', function() {
    new SIYA_Subscription_Status();
});


