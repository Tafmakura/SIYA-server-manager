<?php

namespace Siya\Integrations\WoocommerceSubscriptions\Statuses;

/**
 * Class ServerError
 * Handles custom subscription status for server errors
 * Mimics on-hold functionality with server-error specific messaging
 */
class ServerError {
    
    /**
     * Status key for server error
     * @var string
     */
    const STATUS_KEY = 'wc-server-error';

    /**
     * Initialize the server error status
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize hooks and filters
     * @return void
     */
    private function init() {
        add_filter('wc_subscription_statuses', [$this, 'registerStatus']);
        add_filter('wc_subscription_bulk_actions', [$this, 'addBulkAction']);
        add_action('wcs_subscription_status_server-error', [$this, 'handleStatusChange']);
        add_filter('woocommerce_can_subscription_be_updated_to_server-error', [$this, 'canUpdateStatus'], 10, 2);
    }

    /**
     * Register the server error status
     * @param array $statuses Existing statuses
     * @return array Modified statuses
     */
    public function registerStatus($statuses) {
        $statuses[self::STATUS_KEY] = _x('Server Error', 'Subscription status', 'woocommerce-subscriptions');
        return $statuses;
    }

    /**
     * Add bulk action for server error status
     * @param array $actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function addBulkAction($actions) {
        $actions['server-error'] = __('Change to Server Error', 'woocommerce-subscriptions');
        return $actions;
    }

    /**
     * Handle status change to server error
     * @param WC_Subscription $subscription Subscription object
     * @return void
     */
    public function handleStatusChange($subscription) {
        // Pause payments like on-hold
        $subscription->update_status(
            'server-error',
            __('Subscription status changed to server error due to payment processing issues.', 'woocommerce-subscriptions')
        );

        // Trigger custom action for other integrations
        do_action('wcs_subscription_server_error_status_updated', $subscription);
    }

    /**
     * Check if subscription can be updated to server error
     * @param bool $can_update Current update permission
     * @param WC_Subscription $subscription Subscription object
     * @return bool Whether subscription can be updated
     */
    public function canUpdateStatus($can_update, $subscription) {
        // Mimic on-hold permission logic
        return $subscription->payment_method_supports('subscription_suspension');
    }
}
