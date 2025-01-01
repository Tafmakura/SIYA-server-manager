<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud\Runcloud;
use Siya\Integrations\ServerProviders\Hetzner\Hetzner;

class ServerOrchestrator {
   
    const POST_TYPE = 'server';

    private $subscription;
    private $subscription_id;
    private $server_post_id;
    public $server_provider;
    public $server_manager;
    public $server_plan_identifier;
    private $runcloud;
    private $hetzner;

    public function __construct() {
      
        // Add hooks for subscription status changes
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'provision_and_deploy_server'), 20, 1);
       
        error_log('HOYO!!!! ');
        add_action('woocommerce_subscription_status_active', array($this, 'subscription_circuit_breaker'), 10, 1);
        add_action('woocommerce_subscription_status_updated', [$this, 'subscription_circuit_breaker'], 10, 1);
    }

    public function check_existing_server() {
        $server_post = new ServerPost();
        $server = $server_post->get_server_post_by_subscription_id($this->subscription_id);
        if ($server) {
           
            error_log('[SIYA Server Manager] Found existing server: ' . $server->ID);
            return $server->ID;
        }

        error_log('[SIYA Server Manager] No existing server found');
        return false;
    }

    public function provision_and_deploy_server($subscription) {
        try {

        // Check if server post already exists
        error_log('[SIYA Server Manager] Checking for existing server');
    
        $existing_server_post = $this->check_existing_server();
        if ($existing_server_post) {
            $this->server_post_id = $existing_server_post->ID;
        }
   
        // Get current status flags
        $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
        $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);
        
        // Step 1: Create server post only if it doesn't exist
        if (!$this->server_post_id) {
            error_log('[SIYA Server Manager] creating new server post');
            $server_post = $this->create_and_update_server_post($subscription);
        } else {
            error_log('[SIYA Server Manager] Server post already exists, skipping Step 1');
            $this->server_post_id = $existing_server->ID;
        }

        // Step 2: Provision Hetzner server if not already provisioned
        $server_data = null;
        if (!$is_provisioned) {
            // Instantiate Hetzner only if needed
            $this->hetzner = new Hetzner();  
            $server_data = $this->provision_hetzner_server($server_post, $subscription);
        } else {
            error_log('[SIYA Server Manager] Server already provisioned, skipping Step 2');
            // Get existing server data for Step 3
            $server_data = [
                'server' => [
                    'public_net' => [
                        'ipv4' => ['ip' => get_post_meta($this->server_post_id, 'arsol_server_ipv4', true)]
                    ]
                ]
            ];
        }

        // Step 3: Deploy to RunCloud if not already deployed
        if (!$is_deployed) {
            // Instantiate RunCloud only if needed
            $this->runcloud = new Runcloud();  
            $this->deploy_to_runcloud_and_update_metadata($server_post, $server_data, $subscription);
        } else {
            error_log('[SIYA Server Manager] Server already deployed, skipping Step 3');
        }

        } catch (\Exception $e) {
            // Log the full error message
            error_log(sprintf(
                '[SIYA Server Manager] Error in subscription %d:%s%s',
                $this->subscription_id,
                PHP_EOL,
                $e->getMessage()
            ));
    
            // Add detailed note to subscription
            $subscription->add_order_note(sprintf(
                "Error occurred during server provisioning:%s%s",
                PHP_EOL,
                $e->getMessage()
            ));
    
            $subscription->update_status('on-hold');
        }
    }

    // Step 1: Create server post and update server metadata
    private function create_and_update_server_post($subscription) {
        $server_post = new ServerPost();
        $server_name = 'ARSOL' . $this->subscription_id;
        $post_id = $server_post->create_server_post($this->subscription_id);
        $this->server_post_id = $post_id;

        // Update server post meta
        $server_post->update_meta_data($post_id, [
            'arsol_server_post_name' => $server_name,
            'arsol_server_post_creation_date' => current_time('mysql'),
            'arsol_server_subscription_id' => $this->subscription_id,
            'arsol_server_status' => 'pending',
            'arsol_server_connection_status' => 'initializing',
            'arsol_server_provisioned_status' => 1,
        ]);

        $subscription->add_order_note(
            sprintf(
                'Server post created successfully.%sPost ID: %d%sServer Name: %s',
                PHP_EOL,
                $post_id,
                PHP_EOL,
                $server_name
            )
        );

        return $server_post;
    }

    // Step 2: Provision Hetzner server and update server post metadata
    private function provision_hetzner_server($server_post, $subscription) {
        $server_name = 'ARSOL' . $this->subscription_id;
        $server_data = $this->hetzner->provision_server($server_name);

        if (!$server_data) {
            $error_response = $this->hetzner->get_last_response();
            $error_body = json_encode($error_response, JSON_PRETTY_PRINT);
            $error_message = sprintf(
                "Failed to provision Hetzner server%s%sAPI Response:%s%s",
                PHP_EOL, PHP_EOL, PHP_EOL, $error_body
            );
            $subscription->add_order_note($error_message);
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            throw new \Exception($error_message);
        }

        $server = $server_data['server'];
        $success_message = sprintf(
            "Hetzner server provisioned successfully! %s" .
            "Server Name: %s%s" .
            "IP: %s%s" .
            "Created: %s%s" .
            "Server Type: %s%s" .
            "Location: %s",
            PHP_EOL,
            $server_name, PHP_EOL,
            $server['public_net']['ipv4']['ip'], PHP_EOL,
            $server['created'], PHP_EOL,
            $server['server_type']['name'], PHP_EOL,
            $server['datacenter']['location']['name']
        );
        $subscription->add_order_note($success_message);

        // Update server post metadata
        $metadata = [
            'arsol_server_provider' => 'hetzner',
            'arsol_server_manager' => 'runcloud',
            'arsol_server_plan_identifier' => $this->server_plan_identifier,
            'arsol_provisioned_server_id' => $server['id'],
            'arsol_server_ipv4' => $server['public_net']['ipv4']['ip'],
            'arsol_server_ipv6' => $server['public_net']['ipv6']['ip'],
            'arsol_server_location' => $server['datacenter']['location']['name'],
            'arsol_server_server_type' => $server['server_type']['name'],
            'arsol_server_created_date' => $server['created'],
            'arsol_server_provisioned_status' => 1,
            'arsol_server_connection_status' => 'provisioning'
        ];

        $server_post->update_meta_data($this->server_post_id, $metadata);
        $subscription->add_order_note(sprintf(
            "Server metadata updated successfully:%s%s",
            PHP_EOL,
            print_r($metadata, true)
        ));

        return $server_data;
    }

    // Step 3: Deploy to RunCloud and update server metadata
    private function deploy_to_runcloud_and_update_metadata($server_post, $server_data, $subscription) {
        error_log(sprintf('[SIYA Server Manager] Step 5: Starting deployment to RunCloud for subscription %d', $this->subscription_id));

        $server = $server_data['server'];
        $server_name = 'ARSOL' . $this->subscription_id;
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = 'hetzner';

        // Deploy to RunCloud
        $runcloud_response = $this->runcloud->create_server_in_server_manager(
            $server_name,
            $server['public_net']['ipv4']['ip'],
            $web_server_type,
            $installation_type,
            $provider
        );

        if (is_wp_error($runcloud_response)) {
            error_log('[SIYA Server Manager] RunCloud API Error: ' . $runcloud_response->get_error_message());
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed (WP_Error).\nError message: %s\nFull response: %s",
                $runcloud_response->get_error_message(),
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error
        }

        $response_body_decoded = json_decode($runcloud_response['body'], true);

        if (!isset($runcloud_response['status']) || $runcloud_response['status'] != 200) {
            error_log('[SIYA Server Manager] RunCloud deployment failed with status: ' . $runcloud_response['status']);
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed.\nStatus: %s\nResponse body: %s\nFull response: %s",
                $runcloud_response['status'],
                $runcloud_response['body'],
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error
        }

        // Successful API response
        error_log('[SIYA Server Manager] RunCloud deployment successful');
        $subscription->add_order_note(sprintf(
            "RunCloud deployment successful with status: %s\nResponse body: %s",
            $runcloud_response['status'],
            $runcloud_response['body']
        ));

        // Update server metadata
        $server_post->update_meta_data($this->server_post_id, [
            'arsol_server_deployed_server_id' => $response_body_decoded['id'] ?? null,
            'arsol_server_deployment_date' => current_time('mysql'),
            'arsol_server_deployed_status' => 1,
            'arsol_server_connection_status' => 0
        ]);
       
        $subscription->update_status('active');

        error_log(sprintf('[SIYA Server Manager] Step 5: Deployment to RunCloud completed for subscription %d', $this->subscription_id));

        // Refresh the page after processing
        if (is_admin()) {
            echo "<script type='text/javascript'>
                    setTimeout(function(){
                        location.reload();
                    }, 1000);
                </script>";
        }
    
    }


    public function subscription_circuit_breaker($subscription) {
       
        error_log('[SIYA Server Manager] Starting subscription circuit breaker check');

        if (!is_admin()) {
            return;
        }

        $subscription_id = $subscription->get_id();
        $post_id = get_post_meta($subscription_id, 'arsol_server_post_id', true);
        $is_provisioned = get_post_meta($post_id, 'arsol_server_deployed_status', true);
        $is_deployed = get_post_meta($post_id, 'arsol_server_provisioned_status', true);

        error_log(sprintf('[SIYA Server Manager] Status check - Provisioned: %s, Deployed: %s', 
            $is_provisioned ? 'true' : 'false',
            $is_deployed ? 'true' : 'false'
        ));

        if($is_provisioned && $is_deployed){
            error_log('[SIYA Server Manager] Server is already provisioned and deployed, exiting circuit breaker');
            return;
        }

        error_log('[SIYA Server Manager] Setting subscription to on-hold status');
        $subscription->update_status('on-hold');
        $subscription->add_order_note(
            "Subscription status set to on-hold. Server provisioning and deployment in progress."
        );

        error_log('[SIYA Server Manager] Initiating server provision and deploy process');
        $this->provision_and_deploy_server($subscription);

        error_log('[SIYA Server Manager] Circuit breaker process completed');

        // Refresh the page after processing
        echo "<script type='text/javascript'>
                setTimeout(function(){
                    location.reload();
                }, 1000);
            </script>";
      
    }


}



