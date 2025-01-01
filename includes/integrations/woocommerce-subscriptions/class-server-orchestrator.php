<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud\Runcloud;
use Siya\Integrations\ServerProviders\Hetzner\Hetzner;

class ServerOrchestrator {
   
    const POST_TYPE = 'server';
    const META_PREFIX = 'arsol_server_';

    private $subscription;
    private $subscription_id;
    public $server_provider;
    public $server_manager;
    public $server_plan_identifier;
    private $runcloud;
    private $hetzner;

    public function __construct($subscription) {
        $this->subscription = $subscription;
        $this->subscription_id = $subscription->get_id();
        
        // Debug logging
        error_log(sprintf(
            '[SIYA Server Manager] Constructor - Subscription ID: %s',
            $this->subscription_id
        ));
        
        $this->runcloud = new Runcloud();
        $this->hetzner = new Hetzner();
        
        if ($this->subscription_id) {
            error_log('[SIYA Server Manager] Checking for existing server');
            $this->check_existing_server();
        }

        // Add hooks for subscription status changes
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'provision_and_deploy_server'), 20, 1);
        add_action('woocommerce_subscription_status_active', array($this, 'force_on_hold_and_check_server_status'), 10, 1);
    }

    public function check_existing_server() {
        $server_post = new ServerPost();
        $server = $server_post->get_server_post_by_subscription_id($this->subscription_id);
        if ($server) {
            error_log('[SIYA Server Manager] Found existing server: ' . $server->ID);
            $this->server_provider = get_post_meta($server->ID, 'arsol_server_provider', true);
            $this->server_manager = get_post_meta($server->ID, 'arsol_server_manager', true);
            $this->server_plan_identifier = get_post_meta($server->ID, 'arsol_server_plan_identifier', true);
        }
    }

    public function provision_and_deploy_server($subscription) {
        $subscription_id = $this->subscription_id;
    
        try {
            // Step 1: Create server post and Step 2: Update server post meta
            $server_post = new ServerPost();
            $post_id = $this->create_and_update_server_post($subscription_id, $server_post);
    
            // Step 2: Provision Hetzner server and Step 4: Update server post metadata
            $server_data = $this->provision_hetzner_and_update_metadata($server_post, $post_id, $subscription);
            if (!$server_data) {
                throw new \Exception("Failed to provision Hetzner server.");
            }

            // Step 3: Deploy to RunCloud (now includes handling RunCloud response)
            $this->deploy_to_runcloud($post_id, $server_data, $server_post, $subscription);
    
        } catch (\Exception $e) {
            // Log the full error message
            error_log(sprintf(
                '[SIYA Server Manager] Error in subscription %d:%s%s',
                $subscription_id,
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

    // Step 1: Create server post
    private function create_and_update_server_post($subscription_id, $server_post) {
        $server_name = 'ARSOL' . $subscription_id;
        $post_id = $server_post->create_server_post($subscription_id);

        // Step 2: Update server post metadata
        $server_post->update_meta_data($post_id, [
            'arsol_server_post_name' => $server_name,
            'arsol_server_post_creation_date' => current_time('mysql'),
            'arsol_server_subscription_id' => $subscription_id,
            'arsol_server_status' => 'pending',
            'arsol_server_connection_status' => 'initializing',
            'arsol_server_provisioned_status' => 1,
        ]);
        
        return $post_id;
    }

    // Step 2: Provision Hetzner server and Step 4: Update server post metadata
    private function provision_hetzner_and_update_metadata($server_post, $post_id, $subscription) {
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
        $metadata = [
            'arsol_server_provider' => 'hetzner',
            'arsol_server_manager' => 'runcloud',
            'arsol_server_plan_identifier' => $this->server_plan_identifier,
            'arsol_server_server_id' => $server['id'],
            'arsol_server_ipv4' => $server['public_net']['ipv4']['ip'],
            'arsol_server_ipv6' => $server['public_net']['ipv6']['ip'],
            'arsol_server_location' => $server['datacenter']['location']['name'],
            'arsol_server_server_type' => $server['server_type']['name'],
            'arsol_server_created_date' => $server['created'],
            'arsol_server_provisioned_status' => 1,
            'arsol_server_connection_status' => 'provisioning'
        ];

        $server_post->update_meta_data($post_id, $metadata);
        
        return $server;
    }

    // Step 3: Deploy to RunCloud (including handling RunCloud response)
    private function deploy_to_runcloud($post_id, $server, $server_post, $subscription) {
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = get_post_meta($post_id, 'arsol_server_provider', true);
        $server_name = get_post_meta($post_id, 'arsol_server_post_name', true);
        
        if (empty($server_name)) {
            $server_name = 'ARSOL' . $this->subscription_id;
            update_post_meta($post_id, 'arsol_server_post_name', $server_name);
        }
    
        // Update request body with server name from meta
        $runcloud_response = $this->runcloud->create_server_in_server_manager(
            $server_name,
            $server['public_net']['ipv4']['ip'],
            $web_server_type,
            $installation_type,
            $provider
        );

        // Handle RunCloud response
        $this->handle_runcloud_response($runcloud_response, $post_id, $subscription);
    }

    // Handle the RunCloud response and update subscription status
    private function handle_runcloud_response($runcloud_response, $post_id, $subscription) {
        // WP Error resulting in failure to send API Request
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

        // Decode the response body once
        $response_body_decoded = json_decode($runcloud_response['body'], true);

        // Failed with no status returned from RunCloud deployment
        if (!isset($runcloud_response['status'])) {
            error_log('[SIYA Server Manager] No status returned from RunCloud deployment');
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed - no status returned\nResponse body: %s\nFull response: %s",
                $runcloud_response['body'] ?? 'No body',
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error
        }

        // Error due to failed API requests with failed response
        if ($runcloud_response['status'] != 201 && $runcloud_response['status'] != 200) {
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

        // Step 4: Update server metadata with RunCloud deployment details
        $server_post->update_meta_data($post_id, [
            'arsol_server_runcloud_server_id' => $response_body_decoded['id'] ?? null,
            'arsol_server_deployment_date' => current_time('mysql'),
            'arsol_server_deployed_status' => 1,
            'arsol_server_connection_status' => 0
        ]);
    }
}


