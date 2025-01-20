<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

class ServerError {

    public function __construct() {
        // Register the custom status for subscriptions
        add_filter('woocommerce_subscriptions_registered_statuses', array($this, 'register_new_post_status'), 100, 1);

        // Add the custom status to the subscription status list
        add_filter('wcs_subscription_statuses', array($this, 'add_new_subscription_statuses'), 100, 1);

        // Handle updates to custom status
        add_filter('woocommerce_can_subscription_be_updated_to', array($this, 'extends_can_be_updated'), 100, 3);
        add_action('woocommerce_subscription_status_updated', array($this, 'extends_update_status'), 100, 3);

        // Add the custom status to bulk actions
        add_filter('woocommerce_subscription_bulk_actions', array($this, 'add_new_status_bulk_actions'), 100, 1);
        add_action('load-edit.php', array($this, 'parse_bulk_actions'));

        // Register the custom status for WooCommerce orders
        add_action('init', array($this, 'register_like_on_hold_order_statuses'));

        // Sync order status change with subscriptions
        add_action('woocommerce_order_status_like-on-hold', array($this, 'put_subscription_on_like_on_hold_for_order'), 100);
    }

    // Register the custom subscription status
    public function register_new_status($registered_statuses) {
        $registered_statuses['wc-like-on-hold'] = _nx_noop('Like On Hold <span class="count">(%s)</span>', 'Like On Hold <span class="count">(%s)</span>', 'post status label including post count', 'custom-wcs-status-texts');
        return $registered_statuses;
    }

    // Add the custom status to the subscription status list
    public function add_new_subscription_statuses($subscription_statuses) {
        $subscription_statuses['wc-like-on-hold'] = _x('Like On Hold', 'Subscription status', 'custom-wcs-status-texts');
        return $subscription_statuses;
    }

    // Determine if a subscription can be updated to the custom status
    public function extends_can_be_updated($can_be_updated, $new_status, $subscription) {
        if ($new_status == 'like-on-hold') {
            if ($subscription->payment_method_supports('subscription_suspension') && $subscription->has_status(array('active', 'pending', 'on-hold'))) {
                $can_be_updated = true;
            } else {
                $can_be_updated = false;
            }
        }
        return $can_be_updated;
    }

    // Perform actions when the subscription status is updated
    public function extends_update_status($subscription, $new_status, $old_status) {
        if ($new_status == 'like-on-hold') {
            $subscription->update_suspension_count($subscription->suspension_count + 1);
            wcs_maybe_make_user_inactive($subscription->customer_user);
        }
    }

    // Add the custom status to bulk actions
    public function add_new_status_bulk_actions($bulk_actions) {
        $bulk_actions['like-on-hold'] = _x('Mark Like On Hold', 'an action on a subscription', 'custom-wcs-status-texts');
        return $bulk_actions;
    }

    // Handle the bulk actions for subscriptions
    public function parse_bulk_actions() {
        if (!isset($_REQUEST['post_type']) || 'shop_subscription' !== $_REQUEST['post_type'] || !isset($_REQUEST['post'])) {
            return;
        }

        $action = '';
        if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
            $action = $_REQUEST['action'];
        } elseif (isset($_REQUEST['action2']) && -1 != $_REQUEST['action2']) {
            $action = $_REQUEST['action2'];
        }

        switch ($action) {
            case 'active':
            case 'on-hold':
            case 'cancelled':
            case 'like-on-hold':
                $new_status = $action;
                break;
            default:
                return;
        }

        $report_action = 'marked_' . $new_status;
        $changed = 0;
        $subscription_ids = array_map('absint', (array) $_REQUEST['post']);
        $sendback_args = array(
            'post_type' => 'shop_subscription',
            $report_action => true,
            'ids' => join(',', $subscription_ids),
            'error_count' => 0,
        );

        foreach ($subscription_ids as $subscription_id) {
            $subscription = wcs_get_subscription($subscription_id);
            $order_note = _x('Subscription status changed by bulk edit:', 'Used in order note. Reason why status changed.', 'woocommerce-subscriptions');

            try {
                if ('cancelled' == $action) {
                    $subscription->cancel_order($order_note);
                } else {
                    $subscription->update_status($new_status, $order_note, true);
                }

                do_action('woocommerce_admin_changed_subscription_to_' . $action, $subscription_id);
                $changed++;
            } catch (Exception $e) {
                $sendback_args['error'] = urlencode($e->getMessage());
                $sendback_args['error_count']++;
            }
        }

        $sendback_args['changed'] = $changed;
        $sendback = add_query_arg($sendback_args, wp_get_referer() ? wp_get_referer() : '');
        wp_safe_redirect(esc_url_raw($sendback));

        exit();
    }

    // Register the custom status for orders
    public function register_like_on_hold_order_statuses() {
        register_status('wc-like-on-hold', array(
            'label' => _x('Like On Hold', 'Order status', 'custom-wcs-status-texts'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Like On Hold <span class="count">(%s)</span>', 'Like On Hold<span class="count">(%s)</span>', 'woocommerce'),
        ));
    }

    // Sync order status with subscription status
    public function put_subscription_on_like_on_hold_for_order($order) {
        $subscriptions = wcs_get_subscriptions_for_order($order, array('order_type' => 'parent'));

        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                try {
                    if (!$subscription->has_status(wcs_get_subscription_ended_statuses())) {
                        $subscription->update_status('like-on-hold');
                    }
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to update subscription status after order #%1$s was put to like-on-hold: %2$s', 'woocommerce-subscriptions'), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }
            do_action('subscriptions_put_to_like_on_hold_for_order', $order);
        }
    }
}
