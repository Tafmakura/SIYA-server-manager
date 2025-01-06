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
    
    // Add missing metadata properties
    private $server_post_name;
    private $server_post_creation_date;
    private $server_post_status;
    private $wordpress_server;
    private $wordpress_ecommerce;
    private $connect_server_manager;
    private $server_group_slug;
    private $server_plan_slug;
    private $server_region_slug;
    private $server_image_slug;
    private $server_max_applications;
    private $server_max_staging_sites;
    private $server_provisioned_status;
    private $server_deployed_status;
    private $server_provisioned_name;
    private $server_provisioned_os;
    private $server_provisioned_ipv4;
    private $server_provisioned_ipv6;
    private $server_provisioned_root_password;
    private $server_provisioned_date;
    private $server_provisioned_remote_status;
    private $server_provisioned_remote_raw_status;
    private $server_deployed_server_id;
    private $server_deployment_date;
    private $server_connection_status;

    public function __construct() {
        // Change the action hook to use Action Scheduler
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'start_server_provision'), 20, 1);
        
        // Add new action hook for the background process
        add_action('arsol_complete_server_provision', array($this, 'complete_server_provision'), 20, 1);
    }

    public function start_server_provision($subscription) {
        try {
            $this->subscription = $subscription;
            $this->subscription_id = $subscription->get_id();
            $this->server_product_id = $this->extract_server_product_from_subscription($subscription);
            $this->server_provider_slug = $metadata['arsol_server_provider_slug'] ?? null;

            if (!$this->server_product_id) {
                error_log('[SIYA Server Manager] No server product found in subscription, moving on');
                return;
            }

            $server_post_instance = new ServerPost();
           
            // Step 1: Create server post only if it doesn't exist
            $existing_server_post = $this->check_existing_server($server_post_instance, $subscription);

            if (!$existing_server_post) {
                error_log('[SIYA Server Manager] creating new server post');
                $server_post = $this->create_and_update_server_post($this->server_product_id, $server_post_instance, $subscription);
            } else {
                error_log('[SIYA Server Manager] Server post already exists, skipping Step 1  >>>>>>>');
                $this->server_post_id = $existing_server_post->post_id;
            }

            // Step 2: Schedule server provisioning  as a background process
            as_schedule_single_action(
                time(), // Run immediately, but in the background
                'arsol_complete_server_provision',
                array(
                    'subscription_id' => $this->subscription_id,
                    'server_post_id' => $this->server_post_id,
                    'server_product_id' => $this->server_product_id,
                    'server_provider_slug' => $this->server_provider_slug
                ),
                'arsol_server_provision'
            );

            error_log('[SIYA Server Manager] Scheduled background server provision for subscription ' . $this->subscription_id);

        } catch (\Exception $e) {
            error_log(sprintf(
                '[SIYA Server Manager] Error in subscription %d:%s%s',
                $this->subscription_id,
                PHP_EOL,
                $e->getMessage()
            ));
    
            $subscription->add_order_note(sprintf(
                "Error occurred during server provisioning:%s%s",
                PHP_EOL,
                $e->getMessage()
            ));
    
            $subscription->update_status('on-hold');
        }
    }

    public function complete_server_provision($args) {
        try {

            error_log(sprintf('[SIYA Server Manager] Starting complete_server_provision with args: %s', 
                print_r($args, true)
            ));

            // Initialize required instances
            $server_post_instance = new ServerPost();
            
            // Get the server provider slug from args
            $this->server_provider_slug = $args['server_provider_slug'];
            
            // Initialize the appropriate server provider with the slug
            $this->initialize_server_provider($this->server_provider_slug);
            
            // Retrieve the subscription and server post data
            $subscription = wcs_get_subscription($args['subscription_id']);
            $this->subscription_id = $args['subscription_id'];
            $this->server_post_id = $args['server_post_id'];
            $this->server_product_id = $args['server_product_id'];

            if (!$subscription) {
                throw new \Exception('Subscription not found: ' . $args['subscription_id']);
            }

            // Continue with the rest of your existing complete_server_provision logic
            // Load all parameters from the server post metadata
            $metadata = $server_post_instance->get_meta_data($this->server_post_id);
            
            // Load parameters into class properties
            $this->server_post_name = $metadata['arsol_server_post_name'] ?? null;
            $this->server_post_status = $metadata['arsol_server_post_status'] ?? null;
            $this->server_post_creation_date = $metadata['arsol_server_post_creation_date'] ?? null;
            $this->wordpress_server = $metadata['arsol_wordpress_server'] ?? null;
            $this->wordpress_ecommerce = $metadata['arsol_wordpress_ecommerce'] ?? null;
            $this->connect_server_manager = $metadata['arsol_connect_server_manager'] ?? null;
            $this->server_provider_slug = $metadata['arsol_server_provider_slug'] ?? null;
            $this->server_group_slug = $metadata['arsol_server_group_slug'] ?? null;
            $this->server_plan_slug = $metadata['arsol_server_plan_slug'] ?? null;
            $this->server_region_slug = $metadata['arsol_server_region_slug'] ?? null;
            $this->server_image_slug = $metadata['arsol_server_image_slug'] ?? null;
            $this->server_max_applications = $metadata['arsol_server_max_applications'] ?? null;
            $this->server_max_staging_sites = $metadata['arsol_server_max_staging_sites'] ?? null;
            
            // Load provisioning related parameters
            $this->server_provisioned_status = $metadata['arsol_server_provisioned_status'] ?? null;
            $this->server_provisioned_name = $metadata['arsol_server_provisioned_name'] ?? null;
            $this->server_provisioned_os = $metadata['arsol_server_provisioned_os'] ?? null;
            $this->server_provisioned_ipv4 = $metadata['arsol_server_provisioned_ipv4'] ?? null;
            $this->server_provisioned_ipv6 = $metadata['arsol_server_provisioned_ipv6'] ?? null;
            $this->server_provisioned_root_password = $metadata['arsol_server_provisioned_root_password'] ?? null;
            $this->server_provisioned_date = $metadata['arsol_server_provisioned_date'] ?? null;
            $this->server_provisioned_remote_status = $metadata['arsol_server_provisioned_remote_status'] ?? null;
            $this->server_provisioned_remote_raw_status = $metadata['arsol_server_provisioned_remote_raw_status'] ?? null;
            
            // Load deployment related parameters
            $this->server_deployed_status = $metadata['arsol_server_deployed_status'] ?? null;
            $this->server_deployed_server_id = $metadata['arsol_server_deployed_server_id'] ?? null;
            $this->server_deployment_date = $metadata['arsol_server_deployment_date'] ?? null;
            $this->server_connection_status = $metadata['arsol_server_connection_status'] ?? null;
            
            error_log(sprintf('[SIYA Server Manager] Loaded parameters for server post %d: %s', 
                $this->server_post_id,
                print_r($metadata, true)
            ));

            // Continue with existing provisioning logic
            // Check server status flags
            $is_provisioned = $this->server_provisioned_status;
            $is_deployed = $this->server_deployed_status;

            error_log(sprintf('[SIYA Server Manager] Subscription %d status flags - Provisioned: %s, Deployed: %s', 
                $this->subscription_id,
                $is_provisioned ? 'true' : 'false',
                $is_deployed ? 'true' : 'false'
            ));

            
            // Step 2: Provision server if not already provisioned
            $server_data = null;
            if (!$is_provisioned) {
                $server_data = $this->provision_server($server_post_instance, $subscription);
            } else {
                error_log('[SIYA Server Manager] Server already provisioned, skipping Step 2');
                $server_data = [
                    'server' => [
                        'public_net' => [
                            'ipv4' => ['ip' => get_post_meta($this->server_post_id, 'arsol_server_provisioned_ipv4', true)]
                        ]
                    ]
                ];
            }
                
            $subscription->update_status('on-hold');


            // Runcloud deployment switch
            if ($server_data) {
                $server_ready = $this->wait_for_server_status('active', 300, 10); // 5 minutes timeout, check every 10 seconds
                if (!$server_ready) {
                    throw new \Exception('Server failed to become active within the timeout period');
                }
            }
    
            // Step 3: Deploy to RunCloud if not already deployed
            if (!$is_deployed) {
                error_log('[SIYA Server Manager] Not deployed, deploying to RunCloud');
                $this->runcloud = new Runcloud();  
                $this->deploy_to_runcloud_and_update_metadata($server_post_instance, $server_data, $subscription);
            } else {
                error_log('[SIYA Server Manager] Server already deployed, skipping Step 3');
            }
        } catch (\Exception $e) {
            error_log(sprintf('[SIYA Server Manager] Error in server completion: %s', $e->getMessage()));
            
            if (isset($subscription)) {
                $subscription->add_order_note('Server provision failed: ' . $e->getMessage());
                $subscription->update_status('on-hold');
            }
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
                'arsol_connect_server_manager' => $server_product->get_meta('_arsol_connect_server_manager', true),
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
        $provider_slug = get_post_meta($this->server_post_id, 'arsol_server_provider_slug', true);
        error_log('[SIYA Server Manager] Server provider: ' . print_r($provider_slug, true));
        $server_name = 'ARSOL' . $this->subscription_id;
        $server_plan = get_post_meta($this->server_post_id, 'arsol_server_plan_slug', true);
        error_log('[SIYA Server Manager] Server plan: ' . print_r($server_plan, true));
        
        // Initialize the provider with explicit provider slug
        $this->initialize_server_provider($provider_slug);
        
        // Use the initialized provider
        $server_data = $this->server_provider->provision_server($server_name, $server_plan);
        
        if (!$server_data) {
            $error_message = "Failed to provision server";
            $subscription->add_order_note($error_message);
            $subscription->update_status('on-hold');
            throw new \Exception($error_message);
        }

        $success_message = sprintf(
            "Server provisioned successfully! %s" .
            "Server Provider: %s%s" .
            "Server Name: %s%s" .
            "IP: %s%s" .
            "Memory: %s%s" .
            "CPU Cores: %s%s" .
            "Region: %s",
            PHP_EOL,
            $server_provider_slug, PHP_EOL,
            $server_data['provisioned_name'], PHP_EOL,
            $server_data['provisioned_ipv4'], PHP_EOL,
            $server_data['provisioned_memory'], PHP_EOL,
            $server_data['provisioned_vcpu_count'], PHP_EOL,
            $server_data['provisioned_region_slug']
        );
        $subscription->add_order_note($success_message);

        // Update server post metadata using the standardized data
        $metadata = [
            'arsol_server_provisioned_status' => 1,
            'arsol_server_provisioned_name' => $server_data['provisioned_name'],
            'arsol_server_provisioned_os' => $server_data['provisioned_os'],
            'arsol_server_provisioned_ipv4' => $server_data['provisioned_ipv4'],
            'arsol_server_provisioned_ipv6' => $server_data['provisioned_ipv6'],
            'arsol_server_provisioning_provider' => $this->server_provider_slug,
            'arsol_server_provisioned_root_password' => $server_data['provisioned_root_password'],
            'arsol_server_provisioned_date' => $server_data['provisioned_date'],
            'arsol_server_provisioned_remote_status' => $server_data['provisioned_remote_status'],
            'arsol_server_provisioned_remote_raw_status' => $server_data['provisioned_remote_raw_status']
        ];

        error_log(sprintf('[SIYA Server Manager] Provider Status Details:%sRemote Status: %s%sRaw Status: %s', 
            PHP_EOL,
            $server_data['provisioned_remote_status'],
            PHP_EOL,
            $server_data['provisioned_remote_raw_status']
        ));

        $server_post_instance->update_meta_data($this->server_post_id, $metadata);

        $subscription->add_order_note(sprintf(
            "Server metadata updated successfully:%s%s",
            PHP_EOL,
            print_r($metadata, true)
        ));

        return $server_data;
    }

    private function deploy_to_runcloud_and_update_metadata($server_post_instance, $server_data, $subscription) {
        error_log(sprintf('[SIYA Server Manager] Starting deployment to RunCloud for subscription %d', $this->subscription_id));

        $server_name = 'ARSOL' . $this->subscription_id;
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = $this->server_provider;

        // Use the standardized IPv4 address
        $ipv4 = $server_data['provisioned_ipv4'];
        if (empty($ipv4)) {
            error_log('[SIYA Server Manager] Error: IPv4 address is empty.');
            $subscription->add_order_note('RunCloud deployment failed: IPv4 address is empty.');
            return;
        }

        // Deploy to RunCloud
        $runcloud_response = $this->runcloud->create_server_in_server_manager(
            $server_name,
            $ipv4,
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
            'arsol_server_connection_status' => 0,
            'arsol_server_manager' => 'runcloud'  // Changed from arsol_server_deployment_manager
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





    // Here 

    private function wait_for_server_status($target_status, $timeout_seconds = 300, $check_interval = 10) {
        error_log(sprintf('[SIYA Server Manager] Waiting for server to reach "%s" status (timeout: %d seconds, interval: %d seconds)', 
            $target_status, $timeout_seconds, $check_interval));

        // Initialize provider with current provider slug
        $this->initialize_server_provider($this->server_provider_slug);
        
        $start_time = time();
        $attempts = 0;

        while (time() - $start_time < $timeout_seconds) {
            $attempts++;
            $status = $provider->get_server_status();
            
            if ($status === false) {
                error_log(sprintf('[SIYA Server Manager] Failed to get server status (attempt %d)', $attempts));
                return false;
            }

            $current_status = $status['provisioned_remote_status'];
            error_log(sprintf('[SIYA Server Manager] Current server status (attempt %d): %s (raw: %s)', 
                $attempts,
                $current_status, 
                $status['provisioned_remote_raw_status']
            ));

            if ($current_status === $target_status) {
                error_log(sprintf('[SIYA Server Manager] Server reached target status "%s" after %d seconds (%d attempts)', 
                    $target_status, 
                    time() - $start_time,
                    $attempts
                ));
                return true;
            }

            if ($current_status === 'error') {
                error_log(sprintf('[SIYA Server Manager] Server entered error state (attempt %d)', $attempts));
                return false;
            }

            if (time() - $start_time < $timeout_seconds) {
                error_log(sprintf('[SIYA Server Manager] Waiting %d seconds before next check (attempt %d)', 
                    $check_interval, 
                    $attempts + 1
                ));
                sleep($check_interval);
            }
        }

        error_log(sprintf('[SIYA Server Manager] Timeout reached (%d seconds, %d attempts) waiting for status "%s"', 
            $timeout_seconds,
            $attempts,
            $target_status
        ));
        return false;
    }

    // Modified helper method to initialize server provider
    private function initialize_server_provider($provider_slug = null) {
        // Use passed provider slug or get from metadata if not provided
        if ($provider_slug) {
            $this->server_provider_slug = $provider_slug;
        } else if (!$this->server_provider_slug) {
            $this->server_provider_slug = get_post_meta($this->server_post_id, 'arsol_server_provider_slug', true);
        }

        // Initialize the appropriate provider instance
        switch ($this->server_provider_slug) {
            case 'digitalocean':
                if (!$this->digitalocean) {
                    $this->digitalocean = new DigitalOcean();
                }
                $this->server_provider = $this->digitalocean;
                break;
            case 'hetzner':
                if (!$this->hetzner) {
                    $this->hetzner = new Hetzner();
                }
                $this->server_provider = $this->hetzner;
                break;
            case 'vultr':
                if (!$this->vultr) {
                    $this->vultr = new Vultr();
                }
                $this->server_provider = $this->vultr;
                break;
            default:
                throw new \Exception('Unknown server provider: ' . $this->server_provider_slug);
        }
    }

}



