<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\WoocommerceSubscriptions\ServerOrchestrator;

class ServerCircuitBreaker extends ServerOrchestrator {

    // Circuit breaker states
    const CIRCUIT_BREAKER_TRIPPED = -1;    // Failed state
    const CIRCUIT_BREAKER_CLOSED = 0;      // Initial/Success state
    const CIRCUIT_BREAKER_HALF_OPEN = 1;   // In-progress state

    public function __construct() {
        // Hook into WooCommerce subscription status change to active
       // add_action('woocommerce_subscription_status_active', [$this, 'test_circuit'], 15, 1);
    }

    /**
     * Handles the entire circuit breaker logic for a subscription.
     *
     * @param \WC_Subscription $subscription The subscription object.
     */
    public function test_circuit($subscription) {
        error_log("[SIYA Debug] Starting test_circuit for subscription ID: " . $subscription->get_id());

        try {
            // Retrieve the subscription ID and linked server post ID
            $subscription_id = $subscription->get_id();
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);

            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Testing circuit breaker for subscription {$subscription_id} and server post ID {$server_post_id}.");

            // Initialise the circuit breaker state if it does not exist
            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
            $retry_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_retry_counter', true);
            $reset_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_reset_counter', true);

            error_log("[SIYA Debug] Current state - Circuit Breaker: {$circuit_breaker}, Retry Counter: {$retry_counter}, Reset Counter: {$reset_counter}");

            // Define metadata keys for server-related operations
            $server_metadata_keys = [
                '_arsol_state_05_server_post',
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
                error_log("[SIYA Debug] Metadata {$key}: " . $server_metadata[$key]);
            }

            // Check if server manager is required
            $server_manager_required = $server_metadata['_arsol_server_manager_required'] ?? 'no';
            error_log("[SIYA Debug] Server manager required: {$server_manager_required}");

            // Validate whether all required metadata values are set to 2
            $all_status_complete = true;
            if ($server_manager_required === 'yes') {
                foreach ($server_metadata_keys as $key) {
                    if ($key !== '_arsol_server_manager_required' && ($server_metadata[$key] ?? null) != 2) {
                        $all_status_complete = false;
                        error_log("[SIYA Debug] Incomplete status found for {$key}: " . ($server_metadata[$key] ?? 'null'));
                        break;
                    }
                }
            } else {
                $required_keys = [
                    '_arsol_state_05_server_post',
                    '_arsol_state_10_provisioning',
                    '_arsol_state_20_ip_address',
                ];
                foreach ($required_keys as $key) {
                    if (($server_metadata[$key] ?? null) != 2) {
                        $all_status_complete = false;
                        error_log("[SIYA Debug] Incomplete status found for {$key}: " . ($server_metadata[$key] ?? 'null'));
                        break;
                    }
                }
            }

            error_log("[SIYA Debug] All status complete: " . ($all_status_complete ? 'true' : 'false'));

            if ($all_status_complete) {
                error_log("[SIYA Debug] Resetting circuit breaker - all statuses complete");
                $this->reset_circuit_breaker($subscription, ["Server provisioning and deployment complete."]);
            } else {
                error_log("[SIYA Debug] Setting circuit breaker to half-open - incomplete statuses found");
                $this->half_open_circuit_breaker($subscription);
            }

        } catch (\Exception $e) {
            error_log("[SIYA Debug] Exception caught in test_circuit: " . $e->getMessage());
            $this->handle_exception($e);
        }
    }

    public static function trip_circuit_breaker(\WC_Subscription $subscription, array $details = []) {
        error_log("[SIYA Debug] Tripping circuit breaker for subscription: " . $subscription->get_id());
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_TRIPPED);
            $subscription->add_order_note("Circuit breaker tripped. Manual intervention required, this server may have failed or may have degraded perfomance. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] WARNING: Circuit breaker tripped for subscription {$subscription->get_id()}. Details: " . json_encode($details));
        }
    }

    public function half_open_circuit_breaker(\WC_Subscription $subscription) {
        error_log("[SIYA Debug] Setting circuit breaker to half-open for subscription: " . $subscription->get_id());
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        // Check to see if maintanace is happening on a new server or an existing server
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_HALF_OPEN);
            $subscription->add_order_note("Circuit breaker set to half-open (in progress).");
            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker set to half-open for subscription {$subscription->get_id()}.");
        
            $this->start_server_repair($subscription);

        } else {

            error_log("[SIYA Server Manager - ServerCircuitBreaker] ERROR: Server post ID not found for subscription {$subscription->get_id()}.");
            
            //privision_server
            $this->start_server_provision($subscription);
        }
    }

    public function reset_circuit_breaker(\WC_Subscription $subscription, array $details = []) {
        error_log("[SIYA Debug] Resetting circuit breaker for subscription: " . $subscription->get_id());
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_CLOSED);
            $subscription->add_order_note("Circuit breaker reset and subscription activated. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker reset for subscription {$subscription->get_id()}. Details: " . json_encode($details));
            
            $this->start_server_powerup($subscription);
        }
    }
}
