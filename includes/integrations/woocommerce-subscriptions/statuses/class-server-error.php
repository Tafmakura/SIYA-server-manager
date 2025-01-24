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
                // Add width style
                echo '<style>.column-arsol-server-status { width: 150px; }</style>';
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
                echo '<mark class="subscription-status order-status status-no-server no-server tips"><span>No Server</span></mark>';
                return;
            }

            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

            $server_actions = array();
            $server_actions[] = '<a href="' . esc_url( get_edit_post_link($server_post_id) ) . '">View</a>'; // Always show View

            if ($circuit_breaker == -1) {
                echo '<mark class="subscription-status order-status server-status status-error error tips"><span>Error</span></mark>';
                $repair_url = wp_nonce_url(admin_url('admin-post.php?action=repair&subscription_id=' . $subscription_id), 'repair_nonce');
                $server_actions[] = '<a href="' . esc_url($repair_url) . '" class="repair-server">Repair</a>';
            } elseif ($circuit_breaker == 1) {
                echo '<mark class="subscription-status order-status server-status status-on-hold on-hold tips"><span>Repair</span></mark>';
            } elseif ($circuit_breaker == 0) {
                echo '<mark class="subscription-status order-status server-status status-active active tips"><span>Live</span></mark>';
                $reboot_url = wp_nonce_url(admin_url('admin-post.php?action=reboot&subscription_id=' . $subscription_id), 'reboot_nonce');
                $server_actions[] = '<a href="' . esc_url($reboot_url) . '" class="reboot-server">Reboot</a>';
            } else {
                echo '<mark class="subscription-status order-status server-status status-on-hold pending tips"><span>Setup</span></mark>';
            }

            // Add row actions
            echo '<div class="row-actions">';
            echo '<span class="view-server"><a href="' . esc_url( get_edit_post_link($server_post_id) ) . '">View</a></span>';

            if ($circuit_breaker == -1) {
                echo ' | <span class="repair-server"><a href="' . esc_url($repair_url) . '">Repair</a></span>';
            } elseif ($circuit_breaker == 0) {
                echo ' | <span class="reboot-server"><a href="' . esc_url($reboot_url) . '">Reboot</a></span>';
            }
            echo '</div>';
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
            .server-status.okay { background: #c6e1c6; color: #5b841b; }
            .server-status.setup { background: #f8dda7; color: #94660c; }
            .server-status.error { background: #eba3a3; color: #761919; }
            .server-status.repair { background: #c8d7e1; color:rgb(24, 77, 112); }
            .server-status.no-server { background: #e5e5e5; color: #777; }
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

