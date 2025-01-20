<?php
/**
 * Plugin Name: SIYA Server Manager
 * Description: Server integration and yield augmentation plugin for WooCommerce.
 * Version: 0.0.80
 * Author: Tafadzwa Makura
 * Text Domain: arsol
 */

// Include the Setup class
require_once plugin_dir_path(__FILE__) . 'includes/classes/class-setup.php';

use Siya\Setup;



// Instantiate the Setup class
$siyaServerManager = new Setup();

// Include the Composer autoload to load phpseclib classes
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';




// Step 1: Register the new status in WooCommerce Subscriptions
add_filter('woocommerce_subscriptions_registered_statuses', 'register_hoyo_status', 100, 1);

function register_hoyo_status($registered_statuses) {
    $registered_statuses['wc-hoyo'] = _nx_noop('Hoyo <span class="count">(%s)</span>', 'Hoyo <span class="count">(%s)</span>', 'post status label including post count', 'custom-wcs-status-texts');
    return $registered_statuses;
}

// Step 2: Add the status to the subscription statuses list
add_filter('wcs_subscription_statuses', 'add_hoyo_subscription_status', 100, 1);

function add_hoyo_subscription_status($subscription_statuses) {
    $subscription_statuses['wc-hoyo'] = _x('Hoyo', 'Subscription status', 'custom-wcs-status-texts');
    return $subscription_statuses;
}

// Step 3: Allow specific subscriptions to be updated to the new status
add_filter('woocommerce_can_subscription_be_updated_to', 'allow_hoyo_status_update', 100, 3);

function allow_hoyo_status_update($can_be_updated, $new_status, $subscription) {
    if ($new_status == 'hoyo') {
        if ($subscription->has_status(array('active', 'on-hold', 'pending'))) {
            $can_be_updated = true;
        } else {
            $can_be_updated = false;
        }
    }
    return $can_be_updated;
}

// Step 4: Perform an action when the status is updated to Hoyo
add_action('woocommerce_subscription_status_updated', 'handle_hoyo_status_update', 100, 3);

function handle_hoyo_status_update($subscription, $new_status, $old_status) {
    if ($new_status == 'hoyo') {
        $subscription->add_order_note(__('Subscription status updated to Hoyo.', 'custom-wcs-status-texts'));
        // You can add custom logic here, such as sending notifications
    }
}

// Step 5: Add the status to the bulk actions drop-down
add_filter('woocommerce_subscription_bulk_actions', 'add_hoyo_bulk_action', 100, 1);

function add_hoyo_bulk_action($bulk_actions) {
    $bulk_actions['hoyo'] = _x('Mark as Hoyo', 'an action on a subscription', 'custom-wcs-status-texts');
    return $bulk_actions;
}

// Step 6: Handle the bulk actions logic
add_action('load-edit.php', 'process_hoyo_bulk_action');

function process_hoyo_bulk_action() {
    if (!isset($_REQUEST['post_type']) || $_REQUEST['post_type'] !== 'shop_subscription') {
        return;
    }

    $action = isset($_REQUEST['action']) && $_REQUEST['action'] !== '-1' ? $_REQUEST['action'] : $_REQUEST['action2'];
    if ($action !== 'hoyo') {
        return;
    }

    $subscription_ids = array_map('absint', (array) $_REQUEST['post']);
    foreach ($subscription_ids as $subscription_id) {
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription && !$subscription->has_status(wcs_get_subscription_ended_statuses())) {
            $subscription->update_status('hoyo');
        }
    }

    wp_safe_redirect(esc_url_raw(remove_query_arg(array('action', 'action2', '_wpnonce', 'post'), wp_get_referer())));
    exit;
}

