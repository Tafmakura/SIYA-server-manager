<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\WoocommerceSubscriptions\ServerOrchestrator;

class ServerCircuitBreaker extends ServerOrchestrator {
    public function __construct() {
        // Hook into WooCommerce subscription status change to active
        add_action('woocommerce_subscription_status_active', [$this, 'subscription_circuit_breaker'], 15, 1);
    }

    /**
     * Handles the entire circuit breaker logic for a subscription.
     *
     * @param \WC_Subscription $subscription The subscription object.
     */
    public function subscription_circuit_breaker($subscription) {
        try {
            // Retrieve the subscription ID and linked server post ID
            $subscription_id = $subscription->get_id();
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);

            if (!$server_post_id) {
                // Log and exit if no linked server post ID is found
                error_log("[SIYA Server Manager - ServerCircuitBreaker] ERROR: No linked server post ID found for subscription {$subscription_id}");
                return;
            }

            // Define metadata keys for server-related operations
            $server_metadata_keys = [
                '_arsol_state_10_provisioning',
                '_arsol_state_20_ip_address',
                '_arsol_state_30_deployment',
                '_arsol_state_40_firewall_rules',
                '_arsol_state_50_script_execution',
                '_arsol_state_60_script_installation',
                '_arsol_state_70_manager_connection',
                '_arsol_server_manager_required',
            ];

            // Fetch all metadata for the linked server post
            $server_metadata = [];
            foreach ($server_metadata_keys as $key) {
                $server_metadata[$key] = get_post_meta($server_post_id, $key, true);
            }

            // Check if server manager is required
            $server_manager_required = $server_metadata['_arsol_server_manager_required'] ?? 'no';

            // Validate whether all required metadata values are set to 2
            $all_status_complete = true;
            if ($server_manager_required === 'yes') {
                foreach ($server_metadata_keys as $key) {
                    if ($key !== '_arsol_server_manager_required' && ($server_metadata[$key] ?? null) != 2) {
                        $all_status_complete = false;
                        break;
                    }
                }
            } else {
                $all_status_complete = ($server_metadata['_arsol_state_10_provisioning'] ?? null) == 2;
            }

            if ($all_status_complete) {
                // If all statuses are valid, mark as complete and activate the subscription
                update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', 2);
                $subscription->update_status('active');
                $subscription->add_order_note("Server provisioning and deployment complete. Subscription activated.");
                error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Subscription {$subscription_id} activated successfully.");
            } else {
                // If not, mark the circuit breaker as tripped and initiate provisioning
                update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', -1);
                $subscription->update_status('on-hold');
                $this->start_server_provision($subscription);
                $subscription->add_order_note("Server provisioning failed or incomplete. Retrying deployment.");
                error_log("[SIYA Server Manager - ServerCircuitBreaker] WARNING: Triggered provisioning for subscription {$subscription_id}.");
            }

        } catch (\Exception $e) {
            // Handle exceptions gracefully
            error_log("[SIYA Server Manager - ServerCircuitBreaker] ERROR: Exception occurred: {$e->getMessage()}");
            $subscription->update_status('on-hold');
            $subscription->add_order_note("An error occurred: {$e->getMessage()}. Manual intervention required.");
        }
    }
}
