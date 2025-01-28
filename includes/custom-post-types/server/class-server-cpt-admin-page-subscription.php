<?php

namespace Siya\CustomPostTypes\ServerPost\Admin\Page;

class Subscription {
    
    /**
     * Get examples from here for subs table : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/class-wcs-admin-post-types.php
     * Get examples from here for subs details : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/meta-boxes/class-wcs-meta-box-subscription-data.php
     */

    public function __construct() {
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_server_widget'), 30, 1);
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {

        $subscription = $order;
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);

        if (!$server_post_id) {
            return;
        }

        arsol_sub_component_status_subscription_page($server_post_id);

    }
}