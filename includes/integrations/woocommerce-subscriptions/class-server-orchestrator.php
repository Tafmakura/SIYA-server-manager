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
        // Get subscription ID
        $subscription_id = $subscription->get_id();

        try {
            // Step 1: Create server post
            $server_post = new ServerPost();
            $post_id = $server_post->create_server_post($subscription_id);
            $subscription->add_order_note('Server post created successfully');

            // Step 2: Provision Hetzner server
            $server_data = $this->hetzner->provision_server();
            if (!$server_data) {
                throw new \Exception('Failed to provision Hetzner server');
            }
            $subscription->add_order_note(sprintf(
                'Hetzner server provisioned successfully. IP: %s', 
                $server_data['server']['public_net']['ipv4']['ip']
            ));

            // Step 3: Deploy to RunCloud
            $deploy_result = $this->runcloud->deploy_server(
                'wordpress-' . $subscription_id,
                $server_data['server']['public_net']['ipv4']['ip']
            );
            if (!$deploy_result) {
                throw new \Exception('Failed to deploy server to RunCloud');
            }
            $subscription->add_order_note('Server deployed successfully to RunCloud');

            // Step 4: Update server post meta
            $server_post->update_provisioned_server_data($post_id, [
                'id' => $server_data['server']['id'],
                'ipv4' => $server_data['server']['public_net']['ipv4']['ip'],
                'ipv6' => $server_data['server']['public_net']['ipv6']['ip'],
                'status' => $server_data['server']['status']
            ]);

        } catch (\Exception $e) {
            $subscription->add_order_note('Error: ' . $e->getMessage());
            $subscription->update_status('on-hold');
        }
    }

}


