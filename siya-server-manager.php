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
        // Register the custom status after WC Subscriptions is loaded
        add_action('woocommerce_loaded', array($this, 'register_custom_post_status'));
        
        // Add status to lists
        add_filter('wcs_order_statuses', array($this, 'add_custom_post_status'));
        add_filter('woocommerce_reports_order_statuses', array($this, 'add_custom_post_status'));
        add_filter('wc_order_statuses', array($this, 'add_custom_post_status'));
        
        // Add custom status bulk actions
        add_action('admin_footer', array($this, 'custom_status_bulk_actions'), 100);
    }

    public function register_custom_post_status() {
        global $wc_current_version;

        register_post_status('wc-custom-status', array(
            'label' => _x('Custom Status', 'Order status', 'arsol'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Custom Status <span class="count">(%s)</span>', 
                                   'Custom Status <span class="count">(%s)</span>', 
                                   'arsol')
        ));
    }

    public function add_custom_post_status($order_statuses) {
        $order_statuses['wc-custom-status'] = _x('Custom Status', 'Order status', 'arsol');
        return $order_statuses;
    }

    public function custom_status_bulk_actions() {
        global $post_type;
        if ($post_type == 'shop_subscription') {
            ?>
            <script type="text/javascript">
                jQuery(function() {
                    jQuery('<option>').val('mark_custom-status').text('<?php _e('Change status to Custom Status', 'arsol')?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_custom-status').text('<?php _e('Change status to Custom Status', 'arsol')?>').appendTo("select[name='action2']");
                });
            </script>
            <?php
        }
    }
}

// Initialize immediately rather than with plugins_loaded
$siya_subscription_status = new SIYA_Subscription_Status();


