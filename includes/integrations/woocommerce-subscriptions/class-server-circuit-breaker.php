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

        try {
            // Retrieve the subscription ID and linked server post ID
            $subscription_id = $subscription->get_id();
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);

            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Testing circuit breaker for subscription {$subscription_id} and server post ID {$server_post_id}.");

            if (!$server_post_id) {
                // Log and exit if no linked server post ID is found
                error_log("[SIYA Server Manager - ServerCircuitBreaker] ERROR: No linked server post ID found for subscription {$subscription_id}");
                return;
            }

            // Initialise the circuit breaker state if it does not exist
            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
            $retry_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_retry_counter', true);
            $reset_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_reset_counter', true);

            if ($circuit_breaker === '') {
                // If the meta key does not exist, add it with the value 0 (closed state)
                update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_CLOSED);
                error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker state initialized to closed for server post ID {$server_post_id}.");
            }

            // If subscription is being activated and circuit is tripped and retry is at max, increment _arsol_state_00_reset_counter
            if ($subscription->has_status('active')
                && (int) $circuit_breaker === self::CIRCUIT_BREAKER_TRIPPED
                && $retry_counter >= 2
            ) {
                $reset_counter++;
                update_post_meta($server_post_id, '_arsol_state_00_reset_counter', $reset_counter);
                update_post_meta($server_post_id, '_arsol_state_00_retry_counter', 0);
                error_log("[SIYA] Reset counter incremented for server post ID {$server_post_id}.");
            }

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
                $required_keys = [
                    '_arsol_state_05_server_post',
                    '_arsol_state_10_provisioning',
                    '_arsol_state_20_ip_address',
                ];
                foreach ($required_keys as $key) {
                    if (($server_metadata[$key] ?? null) != 2) {
                        $all_status_complete = false;
                        break;
                    }
                }
            }

            if ($all_status_complete) {
                // Reset the circuit breaker
                $this->reset_circuit_breaker($subscription, ["Server provisioning and deployment complete."]);
            } else {
                // If not, mark the circuit breaker as half-open (in progress) and initiate provisioning
                update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_HALF_OPEN);
                $subscription->update_status('on-hold');
                
                // Increment retry counter if under 2; else trip breaker (no further auto-retry)
                if ($retry_counter < 2) {
                    $retry_counter++;
                    update_post_meta($server_post_id, '_arsol_state_00_retry_counter', $retry_counter);
                    $this->start_server_provision($subscription);
                    $subscription->add_order_note("Server provisioning failed or incomplete. Retrying deployment.");
                    error_log("[SIYA Server Manager - ServerCircuitBreaker] WARNING: Triggered provisioning for subscription {$subscription_id}.");
                } else {
                    update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_TRIPPED);
                    error_log("[SIYA] Circuit breaker tripped due to max retries for server post ID {$server_post_id}.");
                }
            }

        } catch (\Exception $e) {
            // Replaced direct circuit breaker call with handle_exception
            $this->handle_exception($e);
        }
    }

    public static function trip_circuit_breaker(\WC_Subscription $subscription, array $details = []) {
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_TRIPPED);
            $subscription->update_status('on-hold');
            $subscription->add_order_note("Circuit breaker tripped. Manual intervention required. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] WARNING: Circuit breaker tripped for subscription {$subscription->get_id()}. Details: " . json_encode($details));
        }
    }

    public static function reset_circuit_breaker(\WC_Subscription $subscription, array $details = []) {
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_CLOSED);
            $subscription->update_status('active');
            $subscription->add_order_note("Circuit breaker reset and subscription activated. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker reset for subscription {$subscription->get_id()}. Details: " . json_encode($details));
        }
    }
}
