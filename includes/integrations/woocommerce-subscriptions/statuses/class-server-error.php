<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {

    public function __construct() {
        add_filter('manage_shop_subscription_posts_columns', array($this, 'add_custom_column'), 20);
        add_action('manage_shop_subscription_posts_custom_column', array($this, 'render_custom_column'), 10, 2);
    }

    /**
     * Add custom column to subscriptions list
     */
    public function add_custom_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            // Add custom column after order title
            if ($key === 'order_title') {
                $new_columns['server_error_status'] = __('Server Error Status', 'siya-text-domain');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content
     */
    public function render_custom_column($column, $subscription_id) {
        if ('server_error_status' === $column) {
            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription) {
                // Example: Fetch server error status from meta
                $server_error_status = $subscription->get_meta('_server_error_status', true);
                echo esc_html($server_error_status ? $server_error_status : __('No Error', 'siya-text-domain'));
            }
        }
    }
}

