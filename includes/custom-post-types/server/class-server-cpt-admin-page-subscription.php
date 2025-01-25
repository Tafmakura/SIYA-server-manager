<?php

namespace SIYA\CustomPostTypes\ServerPost\Admin\Page;

class Subscription {
    
    /**
     * Get examples from here for subs table : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/class-wcs-admin-post-types.php
     * Get examples from here for subs details : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/meta-boxes/class-wcs-meta-box-subscription-data.php
     */

    public function __construct() {
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_server_widget'), 30, 1);
    }

    /**
     * Add custom column to subscriptions list
     */
    public function add_custom_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('status' === $key) {
                $new_columns['arsol-server-status'] = __('Server', 'siya-text-domain');
                // Add width style
                echo '<style>.column-arsol-server-status { width: 90px; }</style>';
            }
        }
        return $new_columns;
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {

        echo 'HELLO';

        $subscription_id = $order->get_id();
        $server_post_id = get_post_meta($subscription_id, 'arsol_linked_server_post_id', true);

        echo "Subscription ID: " . $subscription_id . "<br>";
        echo "Server Post ID: " . $server_post_id . "<br>";
    
        if (!$server_post_id) {
            return;
        }

    
        arsol_sub_component_status_subscription_page($server_post_id);


        echo 'WORLD';
    }
}