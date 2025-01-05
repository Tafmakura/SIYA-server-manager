<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use SIYA\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud\Runcloud;

use Siya\Integrations\ServerProviders\DigitalOcean;
use Siya\Integrations\ServerProviders\Hetzner;
use Siya\Integrations\ServerProviders\Vultr;

class ServerOrchestrator {
   
    const POST_TYPE = 'server';

    private $subscription;
    private $subscription_id;
    public $server_post_id;
    public $server_provider;
    public $server_provider_slug;
    public $server_product_id;
    public $server_manager;
    public $server_plan_identifier;
    private $runcloud;
    private $digitalocean;
    private $hetzner;
    private $vultr;
    


    public function __construct() {
      
        // Add hooks for subscription status changes
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'provision_and_deploy_server'), 20, 1);
        add_action('woocommerce_subscription_status_active', array($this, 'subscription_circuit_breaker'), 10, 1);

    }

    
    public function provision_and_deploy_server($subscription) {
        try {

        $this->subscription = $subscription;
        $this->subscription_id = $subscription->get_id();
        $this->server_product_id = $this->extract_server_product_from_subscription($subscription);

        if (!$this->server_product_id) {
            error_log('[SIYA Server Manager] No server product found in subscription, moving on');
            return;
        }


        $server_post_instance = new ServerPost();
       
        // Step 1: Create server post only if it doesn't exist

        // Check if server post already exists
        $existing_server_post = $this->check_existing_server($server_post_instance, $subscription);

        if (!$existing_server_post) {
            error_log('[SIYA Server Manager] creating new server post');
            $server_post = $this->create_and_update_server_post($this->server_product_id, $server_post_instance, $subscription);
        } else {
            error_log('[SIYA Server Manager] Server post already exists, skipping Step 1  >>>>>>>');
            $this->server_post_id = $existing_server_post->post_id;
        }

        // Check server status flags
        $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
        $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);

        error_log(sprintf('[SIYA Server Manager] Subscription %d status flags - Provisioned: %s, Deployed: %s', 
            $this->subscription_id,
            $is_provisioned ? 'true' : 'false',
            $is_deployed ? 'true' : 'false'
        ));

        // Step 2: Provision server if not already provisioned
        $server_data = null;
        if (!$is_provisioned) {
            // Provision server only if needed
            $server_data = $this->provision_server($server_post_instance, $subscription);
        } else {
            error_log('[SIYA Server Manager] Server already provisioned, skipping Step 2');
            // Get existing server data for Step 3
            $server_data = [
                'server' => [
                    'public_net' => [
                        'ipv4' => ['ip' => get_post_meta($this->server_post_id, 'arsol_server_provisioned_ipv4', true)]
                    ]
                ]
            ];
        }

        // Step 3: Deploy to RunCloud if not already deployed
        if (!$is_deployed) {
            error_log('[SIYA Server Manager] Not deployed, deploying to RunCloud');
            // Instantiate RunCloud only if needed
            $this->runcloud = new Runcloud();  
            $this->deploy_to_runcloud_and_update_metadata($server_post_instance, $server_data, $subscription);
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
    private function create_and_update_server_post($server_product_id, $server_post_instance, $subscription) {
        $post_id = $server_post_instance->create_server_post($this->subscription_id);
        
        // Update server post metadata
        if ($post_id) {
            $this->server_post_id = $post_id;
            $subscription->add_order_note(
                'Server post created successfully with ID: ' . $this->server_post_id
            );
            error_log('[SIYA Server Manager] Created server post with ID: ' . $this->server_post_id);

            // Get server product metadata
            $server_product = wc_get_product($this->server_product_id);

            // Update server post metadata with correct meta keys
            $metadata = [
                'arsol_server_subscription_id' => $this->subscription_id,
                'arsol_server_post_name' => 'ARSOL' . $this->subscription_id,
                'arsol_server_post_creation_date' => current_time('mysql'),
                'arsol_server_post_status' => 1,
                'arsol_server_product_id' => $this->server_product_id,
                'arsol_wordpress_server' => $server_product->get_meta('_arsol_wordpress_server', true),
                'arsol_wordpress_ecommerce' => $server_product->get_meta('_arsol_wordpress_ecommerce', true),
                'arsol_server_provider_slug' => $server_product->get_meta('_arsol_server_provider_slug', true),
                'arsol_server_group_slug' => $server_product->get_meta('_arsol_server_group_slug', true),
                'arsol_server_plan_slug' => $server_product->get_meta('_arsol_server_plan_slug', true),
                'arsol_server_region_slug' => $server_product->get_meta('_arsol_server_region', true),
                'arsol_server_image_slug' => $server_product->get_meta('_arsol_server_image', true),
                'arsol_server_max_applications' => $server_product->get_meta('_arsol_max_applications', true),
                'arsol_server_max_staging_sites' => $server_product->get_meta('_arsol_max_staging_sites', true)
            ];
            $server_post_instance->update_meta_data($this->server_post_id, $metadata);

            error_log('[SIYA Server Manager] Updated server post meta data ' . $this->server_post_id);

            return true;
        } elseif ($post_id instanceof \WP_Error) {
            $subscription->add_order_note(
                'Failed to create server post. Error: ' . $post_id->get_error_message()
            );
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            throw new \Exception('Failed to create server post');
        }
    }

    // Step 2: Provision server and update server post metadata
    private function provision_server($server_post_instance, $subscription) {
    // Get server post ID early since we need it for subsequent operations
        
        $server_provider_slug = get_post_meta($this->server_post_id, 'arsol_server_provider_slug', true);
        $this->server_provider_slug = $server_provider_slug;
        error_log('[SIYA Server Manager] Server provider: ' . print_r($server_provider_slug, true));
        $server_name = 'ARSOL' . $this->subscription_id;
        $server_plan = get_post_meta($this->server_post_id, 'arsol_server_plan_slug', true);
        error_log('[SIYA Server Manager] Server plan: ' . print_r($server_plan, true));
        
        // Determine the provider and instantiate relevant class
        switch ($server_provider_slug) {
            case 'digitalocean':
                $this->digitalocean = new DigitalOcean();
                $server_data = $this->digitalocean->provision_server($server_name, $server_plan);
                break;
            case 'hetzner':
                $this->hetzner = new Hetzner();
                $server_data = $this->hetzner->provision_server($server_name, $server_plan);
                break;
            case 'vultr':
                $this->vultr = new Vultr();
                $server_data = $this->vultr->provision_server($server_name, $server_plan);
                break;
            default:
                throw new \Exception('Unknown server provider: ' . $server_provider_slug);
        }

        if (!$server_data) {
            $error_response = $this->hetzner->get_last_response();
            $error_body = json_encode($error_response, JSON_PRETTY_PRINT);
            $error_message = sprintf(
                "Failed to provision server%s%sAPI Response:%s%s",
                PHP_EOL, PHP_EOL, PHP_EOL, $error_body
            );
            $subscription->add_order_note($error_message);
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            throw new \Exception($error_message);
        }

        $server = $server_data['server'];
        $success_message = sprintf(
            "Server provisioned successfully! %s" .
            "Server Provider: %s%s" .
            "Server Name: %s%s" .
            "IP: %s%s" .
            "Created: %s%s" .
            "Server Plan: %s%s" .
            "Location: %s",
            PHP_EOL,
            $server_provider_slug, PHP_EOL,
            $server_name, PHP_EOL,
            $server['public_net']['ipv4']['ip'], PHP_EOL,
            $server['created'], PHP_EOL,
            $server['server_type']['name'], PHP_EOL,
            $server['datacenter']['location']['name']
        );
        $subscription->add_order_note($success_message);

        // Update server post metadata using the generic update_meta_data method
        $server_post_instance->update_meta_data($this->server_post_id, [
            'arsol_server_provisioned_id' => $server['id'],
            'arsol_server_provisioned_name' => $server_name,
            'arsol_server_provisioned_status' => 1,
            'arsol_server_provisioned_os' => $server['os'] ?? '',
            'arsol_server_provisioned_ipv4' => $server['public_net']['ipv4']['ip'],
            'arsol_server_provisioned_ipv6' => $server['public_net']['ipv6']['ip'],
            'arsol_server_provisioning_provider' => $this->server_provider_slug,
            'arsol_server_provisioned_root_password' => $server['root_password'] ?? '',
            'arsol_server_deployment_manager' => 'runcloud',
            'arsol_server_provisioned_date' => current_time('mysql'),
            'arsol_server_status_date' => current_time('mysql')
        ]);

        $subscription->add_order_note(sprintf(
            "Server metadata updated successfully:%s%s",
            PHP_EOL,
           // print_r($server_data, true)
        ));

        // Use ServerPost methods instead
        $server_post_instance->update_meta_data([
            'id' => $server['id'],
            'name' => $server_name,
            'status' => 1,
            'os' => $server['os'] ?? '',
            'ipv4' => $server['public_net']['ipv4']['ip'],
            'ipv6' => $server['public_net']['ipv6']['ip'],
            'provider' => $this->server_provider_slug,
            'root_password' => $server['root_password'] ?? '',
            'manager' => 'runcloud'
        ]);

        return $server_data;
    }

    // Step 3: Deploy to RunCloud and update server metadata
    private function deploy_to_runcloud_and_update_metadata($server_post_instance, $server_data, $subscription) {
        error_log(sprintf('[SIYA Server Manager] Step 5: Starting deployment to RunCloud for subscription %d', $this->subscription_id));

        $server = $server_data['server'];
        $server_name = 'ARSOL' . $this->subscription_id;
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = $this->server_provider;

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

        $metadata = [
            'arsol_server_deployed_server_id' => $response_body_decoded['id'] ?? null,
            'arsol_server_deployment_date' => current_time('mysql'),
            'arsol_server_deployed_status' => 1,
            'arsol_server_connection_status' => 0
        ];
        
        $server_post_instance->update_meta_data($this->server_post_id, $metadata);
   
       
        $subscription->update_status('active');

        error_log(sprintf('[SIYA Server Manager] Step 5: Deployment to RunCloud completed for subscription %d', $this->subscription_id));
    
    }


    public function check_existing_server($server_post_instance, $subscription) {
       
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
       
       
        if ($server_post) {
            error_log('[SIYA Server Manager] Found existing server: ' . $server_post->post_id);
            return $server_post;
        }

        error_log('[SIYA Server Manager] No existing server found');
        return false;
    }


    public function extract_server_product_from_subscription($subscription) {
        error_log('[SIYA Server Manager] Starting server product extraction from subscription ' . $subscription->get_id());
    
        // Ensure the subscription object has the required method and is valid
        if (!method_exists($subscription, 'get_items') || !$subscription->get_items()) {
            error_log('[SIYA Server Manager] No items found in subscription');
            return false;
        }
    
        $matching_product_ids = [];
    
        // Loop through all items in the subscription
        foreach ($subscription->get_items() as $item) {
            // Get the product associated with the item
            $product = $item->get_product();
    
            if (!$product) {
                error_log('[SIYA Server Manager] Invalid product in subscription item');
                continue;
            }
    
            $product_id = $product->get_id();
            $meta_value = null;
    
            // Check if the product is a variation
            if (get_post_type($product_id) === 'product_variation') {
                // Get the parent product for the variation
                $parent_id = $product->get_parent_id();
                $parent_product = wc_get_product($parent_id);
    
                if (!$parent_product) {
                    error_log(sprintf('[SIYA Server Manager] Parent product not found for variation ID: %d', $product_id));
                    continue;
                }
    
                // Check the parent product's _arsol_server meta key
                $meta_value = $parent_product->get_meta('_arsol_server', true);
    
                if ($meta_value === 'yes') {
                    $product_id = $parent_id; // Use parent product ID
                }
            } else {
                // For simple products, check the product's meta value
                $meta_value = $product->get_meta('_arsol_server', true);
            }
    
            // Log the meta value
            error_log(sprintf('[SIYA Server Manager] Product ID %d has _arsol_server value: %s', 
                $product_id, 
                print_r($meta_value, true)
            ));
    
            // Check if the meta value is 'yes'
            if ($meta_value === 'yes') {
                error_log('[SIYA Server Manager] Found matching server product: ' . $product_id);
                $matching_product_ids[] = $product_id;
            }
        }
    
        // Handle the results
        if (empty($matching_product_ids)) {
            error_log('[SIYA Server Manager] No matching server products found');
            return false;
        }
    
        if (count($matching_product_ids) > 1) {
            error_log('[SIYA Server Manager] Multiple server products found: ' . implode(', ', $matching_product_ids));
            $subscription->add_order_note('Multiple server products found with _arsol_server = yes. Please review the subscription.');
            return null;
        }
    
        error_log('[SIYA Server Manager] Returning single matching product ID: ' . $matching_product_ids[0]);
        return $matching_product_ids[0];
    }
    
    
    



    public function subscription_circuit_breaker($subscription) {

        $this->server_product_id = $this->extract_server_product_from_subscription($subscription);

        if (!$this->server_product_id) {
            error_log('[SIYA Server Manager] No server product found in subscription');
            return;
        }
       
        error_log('[SIYA Server Manager CB] Starting subscription circuit breaker check');

        if (!is_admin()) {
            return;
        }
        $server_post_instance = new ServerPost;
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
        $this->server_post_id = $server_post->post_id;

        $this->subscription_id = $subscription->get_id();
        $is_provisioned = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);
        $is_deployed = get_post_meta($this->server_post_id, 'arsol_server_deployed_status', true);

        error_log(sprintf('[SIYA Server Manager CB] Status check - Provisioned: %s, Deployed: %s', 
            $is_provisioned ? 'true' : 'false',
            $is_deployed ? 'true' : 'false'
        ));

        if($is_provisioned && $is_deployed){
            error_log('[SIYA Server Manager CB] Server is provisioned and deployed, no need to disconnect');
            return;
        
        }else{

            error_log('[SIYA Server Manager  CB] Setting subscription to on-hold status');
            $subscription->add_order_note(
                "Subscription status set to on-hold. Server provisioning and deployment in progress."
            );
    
            $subscription->update_status('on-hold');

            error_log('[SIYA Server Manager CB] Initiating server provision and deploy process');
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



