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
    private $server_post_id;

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
        try {
            // Step 1 and 2: Create server post and update server metadata
            $server_post = $this->create_and_update_server_post($subscription);

            // Step 3 and 4: Provision Hetzner server and update server post metadata
            $server_data = $this->provision_hetzner_server($server_post, $subscription);

            // Step 5 and 6: Deploy to RunCloud and update server metadata
            $this->deploy_to_runcloud_and_update_metadata($server_post, $server_data, $subscription);

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

    // Step 1 and 2: Create server post and update server metadata
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

    // Step 3 and 4: Provision Hetzner server and update server post metadata
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
            'arsol_server_server_id' => $server['id'],
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

    // Step 5 and 6: Deploy to RunCloud and update server metadata
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
        $server_post->update_meta_data($server_post->get_post_id(), [
            'arsol_server_runcloud_server_id' => $response_body_decoded['id'] ?? null,
            'arsol_server_deployment_date' => current_time('mysql'),
            'arsol_server_deployed_status' => 1,
            'arsol_server_connection_status' => 0
        ]);

        error_log(sprintf('[SIYA Server Manager] Step 5: Deployment to RunCloud completed for subscription %d', $this->subscription_id));
    }
}



