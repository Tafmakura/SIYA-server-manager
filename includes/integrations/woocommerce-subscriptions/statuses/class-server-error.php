<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {

    public function __construct() {
        add_filter('woocommerce_shop_subscription_list_table_columns', array($this, 'add_custom_column'), 20);
        add_action('woocommerce_shop_subscription_list_table_custom_column', array($this, 'render_custom_column'), 10, 2);
    }

    /**
     * Add custom column to subscriptions list
     */
    public function add_custom_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('status' === $key) {
                $new_columns['server_error_status'] = __('Server Error Status', 'siya-text-domain');
            }
        }
        return $new_columns;
    }

    /**
     * Render custom column content
     *
     * @param string $column The column name.
     * @param int $subscription_id The subscription ID.
     */
    public function render_custom_column($column, $subscription_id) {
        global $post, $the_subscription;

        // Attempt to get the subscription ID for the current row from the passed variable or the global $post object.
        if ( ! empty( $subscription_id ) ) {
            $subscription_id = is_int( $subscription_id ) ? $subscription_id : $subscription_id->get_id();
        } else {
            $subscription_id = $post->ID;
        }

        // If we have a subscription ID, set the global $the_subscription object.
        if ( empty( $the_subscription ) || $the_subscription->get_id() !== $subscription_id ) {
            $the_subscription = wcs_get_subscription( $subscription_id );
        }

        // If the subscription failed to load, only display the ID.
        if ( empty( $the_subscription ) ) {
            echo '&mdash;';
            return;
        }

        if ('server_error_status' === $column) {
            // Example: Fetch server error status from meta
            $server_error_status = $the_subscription->get_meta('_server_error_status', true);
            echo esc_html($server_error_status ? $server_error_status : __('No Error', 'siya-text-domain'));
        }
    }
}

