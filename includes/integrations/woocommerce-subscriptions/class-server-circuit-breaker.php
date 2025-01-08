<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\WoocommerceSubscriptions\ServerOrchestrator;

class ServerCircuitBreaker extends ServerOrchestrator {
    public $subscription;
    public $server;
    public $max_attempts = 3;
    public $check_interval = 300; // 5 minutes
    public $server_product_id;
    public $server_post_id;
    public $subscription_id;

    public function __construct() {
        //$this->subscription = wcs_get_subscription($subscription_id);
       // $this->server = ServerPost::get_server_post_by_subscription($subscription_id);

        add_action('woocommerce_subscription_status_active', array($this, 'subscription_circuit_breaker'), 20, 1);

    }

    public function subscription_circuit_breaker($subscription) {
        if (!is_admin()) {
            return;
        }
    
        try {
            $this->subscription_id = $subscription->get_id();
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting subscription circuit breaker check');
    
            // Get server post ID linked to the subscription
            $server_post_id = get_post_meta($this->subscription_id, 'arsol_linked_server_post_id', true);
    
            if (!$server_post_id) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] No linked server post ID found for subscription');
                return;
            }
    
            $this->subscription = $subscription;
            $this->server_post_id = $server_post_id;
    
            // Get server metadata
            $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
            $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);
            $requires_server_manager = get_post_meta($this->server_post_id, 'arsol_connect_server_manager', true);
    
            error_log(sprintf('[SIYA Server Manager - ServerCircuitBreaker] Status - Provisioned: %s, Deployed: %s, Requires Manager: %s', 
                $is_provisioned ? 'true' : 'false',
                $is_deployed ? 'true' : 'false',
                $requires_server_manager
            ));
    
            // Handle anomaly: Deployed but not provisioned
            if (!$is_provisioned && $is_deployed) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Anomaly detected: Server is deployed but not provisioned. Manual intervention required.');
                $subscription->update_status('on-hold');
                $subscription->add_order_note("Anomaly detected: Server is deployed but not provisioned. Manual intervention required.");
                return;
            }
    
            // Fully provisioned and deployed
            if ($is_provisioned && $is_deployed) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server setup complete.');
                return;
            }
    
            // Provisioned but not deployed
            if ($is_provisioned && !$is_deployed) {
                if ($requires_server_manager === 'no') {
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server provisioned but deployment not required. No action needed.');
                    return;
                } else {
                    error_log('[SIYA Server Manager - ServerCircuitBreaker] Server provisioned but deployment failed. Attempting deployment.');
                    $this->deploy_to_runcloud_and_update_metadata($this->server_post_id, $this->subscription);
                    $subscription->add_order_note("Server provisioned but deployment failed. Retrying deployment.");
                    $subscription->update_status('on-hold');
                }
                return;
            }
    
            // Not provisioned or deployed
            if (!$is_provisioned && !$is_deployed) {
                error_log('[SIYA Server Manager - ServerCircuitBreaker] Server not provisioned. Initiating provisioning process.');
                $this->provision_server($this->subscription);
                $subscription->update_status('on-hold');
                $subscription->add_order_note("Server not provisioned. Retrying provisioning process and placing subscription on-hold.");
                return;
            }
    
            // Catch-all case: Unexpected state
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Unexpected state encountered. Manual intervention required.');
            $subscription->update_status('on-hold');
            $subscription->add_order_note("Unexpected server state encountered. Manual intervention required.");
    
        } catch (Exception $e) {
            // Log the exception and notify for manual intervention
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Exception occurred: ' . $e->getMessage());
            $subscription->update_status('on-hold');
            $subscription->add_order_note("An error occurred: " . $e->getMessage() . ". Manual intervention required.");
        }
    
    
        /*
        error_log('[SIYA Server Manager - ServerCircuitBreaker] Setting subscription to on-hold status');
        $subscription->add_order_note(
            "Subscription status set to on-hold. Server provisioning and deployment in progress."
        );

        $subscription->update_status('on-hold');

        error_log('[SIYA Server Manager - ServerCircuitBreaker] Initiating server provision and deploy process');
        
        
        // Provision the server
        $this->provision_server($this->subscription);

        // Refresh the page after processing
        echo "<script type='text/javascript'>
            setTimeout(function(){
                location.reload();
            }, 1000);
        </script>";
        */
        

    }

}