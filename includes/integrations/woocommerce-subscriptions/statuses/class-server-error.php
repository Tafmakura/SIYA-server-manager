<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {

    /**
     * Get examples from here for subs table : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/class-wcs-admin-post-types.php
     * Get examples from here for subs details : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/meta-boxes/class-wcs-meta-box-subscription-data.php
     */

    public function __construct() {
        add_filter('woocommerce_shop_subscription_list_table_columns', array($this, 'add_custom_column'), 20);
        add_action('woocommerce_shop_subscription_list_table_custom_column', array($this, 'render_custom_column'), 10, 2);
        add_action('admin_head', array($this, 'add_status_styles'));
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

        if ('arsol-server-status' === $column) {
            $subscription = wcs_get_subscription($subscription_id);
            if (!$subscription) return;

            $status = $subscription->get_status();
            if (!in_array($status, array('active', 'on-hold', 'pending-cancel'))) {
                echo '&mdash;';
                return;
            }

            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
            if (!$server_post_id) {
                // Load the status button template for "No Server"
                $template_path = __FILE__ . '/ui/components/admin/status-button.php';
                if (file_exists($template_path)) {
                    $status = 'no-server';
                    $label = 'No Server';
                    require_once $template_path;
                }
                return;
            }

            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

            // Load the status button template with the appropriate data
            $template_path = __FILE__ . '/ui/components/admin/status-button.php';
           //if (file_exists($template_path)) {
                require_once $template_path;
          //  }

            ?> <h1> HOYO!!!! </h1> <?php 
        }
    }

    /**
     * Remove inline CSS for status styles
     */
    public function add_status_styles() {
        // Remove inline CSS
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {
        echo '<div class="server-widget">HELLO WORLD</div>';
    }
}