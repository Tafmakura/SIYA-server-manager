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
                echo '<span class="server-status no-server">No Server</span>';
                return;
            }

            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

            if ($circuit_breaker == -1) {
                echo '<mark class="subscription-status order-status status-error arsol-server-status tips"><span>Error</span></mark>';
            } elseif ($circuit_breaker == 1) {
                echo '<mark class="subscription-status order-status status-in-progress arsol-server-status tips"><span>Maintenance</span></mark>';
            } elseif ($circuit_breaker == 0) {
                echo '<mark class="subscription-status order-status status-active arsol-server-status tips"><span>Okay</span></mark>';
            } else {
                echo '<mark class="subscription-status order-status status-pending arsol-server-status tips"><span>Setup</span></mark>';
            }
        }
    }

    public function add_status_styles() {
        ?>
        <style>
            .arsol-server-status {
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .status-active { background: #c6e1c6; color: #5b841b; }
            .status-pending { background: #f8dda7; color: #94660c; }
            .status-error { background: #eba3a3; color: #761919; }
            .status-in-progress { background: #c8d7e1; color: #2e4453; }
            .no-server { background: #e5e5e5; color: #777; }
        </style>
        <?php
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {
        echo '<div class="server-widget">HELLO WORLD</div>';
    }
}

