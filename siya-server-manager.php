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

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Custom_Woocommerce_Status_For_Subscription {
    
    public function __construct() {
        // Register the custom status.
        add_action('init', array($this, 'register_custom_post_status'));
        
        // Add to order status list.
        add_filter('wc_order_statuses', array($this, 'add_custom_post_status'));
        
        // Add to order list bulk actions.
        add_filter('woocommerce_register_shop_order_post_statuses', array($this, 'add_custom_status_to_order'));
        add_filter('woocommerce_subscription_statuses', array($this, 'add_custom_status_to_order'));
        add_filter('woocommerce_reports_order_statuses', array($this, 'add_custom_status_to_reports'));
        
        add_action('admin_footer', array($this, 'custom_status_bulk_actions'), 100);
        
        // Auto trigger complete virtual orders.
        add_action('woocommerce_subscription_status_updated', array($this, 'custom_woocommerce_auto_transition_secret_subscription_status'), 10, 3);
    }

    public function register_custom_post_status() {
        register_post_status('wc-custom-status', array(
            'label' => _x('Custom Status', 'Order status', 'woocommerce'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Custom Status <span class="count">(%s)</span>', 'Custom Status <span class="count">(%s)</span>')
        ));
    }

    public function add_custom_post_status($order_statuses) {
        $new_order_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-processing' === $key) {
                $new_order_statuses['wc-custom-status'] = 'Custom Status';
            }
        }
        return $new_order_statuses;
    }

    public function add_custom_status_to_order($order_statuses) {
        $order_statuses['wc-custom-status'] = array(
            'label' => 'Custom Status',
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Custom Status <span class="count">(%s)</span>', 'Custom Status <span class="count">(%s)</span>')
        );
        return $order_statuses;
    }

    public function add_custom_status_to_reports($order_statuses) {
        if (!in_array('custom-status', $order_statuses)) {
            $order_statuses[] = 'custom-status';
        }
        return $order_statuses;
    }

    public function custom_woocommerce_auto_transition_secret_subscription_status($subscription_id, $new_status, $old_status) {
        if ('custom-status' === $new_status) {
            // Add your custom code here for status transition
        }
    }

    public function custom_status_bulk_actions() {
        global $post_type;
        if ($post_type == 'shop_subscription') {
            ?>
            <script type="text/javascript">
                jQuery(function() {
                    jQuery('<option>').val('mark_custom-status').text('<?php _e('Change status to Custom Status', 'woocommerce')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_custom-status').text('<?php _e('Change status to Custom Status', 'woocommerce')?>').appendTo("select[name='action2']");
                });
            </script>
            <?php
        }
    }
}

new Custom_Woocommerce_Status_For_Subscription();


