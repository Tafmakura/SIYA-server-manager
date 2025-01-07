<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;

class ServerCircuitBreaker {
    private $subscription;
    private $server;
    private $max_attempts = 3;
    private $check_interval = 300; // 5 minutes

    public function __construct($subscription_id) {
        $this->subscription = wcs_get_subscription($subscription_id);
        $this->server = ServerPost::get_server_post_by_subscription($subscription_id);
    }

    public function subscription_circuit_breaker($subscription) {

        $this->server_product_id = $this->extract_server_product_from_subscription($subscription);

        if (!$this->server_product_id) {
            error_log('[SIYA Server Manager - ServerOrchestrator] No server product found in subscription');
            return;
        }
       
        error_log('[SIYA Server Manager - ServerOrchestrator] Starting subscription circuit breaker check');

        if (!is_admin()) {
            return;
        }
        $server_post_instance = new ServerPost;
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
        $this->server_post_id = $server_post->post_id;

        $this->subscription_id = $subscription->get_id();
        $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
        $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);

        error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Status check - Provisioned: %s, Deployed: %s', 
            $is_provisioned ? 'true' : 'false',
            $is_deployed ? 'true' : 'false'
        ));

        if($is_provisioned && $is_deployed){
            error_log('[SIYA Server Manager - ServerOrchestrator] Server is provisioned and deployed, no need to disconnect');
            return;
        
        }else{

            error_log('[SIYA Server Manager - ServerOrchestrator] Setting subscription to on-hold status');
            $subscription->add_order_note(
                "Subscription status set to on-hold. Server provisioning and deployment in progress."
            );
    
            $subscription->update_status('on-hold');

            error_log('[SIYA Server Manager - ServerOrchestrator] Initiating server provision and deploy process');
            $this->provision_and_deploy_server($subscription);


            // Refresh the page after processing
            echo "<script type='text/javascript'>
                setTimeout(function(){
                    location.reload();
                }, 1000);
            </script>";


        }

    }

}