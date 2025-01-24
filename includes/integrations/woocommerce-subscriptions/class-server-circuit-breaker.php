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

            // Initialise the circuit breaker state if it does not exist
            $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
            $retry_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_retry_counter', true);
            $reset_counter   = (int) get_post_meta($server_post_id, '_arsol_state_00_reset_counter', true);

            // If subscription is being activated and circuit is tripped and retry is at max, increment _arsol_state_00_reset_counter
           
           /* TODO Delete
            if ($subscription->has_status('active')
                && (int) $circuit_breaker === self::CIRCUIT_BREAKER_TRIPPED
                && $retry_counter >= 2
            ) {
                $reset_counter++;
                update_post_meta($server_post_id, '_arsol_state_00_reset_counter', $reset_counter);
                update_post_meta($server_post_id, '_arsol_state_00_retry_counter', 0);
                error_log("[SIYA] Reset counter incremented for server post ID {$server_post_id}.");
            }
            */

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
                $this->half_open_circuit_breaker($subscription);
            
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
            $subscription->add_order_note("Circuit breaker tripped. Manual intervention required, this server may have failed or may have degraded perfomance. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] WARNING: Circuit breaker tripped for subscription {$subscription->get_id()}. Details: " . json_encode($details));
            
            // In this class we intentionally do not power down the server because we are willing to tolerate degraded perfomance or false flags, the circuit breakersimply raises attention and does not take action 
        }
    }

    public static function half_open_circuit_breaker(\WC_Subscription $subscription) {
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_HALF_OPEN);
            $subscription->add_order_note("Circuit breaker set to half-open (in progress).");
            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker set to half-open for subscription {$subscription->get_id()}.");
        
            $this->start_server_repair($subscription);
        }
    }

    public static function reset_circuit_breaker(\WC_Subscription $subscription, array $details = []) {
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        if ($server_post_id) {
            update_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', self::CIRCUIT_BREAKER_CLOSED);
           
        
            $subscription->add_order_note("Circuit breaker reset and subscription activated. " . json_encode($details));
            error_log("[SIYA Server Manager - ServerCircuitBreaker] INFO: Circuit breaker reset for subscription {$subscription->get_id()}. Details: " . json_encode($details));
            
            $this->start_server_powerup($subscription);

        }
    }
}
