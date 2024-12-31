<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud\Runcloud;
use Siya\Integrations\ServerProviders\Hetzner\Hetzner;
use Siya\Integrations\WoocommerceSubscriptions\CircuitBreaker;

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
        $this->runcloud = new Runcloud();
        $this->hetzner = new Hetzner();
        
        if ($subscription_id) {
            $server = $this->get_server_by_subscription_id($subscription_id);
            if ($server) {
                $this->server_provider = get_post_meta($server->ID, self::META_PREFIX . 'provider', true);
                $this->server_manager = get_post_meta($server->ID, self::META_PREFIX . 'manager', true);
                $this->server_plan_identifier = get_post_meta($server->ID, self::META_PREFIX . 'plan_identifier', true);
            }
        }

        // Add hooks for subscription status changes
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'provision_and_deploy_server'), 20, 1);
        add_action('woocommerce_subscription_status_active', array($this, 'force_on_hold_and_check_server_status'), 10, 1);
    }

    public function provision_and_deploy_server($subscription) {
        $subscription_id = $subscription->get_id();

        try {
            // Step 1: Create server post
            $server_post = new ServerPost();
            $post_id = $server_post->create_server_post($subscription_id);
            $subscription->add_order_note('Server post created successfully.' . PHP_EOL . 'Post ID: ' . $post_id);

            // Step 2: Provision Hetzner server
            $server_data = $this->hetzner->provision_server();
            if (!$server_data) {
                $error_response = $this->hetzner->get_last_response();
                $error_body = json_encode($error_response, JSON_PRETTY_PRINT);
                $error_message = sprintf(
                    "Failed to provision Hetzner server%s%sAPI Response:%s%s",
                    PHP_EOL, PHP_EOL, PHP_EOL, $error_body
                );
                throw new \Exception($error_message);
            }
            $server = $server_data['server'];
            $success_message = sprintf(
                "Hetzner server provisioned successfully! ✓%s" .
                "IP: %s%s" .
                "Created: %s%s" .
                "Server Type: %s%s" .
                "Location: %s",
                PHP_EOL,
                $server['public_net']['ipv4']['ip'], PHP_EOL,
                $server['created'], PHP_EOL,
                $server['server_type']['name'], PHP_EOL,
                $server['datacenter']['location']['name']
            );
            $subscription->add_order_note($success_message);

            // Step 3: Update server post metadata
            $metadata = [
                'provider' => 'hetzner',
                'manager' => 'runcloud',
                'plan_identifier' => $this->server_plan_identifier,
                'server_id' => $server['id'],
                'ipv4' => $server['public_net']['ipv4']['ip'],
                'ipv6' => $server['public_net']['ipv6']['ip'],
                'status' => $server['status'],
                'location' => $server['datacenter']['location']['name'],
                'server_type' => $server['server_type']['name'],
                'created_date' => $server['created']
            ];
            
            $server_post->update_meta_data($post_id, $metadata);
            $subscription->add_order_note(sprintf(
                "Server metadata updated successfully:%s%s",
                PHP_EOL,
                print_r($metadata, true)
            ));

            // Step 4: Deploy to RunCloud
            $deploy_result = $this->runcloud->deploy_server(
                'wordpress-' . $subscription_id,
                $server['public_net']['ipv4']['ip']
            );
            if (!$deploy_result) {
                $error_response = $this->runcloud->get_last_response();
                $error_body = json_encode($error_response, JSON_PRETTY_PRINT);
                $error_message = sprintf(
                    "Failed to deploy server to RunCloud%s%sAPI Response:%s%s",
                    PHP_EOL, PHP_EOL, PHP_EOL, $error_body
                );
                throw new \Exception($error_message);
            }
            $subscription->add_order_note(sprintf(
                "RunCloud deployment response:%s%s",
                PHP_EOL,
                print_r($deploy_result, true)
            ));

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

}


