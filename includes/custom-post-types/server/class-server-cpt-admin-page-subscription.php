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
     * Render custom column content
     *
     * @param string $column The column name.
     * @param \WC_Subscription $subscription The subscription object.
     */
    public function render_custom_column($column, $subscription) {

        if ('arsol-server-status' !== $column || !$subscription) {
            return;
        }
    
        arsol_component_status_pill_simple($subscription);
    
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {
        echo '<div class="server-widget">HELLO WORLD</div>';
    }
}