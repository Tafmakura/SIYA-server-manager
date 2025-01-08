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
        //$this->subscription = wcs_get_subscription($subscription_id);
       // $this->server = ServerPost::get_server_post_by_subscription($subscription_id);
       add_action('woocommerce_subscription_status_active', array($this, 'subscription_circuit_breaker'), 20, 1);

    }

    public function subscription_circuit_breaker($subscription) {

       

     

        try {
            
            $this->subscription = $subscription;
            $this->subscription_id = $this->subscription->get_id();
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting subscription circuit breaker check'.$this->subscription_id);
    
            // Get server post ID linked to the subscription
            $server_post_id = $subscription->get_meta( 'arsol_linked_server_post_id', true );
            $this->server_post_id = $server_post_id;

            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server post ID: ' . $server_post_id);
            
            // Check if server post ID is found
            if (!$server_post_id) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] No linked server post ID found for subscription');
                return;
            }

            // Get server metadata
            $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
            $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);
            $requires_server_manager = get_post_meta($this->server_post_id, 'arsol_connect_server_manager', true);
    
            error_log(sprintf('[SIYA Server Manager - ServerCircuitBreaker] Status - Provisioned: %s, Deployed: %s, Requires Manager: %s', 
                $is_provisioned ? 'true' : 'false',
                $is_deployed ? 'true' : 'false',
                $requires_server_manager
            ));

            // Fully provisioned and deployed
            if ($is_provisioned && $is_deployed) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server setup is fine.');
                update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'complete');
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker status updated to complete.');

                return;
            }

        

            // Provisioned but not deployed
            if (!$is_provisioned && !$is_deployed || $is_provisioned && !$is_deployed ) {
                
                if ( $is_provisioned && !$is_deployed && $requires_server_manager === 'no') {
                    update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'okay');
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker status updated to okay.');
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server provisioned but deployment not required. No action needed.');
                    return;
                } else {
                    update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'tripped');
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker tripped.');    
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server not provisioned and not deployed. Attempting deployment.');
                    $subscription->update_status('on-hold');
                    $this->start_server_provision($this->subscription);
                    $subscription->add_order_note("Server provisioned but deployment failed. Retrying deployment.");
                    return;
                }
                
            }
    
            // Catch-all case: Unexpected state
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Unexpected state encountered. Manual intervention required.');
            update_post_meta($this->server_post_id, 'arsol_server_circuit_breaker_status', 'tripped');
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server circuit breaker tripped.');  
            $subscription->update_status('on-hold');
            $subscription->add_order_note("Unexpected server state encountered. Manual intervention required.");
    
        } catch (Exception $e) {
            // Log the exception and notify for manual intervention
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Exception occurred: ' . $e->getMessage());
            $subscription->update_status('on-hold');
            $subscription->add_order_note("An error occurred: " . $e->getMessage() . ". Manual intervention required.");
        }
    
        /*
        // Refresh the page after processing
        echo "<script type='text/javascript'>
            setTimeout(function(){
                location.reload();
            }, 1000);
        </script>";
        */
        

    }

}