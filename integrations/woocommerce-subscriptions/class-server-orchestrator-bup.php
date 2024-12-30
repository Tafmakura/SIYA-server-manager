<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud\Runcloud;
use Siya\Integrations\ServerProviders\Hetzner\Hetzner;
use Siya\Integrations\WoocommerceSubscriptions\CircuitBreaker;

class ServerOrchestrator {
   
    const POST_TYPE = 'server';
    const META_PREFIX = 'arsol_server_';

    private $subscription_id;
    public $server_provider;
    public $server_manager;
    public $server_plan_identifier;
    private $runcloud;
    private $hetzner;

    public function __construct($subscription_id = null) {
        $this->subscription_id = $subscription_id;
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
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'create_server_on_activate'), 20, 1);
        add_action('woocommerce_subscription_status_active', array($this, 'force_on_hold_and_check_server_status'), 10, 1);
    }

    public function create_server_for_subscription($subscription_id) {
        $post_data = array(
            'post_title'    => 'Server ' . $subscription_id,
            'post_status'   => 'publish',
            'post_type'     => self::POST_TYPE
        );
        return wp_insert_post($post_data);
    }

    public function get_server_by_subscription_id($subscription_id) {
        $server_id = $this->get_server_id_by_subscription($subscription_id);
        return $server_id ? ServerPost::get_server_post_by_id($server_id) : null;
    }

    public function get_server_id_by_subscription($subscription_id) {
        $args = array(
            'post_type' => self::POST_TYPE,
            'meta_query' => array(
                array(
                    'key' => self::META_PREFIX . 'subscription_id',
                    'value' => $subscription_id
                )
            )
        );
        $query = new \WP_Query($args);
        return $query->posts ? $query->posts[0]->ID : null;
    }

    private function provision_and_deploy_server($subscription_id, $post_id) {
        try {
            // Step 1: Provision with Hetzner
            $server_name = 'wp-' . date('y-m-d') . '-' . $subscription_id;
            $provisioned_server = $this->hetzner->provision_server($server_name);
            
            // Step 2: Update post meta with provisioned data
            update_post_meta($post_id, 'arsol_server_provisioned_id', $provisioned_server['server']['id']);
            update_post_meta($post_id, 'arsol_server_ipv4', $provisioned_server['server']['public_net']['ipv4']['ip']);
            
            // Step 3: Deploy with RunCloud
            $this->runcloud->deploy_server(
                $server_name,
                $provisioned_server['server']['public_net']['ipv4']['ip'],
                'nginx',
                'containerized'
            );

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Server provisioning failed: ' . $e->getMessage());
        }
    }

    public function create_server_on_activate($subscription) {
        $subscription_id = $subscription->get_id();
        $post_id = get_post_meta($subscription_id, self::META_PREFIX . 'post_id', true);
        
        $subscription->add_order_note(
            __('[create-activate-1] Retrieved server post ID: ' . $post_id . ' for subscription ID: ' . $subscription_id, 'your-text-domain')
        );

        if (!$post_id) {
            $subscription->add_order_note(
                __('[create-activate-2] Creating new server post for subscription ID: ' . $subscription_id, 'your-text-domain')
            );

            $post_id = $this->create_server_for_subscription($subscription_id);
            
            if (is_wp_error($post_id)) {
                $subscription->add_order_note(
                    __('[create-activate-3] Failed to create server post. Error: ' . $post_id->get_error_message(), 'your-text-domain')
                );
                return;
            }

            $subscription->add_order_note(
                __('[create-activate-4] Successfully created server post with ID: ' . $post_id, 'your-text-domain')
            );

            update_post_meta($post_id, self::META_PREFIX . 'deployed', 0);
            update_post_meta($post_id, self::META_PREFIX . 'connected', 0);
            update_post_meta($subscription_id, self::META_PREFIX . 'post_id', $post_id);
            update_post_meta($post_id, self::META_PREFIX . 'subscription_id', $subscription_id);

            $subscription->add_order_note(
                __('[create-activate-5] Successfully created server entity.', 'your-text-domain')
            );
        } else {
            $subscription->add_order_note(
                __('[create-activate-6] Server post already exists for subscription ID: ' . $subscription_id, 'your-text-domain')
            );
        }

        $server_deployed = get_post_meta($post_id, self::META_PREFIX . 'deployed', true);
        $server_connected = get_post_meta($post_id, self::META_PREFIX . 'connected', true);

        $subscription->add_order_note(
            __('[create-activate-7] Server deployed: ' . $server_deployed . ', server connected: ' . $server_connected, 'your-text-domain')
        );

        if ($server_deployed != 1 || $server_connected != 1) {
            try {
                $this->provision_and_deploy_server($subscription_id, $post_id);

                update_post_meta($post_id, self::META_PREFIX . 'deployed', 1);
                update_post_meta($post_id, self::META_PREFIX . 'connected', 1);

                $subscription->add_order_note(
                    __('[create-activate-9] Successfully provisioned and registered server.', 'your-text-domain')
                );
            } catch (\Exception $e) {
                $subscription->add_order_note(
                    __('[create-activate-8] Failed to provision and register server: ' . $e->getMessage(), 'your-text-domain')
                );
                $subscription->update_status('on-hold');
            }
        } else {
            $subscription->add_order_note(
                __('[create-activate-10] Server already deployed and connected.', 'your-text-domain')
            );
        }

        $parent_order = $subscription->get_parent();
        if ($parent_order) {
            $parent_order->update_status('completed');
        }
    }

    public function force_on_hold_and_check_server_status($subscription_id) {
        $circuit_breaker = new CircuitBreaker($subscription_id);
        $circuit_breaker->force_on_hold_and_check_server_status();
    }
}
