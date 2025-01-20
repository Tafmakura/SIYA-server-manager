<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {
    public function __construct() {
        add_filter('woocommerce_subscriptions_registered_statuses', array($this, 'register_server_error_status'), 100, 1);
        add_filter('wcs_subscription_statuses', array($this, 'add_server_error_subscription_status'), 100, 1);
        add_filter('woocommerce_can_subscription_be_updated_to', array($this, 'allow_server_error_status_update'), 100, 3);
        add_action('woocommerce_subscription_status_updated', array($this, 'handle_server_error_status_update'), 100, 3);
        add_filter('woocommerce_subscription_bulk_actions', array($this, 'add_server_error_bulk_action'), 100, 1);
        add_action('load-edit.php', array($this, 'process_server_error_bulk_action'));
    }

    // Step 1: Register the new status in WooCommerce Subscriptions
    public function register_server_error_status($registered_statuses) {
        $registered_statuses['wc-server-error'] = _nx_noop('Server Error <span class="count">(%s)</span>', 'Server Error <span class="count">(%s)</span>', 'post status label including post count', 'custom-wcs-status-texts');
        return $registered_statuses;
    }

    // Step 2: Add the status to the subscription statuses list
    public function add_server_error_subscription_status($subscription_statuses) {
        $subscription_statuses['wc-server-error'] = _x('Server Error', 'Subscription status', 'custom-wcs-status-texts');
        return $subscription_statuses;
    }

    // Step 3: Allow specific subscriptions to be updated to the new status
    public function allow_server_error_status_update($can_be_updated, $new_status, $subscription) {
        if ($new_status == 'server-error') {
            if ($subscription->has_status(array('active', 'on-hold', 'pending'))) {
                $can_be_updated = true;
            } else {
                $can_be_updated = false;
            }
        }
        return $can_be_updated;
    }

    // Step 4: Perform an action when the status is updated to Server Error
    public function handle_server_error_status_update($subscription, $new_status, $old_status) {
        if ($new_status == 'server-error') {
            $subscription->add_order_note(__('Subscription status updated to Server Error.', 'custom-wcs-status-texts'));
            // You can add custom logic here, such as sending notifications
        }
    }

    // Step 5: Add the status to the bulk actions drop-down
    public function add_server_error_bulk_action($bulk_actions) {
        $bulk_actions['server-error'] = _x('Mark as Server Error', 'an action on a subscription', 'custom-wcs-status-texts');
        return $bulk_actions;
    }

    // Step 6: Handle the bulk actions logic
    public function process_server_error_bulk_action() {
        if (!isset($_REQUEST['post_type']) || $_REQUEST['post_type'] !== 'shop_subscription') {
            return;
        }

        $action = isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ? $_REQUEST['action'] : $_REQUEST['action2'];
        if ($action !== 'server-error') {
            return;
        }

        $subscription_ids = array_map('absint', (array) $_REQUEST['post']);
        foreach ($subscription_ids as $subscription_id) {
            $subscription = wcs_get_subscription($subscription_id);
            if ($subscription && !$subscription->has_status(wcs_get_subscription_ended_statuses())) {
                $subscription->update_status('server-error');
            }
        }

        wp_safe_redirect(esc_url_raw(remove_query_arg(array('action', 'action2', '_wpnonce', 'post'), wp_get_referer())));
        exit;
    }
}