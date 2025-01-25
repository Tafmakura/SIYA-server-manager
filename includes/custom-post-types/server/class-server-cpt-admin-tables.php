<?php

namespace SIYA\CustomPostTypes\ServerPost\Admin;

class Tables {
    
    /**
     * Get examples from here for subs table : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/class-wcs-admin-post-types.php
     * Get examples from here for subs details : plugins/woocommerce-subscriptions/vendor/woocommerce/subscriptions-core/includes/admin/meta-boxes/class-wcs-meta-box-subscription-data.php
     */

    public function __construct() {
        add_filter('woocommerce_shop_subscription_list_table_columns', array($this, 'add_custom_column'), 20);
        add_action('woocommerce_shop_subscription_list_table_custom_column', array($this, 'render_custom_column'), 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'add_server_widget'), 30, 1);
        add_action('manage_server_posts_custom_column', array($this, 'populate_custom_columns'), 10, 2);
        add_filter('manage_server_posts_columns', array($this, 'add_server_column'));
        add_action('manage_server_posts_custom_column', array($this, 'render_server_column'), 10, 2);
    }

    /**
     * Add custom column to subscriptions list
     */
    public function add_custom_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ('status' === $key) {
                $new_columns['arsol-server-status'] = __('Server Status', 'siya-text-domain');
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

        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
    
        arsol_sub_component_status_pill_simple($server_post_id);
    
    }

    /**
     * Add server widget to order details
     */
    public function add_server_widget($order) {
        echo '<div class="server-widget">HELLO WORLD</div>';
    }

    public function populate_custom_columns($column, $post_id) {
        if ($column === 'details') {
            echo '<style>.column-details { width: 400px !important; }</style>';
            // Get the associated subscription ID
            $subscription_id = get_post_meta($post_id, 'arsol_server_subscription_id', true);
    
            if ($subscription_id) {

                // Get the subscription object
                $subscription = wcs_get_subscription($subscription_id);

                if ($subscription) {
                    // Get the customer ID
                    $customer_id = $subscription->get_customer_id();
    
                    // Get billing name (first and last)
                    $billing_first_name = $subscription->get_billing_first_name();
                    $billing_last_name = $subscription->get_billing_last_name();
                    
                    // Check if the billing name exists, if not, use the profile name or username
                    if ($billing_first_name && $billing_last_name) {
                        $billing_name = $billing_first_name . ' ' . $billing_last_name;
                    } else {
                        // Fallback to user's display name or username if billing name is missing
                        $user = get_userdata($customer_id);
                        
                        // Use display name if available, otherwise fallback to username
                        $billing_name = $user ? $user->display_name : ( $user ? $user->user_login : __('No customer found', 'your-text-domain') );
                    }
    
                    // Generate links for subscription and customer
                    $subscription_link = get_edit_post_link($subscription_id);
                    $customer_wc_link = admin_url('user-edit.php?user_id=' . $customer_id);
    
                    // Render the column content
                    echo sprintf(
                        __('Assigned server post id: #%d for subscription: <strong><a href="%s">#%s</a></strong> associated with customer: <a href="%s">%s</a>', 'your-text-domain'),
                        $post_id,
                        esc_url($subscription_link),
                        esc_html($subscription_id),
                        esc_url($customer_wc_link),
                        esc_html($billing_name)
                    );
                } else {
                    echo __('Invalid subscription', 'your-text-domain');
                }
            } else {
                echo __('No subscription found', 'your-text-domain');
            }
        }
    }

    public function my_column_width() {
        echo '<style type="text/css">
                .column-arsol-server-status .column-details { width: auto ; overflow: hidden; }
              </style>';
    }

    public function add_server_column($columns) {
        // Add the new column at the start of the columns array
        $new_columns = array('arsol_server_status' => __('Status', 'your-text-domain'));
    
        // Merge the new column with the existing columns
        $new_columns = array_merge($new_columns, $columns);
    
        // Add width style for the column
        add_action('admin_head', function () {
            echo '<style>.column-arsol-server-status { width: 90px; }</style>';
        });
    
        return $new_columns;
    }
    

    public function render_server_column($column, $post_id) {
        if (!$column === 'arsol_server_status') {
            return;
        }

        $server_post_id = $post_id;
    
        arsol_sub_component_status_pill_simple($server_post_id);
    
    }
}