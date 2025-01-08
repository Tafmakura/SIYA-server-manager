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
        
        $this->subscription_id = $subscription->get_id();

        error_log('[SIYA Server Manager - ServerCircuitBreaker] Starting subscription circuit breaker check');


        // Get the arsol_linked_server_post_id from the subscription
        $server_post_id = get_post_meta($this->subscription_id, 'arsol_linked_server_post_id', true);

        if (!$server_post_id) {
            error_log('[SIYA Server Manager - ServerCircuitBreaker] No linked server post ID found in subscription');
            return;
        }


        $this->subscription = $subscription;
        $this->server_post_id = $server_post_id;
        $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
        $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);

        error_log(sprintf('[SIYA Server Manager - ServerCircuitBreaker] Status check - Provisioned: %s, Deployed: %s', 
            $is_provisioned ? 'true' : 'false',
            $is_deployed ? 'true' : 'false'
        ));

        if($is_provisioned && $is_deployed){
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server is provisioned and deployed, no need to disconnect');
            return;
        
        }elseif(!$is_provisioned && !$is_deployed){ 
            $this->provision_server($this->subscription);
            $subscription->update_status('on-hold');
            $subscription->add_order_note(
                "Server is not provisioned. Retrying provisioning process and setting status to on-hold."
            );
            return;
        }elseif($is_provisioned && !$is_deployed){
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Server is provisioned but not deployed, initiating deployment process');
            $this->deploy_to_runcloud_and_update_metadata($this->server_post_id, $this->subscription);
            $subscription->update_status('on-hold');
            $subscription->add_order_note(
                "Server is provisioned but deployment failed. Retrying deployment process and setting status to on-hold."
            );
            return;
        }else{
            error_log('[SIYA Server Manager - ServerCircuitBreaker] Subscription:. '.$this->subscription_id.' reequires attention');
            $subscription->add_order_note(
                "Server is provisioned but deployment failed. Retrying deployment process and setting status to on-hold."
            );
            return;
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