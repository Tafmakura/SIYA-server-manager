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
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting subscription circuit breaker check for subscription ' . $this->subscription_id);
    
            // Get server post ID linked to the subscription
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
            $this->server_post_id = $server_post_id;

            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server post ID: ' . $server_post_id);
            
            // Check if server post ID is found
            if (!$server_post_id) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] No linked server post ID found for subscription');
                return;
            }

            // List of server metadata keys
            $server_metadata_keys = [
                'arsol_server_provisioned_status',
                'arsol_server_deployed_status',
                'arsol_server_manager_required',
                'arsol_server_deployed_status_connection',
                'arsol_server_provisioned_status_script_execution',
                'arsol_server_provisioned_status_script_installation',
                'arsol_server_provisioned_status_firewall_rules',
            ];

            // Fetching metadata values
            $server_metadata = [];
            foreach ($server_metadata_keys as $key) {
                $server_metadata[$key] = get_post_meta($this->server_post_id, $key, true);
            }

            // Log the retrieved values for debugging
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server Metadata: ' . print_r($server_metadata, true));

            // Check if server manager is required
            $server_manager_required = $server_metadata['arsol_server_manager_required'];

            // If server manager is required, check all statuses must be 2
            if ($server_manager_required === 'yes') {
                $all_status_complete = true;
                foreach ($server_metadata as $key => $value) {
                    if ($key != 'arsol_server_manager_required' && $value != 2) {
                        $all_status_complete = false;
                        break;
                    }
                }
            } else {
                // If server manager is not required, only check provisioned status
                $all_status_complete = ($server_metadata['arsol_server_provisioned_status'] == 2);
            }

            // If all required statuses are 2, proceed to complete the process
            if ($all_status_complete) {
                update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'complete');
                $subscription->update_status('active');
                $subscription->add_order_note("Server provisioning and deployment complete. Subscription activated.");
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server setup complete. Subscription activated.');
                return;
            } else {
                // If not complete, trigger provisioning
                update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'tripped');
                $subscription->update_status('on-hold');
                $this->start_server_provision($this->subscription);
                $subscription->add_order_note("Server provisioning failed or incomplete. Retrying deployment.");
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker tripped. Retrying deployment.');
                return;
            }

        } catch (Exception $e) {
            // Log the exception and notify for manual intervention
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Exception occurred: ' . $e->getMessage());
            $subscription->update_status('on-hold');
            $subscription->add_order_note("An error occurred: " . $e->getMessage() . ". Manual intervention required.");
        }
    }

    // Function to start the server provisioning process (stub for now)
    public function start_server_provision($subscription) {
        // Server provisioning logic here (e.g., invoking an API or service to provision the server)
        error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting server provisioning for subscription: ' . $subscription->get_id());
        // After provisioning, re-check server metadata, and update statuses accordingly
    }
}
