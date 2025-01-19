<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\WoocommerceSubscriptions\ServerOrchestrator;

class ServerCircuitBreaker extends ServerOrchestrator {
    public $subscription;
    public $server;
    public $server_product_id;
    public $server_post_id;
    public $subscription_id;

    public function __construct() {
        add_action('woocommerce_subscription_status_active', array($this, 'subscription_circuit_breaker'), 20, 1);
    }

    public function subscription_circuit_breaker($subscription) {
        try {
            $this->subscription = $subscription;
            $this->subscription_id = $this->subscription->get_id();
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting subscription circuit breaker check for subscription ID: ' . $this->subscription_id);

            // Get server post ID linked to the subscription
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
            $this->server_post_id = $server_post_id;

            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server post ID: ' . $server_post_id);

            // Check if server post ID is found
            if (!$server_post_id) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] No linked server post ID found for subscription');
                return;
            }

            // Get server metadata
            $server_provisioned_status = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
            $server_deployed_status = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);
            $server_manager_required = get_post_meta($this->server_post_id, 'arsol_server_manager_required', true);

            // Log metadata status
            error_log(sprintf('[SIYA Server Manager - ServerCircuitBreaker] Status - Provisioned: %s, Deployed: %s, Requires Manager: %s', 
                $server_provisioned_status == 2 ? 'true' : 'false',
                $server_deployed_status == 2 ? 'true' : 'false',
                $server_manager_required
            ));

            // Set the initial circuit breaker status to "half-open"
            update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'half-open');
            $subscription->update_status('on-hold');
            $subscription->add_order_note("Server provisioning check initiated. Status set to on-hold.");

            // Check if provisioning and deployment are complete
            if ($server_provisioned_status == 2 && $server_deployed_status == 2) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server setup is fine.');
                update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'complete');
                $subscription->update_status('active');
                $subscription->add_order_note("Server provisioning and deployment complete. Subscription activated.");
                return;
            }

            // Trigger provisioning if necessary
            if ($server_provisioned_status != 2 || $server_deployed_status != 2) {
                if ($server_provisioned_status == 2 && $server_deployed_status != 2 && $server_manager_required === 'no') {
                    update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'okay');
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server provisioned but deployment not required. No action needed.');
                    $subscription->add_order_note("Server provisioned but deployment not required. No action needed.");
                    return;
                } else {
                    update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'tripped');
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker tripped. Triggering provisioning process.');
                    $subscription->update_status('on-hold');
                    $this->start_server_provision($this->subscription);
                    $subscription->add_order_note("Server provisioned but deployment failed. Retrying deployment.");
                    return;
                }
            }

            // Catch-all case for unexpected states
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Unexpected state encountered. Manual intervention required.');
            update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'tripped');
            $subscription->update_status('on-hold');
            $subscription->add_order_note("Unexpected server state encountered. Manual intervention required.");

        } catch (Exception $e) {
            // Log the exception and notify for manual intervention
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Exception occurred: ' . $e->getMessage());
            $subscription->update_status('on-hold');
            $subscription->add_order_note("An error occurred during provisioning: " . $e->getMessage() . ". Manual intervention required.");
        }
    }
}
