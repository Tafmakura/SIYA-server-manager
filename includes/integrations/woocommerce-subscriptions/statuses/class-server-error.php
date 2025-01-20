<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {

    public function __construct() {
        add_filter('woocommerce_shop_subscription_list_table_columns', array($this, 'add_custom_column'), 20);
        add_action('woocommerce_shop_subscription_list_table_custom_column', array($this, 'render_custom_column'), 10, 2);
        add_action('admin_head', array($this, 'add_status_styles'));
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

            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
            if (!$server_post_id) {
                echo '<span class="server-status no-server">No Server</span>';
                return;
            }

            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
            $provision_status = get_post_meta($server_post_id, '_arsol_state_10_provisioning', true);
            $deployment_status = get_post_meta($server_post_id, '_arsol_state_30_deployment', true);

            if ($circuit_breaker == -1) {
                echo '<span class="server-status error">Error</span>';
            } elseif ($circuit_breaker == 1) {
                echo '<span class="server-status in-progress">In Progress</span>';
            } elseif ($provision_status == 2 && $deployment_status == 2) {
                echo '<span class="server-status active">Active</span>';
            } else {
                echo '<span class="server-status pending">Pending</span>';
            }
        }
    }

    public function add_status_styles() {
        ?>
        <style>
            .server-status {
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }
            .server-status.active { background: #c6e1c6; color: #5b841b; }
            .server-status.pending { background: #f8dda7; color: #94660c; }
            .server-status.error { background: #eba3a3; color: #761919; }
            .server-status.in-progress { background: #c8d7e1; color: #2e4453; }
            .server-status.no-server { background: #e5e5e5; color: #777; }
        </style>
        <?php
    }
}

