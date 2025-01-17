<?php

namespace Siya\Integrations\WoocommerceSubscriptions;

use Siya\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud;
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
    public $server_product;
    public $server_manager;
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
    private $server_provisioned_id;
    protected $server_circuit_breaker_status;

    public function __construct() {
        // Change the action hook to use Action Scheduler
        // Hook into WooCommerce subscription status change from pending to active to start server provisioning
        add_action('woocommerce_subscription_status_pending_to_active', array($this, 'start_server_provision'), 20, 1);

        // Register new hooks to trigger shutdown
        add_action('woocommerce_subscription_status_active_to_on-hold', array($this, 'start_server_shutdown'), 20, 1);
        add_action('woocommerce_subscription_status_active_to_expired', array($this, 'start_server_shutdown'), 20, 1);
        add_action('arsol_server_shutdown', array($this, 'finish_server_shutdown'), 20, 1);

        // Add new action hook for the scheduled processes
        add_action('arsol_finish_server_provision', array($this, 'finish_server_provision'), 20, 1);
        add_action('arsol_update_server_status', array($this, 'start_update_server_status'), 20, 1);

        // Add new action hooks for server powerup
        add_action('woocommerce_subscription_status_on-hold_to_active', array($this, 'start_server_powerup'), 20, 1);
        add_action('arsol_server_powerup', array($this, 'finish_server_powerup'), 20, 1);

        add_action('woocommerce_subscription_status_pending-cancel_to_cancelled', array($this, 'start_server_deletion'), 10, 1);
        add_action('arsol_finish_server_deletion', array($this, 'finish_server_deletion'), 20, 1);

        // Add new action hooks for server connection
        add_action('arsol_verify_server_manager_connection_hook', [$this, 'verify_server_manager_connection']);

    }

    // Step 1: Start server provisioning process (Create server post)
    public function start_server_provision($subscription) {
        try {
            $this->subscription = $subscription;
            $this->subscription_id = $subscription->get_id();
            $this->server_product_id = $this->extract_server_product_from_subscription($subscription);
            $this->server_product = wc_get_product($this->server_product_id); 
            $this->server_provider_slug = $this->server_product
                ? $this->server_product->get_meta('_arsol_server_provider_slug', true)
                : null;

            if (!$this->server_product_id) {
                error_log('#001 [SIYA Server Manager - ServerOrchestrator] No server product found in subscription, moving on');
                return;
            }

           
            // Step 1: Create server post only if it doesn't exist
            $server_post_instance = new ServerPost();
            $existing_server_post = $this->check_existing_server($server_post_instance, $this->subscription);

            if (!$existing_server_post) {
                error_log('#002 [SIYA Server Manager - ServerOrchestrator] creating new server post');
                $server_post = $this->create_and_update_server_post($this->server_product_id, $server_post_instance, $subscription);
            } else {
                error_log('#003 [SIYA Server Manager - ServerOrchestrator] Server post already exists, skipping Step 1');
                $this->server_post_id = $existing_server_post->post_id;
            }
            
            // Step 2: Schedule asynchronous action with predefined parameters to complete server provisioning
            $this->schedule_action('arsol_finish_server_provision', [
                'subscription_id' => $this->subscription_id,
                'server_post_id' => $this->server_post_id,
                'server_product_id' => $this->server_product_id,
                'server_provider_slug' => $this->server_provider_slug
            ]);

            error_log('#004 [SIYA Server Manager - ServerOrchestrator] Scheduled background server provision for subscription ' . $this->subscription_id);

        } catch (\Exception $e) {
            $this->handle_exception($e, $subscription, 'Error occurred during server provisioning');
        }
    }

    // Step 2: Finish server provisioning process (Provision server)
    public function finish_server_provision($args) {
        try {
            
            error_log('#006 [SIYA Server Manager - ServerOrchestrator] Starting server completion');
        
            // Extract the arguments from action scheduler
            $this->subscription_id = $args['subscription_id'];
            $this->subscription = wcs_get_subscription($this->subscription_id);
            $this->server_post_id = $args['server_post_id'];
            $this->server_product_id = $args['server_product_id'];
            $this->server_provider_slug = $args['server_provider_slug'];

            error_log('Milestone 1');
            error_log(sprintf('#007 [SIYA Server Manager - ServerOrchestrator] Received arguments: %s', json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
            
            // Load all parameters from the server post metadata
            $server_post_instance = new ServerPost($this->server_post_id);
            $metadata = $server_post_instance->get_meta_data();

            error_log('Milestone 2');
            error_log(sprintf('#008 [SIYA Server Manager - ServerOrchestrator] Loaded metadata: %s', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

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



            // Step 2: Provision server if not already provisioned
            // Check server status flags
            error_log('Milestone 3');
            $this->server_provisioned_status = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true); 
            if ($this->server_provisioned_status) {
                error_log('Milestone 3 Server Status: ' . $this->server_provisioned_status);
            } else {
                error_log('Milestone 3 Server Status: Unavailable');
            }

            if (!$this->server_provisioned_status) {

                error_log('Milestone 5a');
                error_log(sprintf(
                    '#009 [SIYA Server Manager - ServerOrchestrator] Subscription ID: %d, Server Post ID: %d, Server Product ID: %d, Server Provider Slug: %s',
                    $this->subscription_id,
                    $this->server_post_id,
                    $this->server_product_id,
                    $this->server_provider_slug
                ));


                try {
                    // Initialize the appropriate server provider with the slug
                    
                    $this->initialize_server_provider($this->server_provider_slug);

                    error_log('Milestone 5b');

                    try {
                        $server_data = $this->provision_server_at_provider($this->subscription);
                    } catch (\Exception $e) {
                        error_log(sprintf('#SIYA Server Manager - ServerOrchestrator] Error during server provisioning: %s', $e->getMessage()));
                        $this->subscription->add_order_note(sprintf(
                            "Error occurred during server provisioning:%s%s%s%s",
                            PHP_EOL,
                            $e->getMessage(),
                            PHP_EOL,
                            json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                        ));
                        $this->subscription->update_status('on-hold');
                        return;
                    }

                    error_log('Milestone 5c');

                    error_log(sprintf('#010 [SIYA Server Manager - ServerOrchestrator] Provisioned server data:%s%s', 
                        PHP_EOL,
                        json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ));

                } catch (\Exception $e) {
                    error_log(sprintf('#SIYA Server Manager - ServerOrchestrator] Error during server provisioning: %s', $e->getMessage()));
                    $this->subscription->add_order_note(sprintf(
                        "Error occurred during server provisioning:%s%s",
                        PHP_EOL,
                        $e->getMessage()
                    ));
                    $this->subscription->update_status('on-hold');
                    return;
                }
            }

            // Check latest server status flags
            $this->server_provisioned_status = get_post_meta($this->server_post_id, 'arsol_server_provisioned_status', true);    
            
            error_log(sprintf('#010b [SIYA Server Manager - ServerOrchestrator] Provisioned status: %s', $this->server_provisioned_status));
            
            if ($this->server_provisioned_status == 1) {

                $metadata = $server_post_instance->get_meta_data();

                $this->server_provisioned_status = $metadata['arsol_server_provisioned_status'] ?? null;
                $this->server_provisioned_id = $metadata['arsol_server_provisioned_id'] ?? null;
                $this->server_provisioned_name = $metadata['arsol_server_provisioned_name'] ?? null;
                $this->server_provisioned_os = $metadata['arsol_server_provisioned_os'] ?? null;
                $this->server_provisioned_ipv4 = $metadata['arsol_server_provisioned_ipv4'] ?? null;
                $this->server_provisioned_ipv6 = $metadata['arsol_server_provisioned_ipv6'] ?? null;
                $this->server_provisioned_root_password = $metadata['arsol_server_provisioned_root_password'] ?? null;
                $this->server_provisioned_date = $metadata['arsol_server_provisioned_date'] ?? null;
                $this->server_provisioned_remote_status = $metadata['arsol_server_provisioned_remote_status'] ?? null;
                $this->server_provisioned_remote_raw_status = $metadata['arsol_server_provisioned_remote_raw_status'] ?? null;
            }

             error_log(sprintf('#011 [SIYA Server Manager - ServerOrchestrator] Full Metadata: %s%s',
                PHP_EOL,
                json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ));
       
            error_log('Milestone 6');

            // Step 2: Schedule asynchronous action with predefined parameters to complete server provisioning
            $this->schedule_action('arsol_update_server_status', [
                'server_provider'           => $this->server_provider_slug,
                'connect_server_manager'    => $this->connect_server_manager,
                'server_manager'            => $this->server_manager,
                'server_provisioned_id'     => $this->server_provisioned_id,
                'subscription'              => $this->subscription,
                'target_status'             => 'active',
                'server_post_id'            => $this->server_post_id,
                'poll_interval'             => 10,
                'time_out'                  => 120
            ]);

            error_log('#012 [SIYA Server Manager - ServerOrchestrator] Scheduled background server status update for subscription ' . $this->subscription_id);

            error_log(sprintf('#013 [SIYA Server Manager - ServerOrchestrator] Provisioned server ID: %s', $this->server_provisioned_id));

        } catch (\Exception $e) {
            $this->handle_exception($e, $subscription, 'Error in server completion');
        }
    }

    // Step 3: Update server status 
    public function start_update_server_status($args) {
       
       error_log('Milestone 7');
 
        error_log('#015 [SIYA Server Manager - ServerOrchestrator] scheduled server status update started');
        $server_provider_slug = $args['server_provider'];
        $server_manager = $args['server_manager'];
        $connect_server_manager = $args['connect_server_manager'];
        $server_provisioned_id = $args['server_provisioned_id'];
        $subscription = $args['subscription'];
        $target_status = $args['target_status'];
        $server_post_id = $args['server_post_id'];
        $poll_interval = $args['poll_interval'];
        $time_out = $args['time_out'];

        error_log('#016 [SIYA Server Manager - ServerOrchestrator] HOYO >>>>> Provisioned ID ' . $server_provisioned_id);

        $start_time = time();
        while ((time() - $start_time) < $time_out) {
            try {
               
                $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);

                error_log('#017 [SIYA Server Manager - ServerOrchestrator] Checking status: ' . print_r($remote_status, true));
                if ($remote_status['provisioned_remote_status'] === $target_status) {
                    error_log('#018 [SIYA Server Manager - ServerOrchestrator] Remote status matched target status: ' . $target_status);

                    ///Here we adjutst hetzner 

                    $server_deployed_status = get_post_meta($server_post_id, 'arsol_server_deployed_status', true);
                    
                    if (!$server_deployed_status && $connect_server_manager === 'yes') {
                        error_log('#019 [SIYA Server Manager - ServerOrchestrator] Initializing RunCloud deployment');
                        $this->runcloud = new Runcloud();  
                        $this->deploy_to_runcloud_and_update_metadata($server_post_id, $subscription);
                    } else {
                        error_log('#020 [SIYA Server Manager - ServerOrchestrator] Server ready, no Runcloud deployment needed');
                    }

                }
                sleep($poll_interval);
            } catch (\Exception $e) {
                error_log("#021 [SIYA Server Manager - ServerOrchestrator] Error fetching server status: " . $e->getMessage());
                return false;
            }
        }
        error_log("#022 [SIYA Server Manager - ServerOrchestrator] Server status update timed out for server post ID: " . $server_post_id);
        return false;
    }

    // Step 4 (Optional): Deploy to RunCloud and update server metadata
    protected function deploy_to_runcloud_and_update_metadata($server_post_id, $subscription) {
        error_log(sprintf('#023 [SIYA Server Manager - ServerOrchestrator] Starting deployment to RunCloud for subscription %d', $this->subscription_id));

        // Get server metadata from post
        $this->server_post_id = $server_post_id;
        $server_post_instance = new ServerPost($server_post_id);
        $server_name = 'ARSOL' . $this->subscription_id;
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = $this->server_provider_slug;

        // Get server IP addresses
        $server_ips = $this->get_provisioned_server_ip($this->server_provider_slug, $this->server_provisioned_id);
        $ipv4 = $server_ips['ipv4'];
        $ipv6 = $server_ips['ipv6'];
        
        // Save IP addresses to post meta so that it is available for RunCloud deployment Investigate why this is only needed for DigitalOcean but not Vultr an Hetzner 
        if (!empty($ipv4)) {
            update_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', $ipv4);
        }
        if (!empty($ipv6)) {
            update_post_meta($server_post_id, 'arsol_server_provisioned_ipv6', $ipv6);
        }

        // Milestone 1: Log IP addresses
        error_log(sprintf('#024 [SIYA Server Manager - ServerOrchestrator] Milestone X1: Server IP Addresses:%sIPv4: %s%sIPv6: %s', 
            PHP_EOL,
            $ipv4,
            PHP_EOL,
            $ipv6
        ));

        if (empty($ipv4)) {
            error_log('#025 [SIYA Server Manager - ServerOrchestrator] Error: IPv4 address is empty.');
            $subscription->add_order_note('RunCloud deployment failed: IPv4 address is empty.');
            return;
        }

        error_log('Milestone X2');
        
        // Initialize RunCloud & Deploy to RunCloud
        $this->runcloud = new Runcloud();
        error_log('Milestone X2b');
        
        $runcloud_response = $this->runcloud->create_server_in_server_manager(
            $server_name,
            $ipv4,
            $web_server_type,
            $installation_type, 
            $provider
        );

        error_log('Milestone X3');

        // Debug log the RunCloud response
        error_log(sprintf(
            '#026 [SIYA Server Manager - ServerOrchestrator] RunCloud Response:%s%s',
            PHP_EOL, 
            print_r($runcloud_response, true)
        ));

        if ($runcloud_response['status'] == 200 || $runcloud_response['status'] == 201) {

            // Successful Log
            error_log('#027 [SIYA Server Manager - ServerOrchestrator] RunCloud deployment successful');

            // Update server metadata
            $metadata = [
                'arsol_server_deployed_id' => json_decode($runcloud_response['body'], true)['id'] ?? null,
                'arsol_server_deployment_date' => current_time('mysql'),
                'arsol_server_deployed_status' => 1,
                'arsol_server_connection_status' => 0,
                'arsol_server_manager' => 'runcloud'  // Changed from arsol_server_deployment_manager
            ];
            $server_post_instance->update_meta_data($this->server_post_id, $metadata);

                // TODO FIX THIS
            // Successful API subscription note for RunCloud deployment
          /* $subscription->add_order_note(sprintf(
                "RunCloud deployment successful with Response body: %s",
                $runcloud_response['body']
            )); */

            error_log(sprintf('#028 [SIYA Server Manager - ServerOrchestrator] Step 5: Deployment to RunCloud completed for subscription %d', $this->subscription_id));

            // Trigger the connection to the provisioned server
            $this->connect_server_manager($server_post_id);

        } elseif (!isset($runcloud_response['status']) || !in_array($runcloud_response['status'], [200, 201])) {
            error_log('#029 [SIYA Server Manager - ServerOrchestrator] RunCloud deployment failed with status: ' . $runcloud_response['status']);
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed.\nStatus: %s\nResponse body: %s\nFull response: %s",
                $runcloud_response['status'],
                $runcloud_response['body'],
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error
        } elseif (is_wp_error($runcloud_response)){

            error_log('#030 [SIYA Server Manager - ServerOrchestrator] RunCloud deployment failed with WP_Error: ' . $runcloud_response->get_error_message());
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed (WP_Error).\nError message: %s\nFull response: %s",
                $runcloud_response->get_error_message(),
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error
        } else {

            error_log('#031 [SIYA Server Manager - ServerOrchestrator] RunCloud deployment failed with unknown error');
            $subscription->add_order_note(sprintf(
                "RunCloud deployment failed with unknown error.\nFull response: %s",
                print_r($runcloud_response, true)
            ));
            $subscription->update_status('on-hold'); // Switch subscription status to on hold
            return; // Exit the function after logging the error

        }
    }

    // New method to connect server manager to provisioned server
    protected function connect_server_manager($server_post_id) {

        // TODO 
        // ADD validation for servers that have Runcloud Deployed
       
 
        error_log ('Milestone X4');

        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        if ($subscription) {
            error_log('ZZZ Subscription retrieved successfully: ' . $subscription->get_id());
        } else {
            error_log('ZZZ Failed to retrieve subscription for ID: ' . $subscription_id);
        }
        $server_deployed_id = get_post_meta($server_post_id, 'arsol_server_deployed_id', true);
        $server_ip = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', true);

        error_log ('Milestone X5');

        $this->server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        $ssh_private_key = get_post_meta($server_post_id, 'arsol_ssh_private_key', true);
        
        $ssh_username = get_post_meta($server_post_id, 'arsol_ssh_username', true);

        error_log('Milestone X6');

        $ssh_port = 22;

        error_log('Milestone X7');

        error_log(sprintf(
            '[SIYA Server Manager - ServerOrchestrator] Connecting server manager to provisioned server with ID: %s and IP: %s',
            $server_deployed_id,
            $server_ip
        ));

        error_log('Milestone X8');       

        // Open ports before connecting server manager
        $open_ports_result = $this->assign_firewall_rules_to_server($this->server_provider_slug, $this->server_provisioned_id);
        if (!$open_ports_result) {
            error_log('[SIYA Server Manager - ServerOrchestrator] Failed to open ports for server.');
            $subscription->add_order_note('Failed to open ports for server.');
            return;
        }

        error_log('Milestone X9');

        if ($server_deployed_id && $server_ip) {
            
            $this->runcloud = new Runcloud();
            
            try {
                $connection_result = $this->runcloud->execute_installation_script_on_server($server_post_id);
            } catch (\Exception $e) {
                error_log(sprintf(
                    '[SIYA Server Manager - ServerOrchestrator] Failed to connect server manager to provisioned server: %s',
                    $e->getMessage()
                ));
                $subscription->add_order_note(sprintf(
                    'Failed to connect server manager to provisioned server: %s',
                    $e->getMessage()
                ));
                return;
            }

            if (is_wp_error($connection_result)) {
                error_log(sprintf(
                    '[SIYA Server Manager - ServerOrchestrator] Failed to connect server manager to provisioned server: %s',
                    $connection_result->get_error_message()
                ));
                $subscription->add_order_note(sprintf(
                    'Failed to connect server manager to provisioned server: %s',
                    $connection_result->get_error_message()
                ));
            } else {
               
                error_log('[SIYA Server Manager - Server Orchestrator] Successfully executed script on server.');
               // $subscription->add_order_note('Successfully connected server manager to provisioned server.');

                // Schedule finish_server_connection using Action Scheduler
                as_schedule_single_action(time() + 5, 
                    'arsol_verify_server_manager_connection_hook', 
                    [[
                        'subscription_id' => $subscription->get_id(),
                        'server_post_id' => $server_post_id,
                        'server_id' => $server_deployed_id, // Optional: if you need to reference server_id in finish method
                        'ssh_host' => $server_ip,
                        'ssh_username' => $ssh_username,
                        'ssh_private_key' => $ssh_private_key,
                        'ssh_port' => $ssh_port
                    ]],  
                    'arsol_class_server_orchestrator');

                error_log('[SIYA Server Manager][RunCloud] Scheduled finish_server_connection action.');
            }

        } else {
            error_log('[SIYA Server Manager - ServerOrchestrator] Missing server ID or IP address for connection.');
            $subscription->add_order_note('Missing server ID or IP address for connection.');
        }
    }

    // Step 4 (Optional): Finish server connection
    public function verify_server_manager_connection($args) {
        $subscription_id = $args['subscription_id'];
        $server_post_id = $args['server_post_id'];
        $server_id = $args['server_id'];
        $ssh_host = $args['ssh_host'];
        $ssh_username = $args['ssh_username'];
        $ssh_private_key = $args['ssh_private_key'];
        $ssh_port = $args['ssh_port'];

        error_log(sprintf(
            '[SIYA Server Manager - ServerOrchestrator] Finishing server connection for subscription %d',
            $subscription_id
        ));


        /*

        $this->runcloud = new Runcloud();

        $connection_result = $this->runcloud->connect_server_manager_to_provisioned_server(
            $server_id,
            $ssh_host,
            $ssh_username,
            $ssh_private_key,
            $ssh_port
        );

        if (is_wp_error($connection_result)) {
            error_log(sprintf(
                '[SIYA Server Manager - ServerOrchestrator] Failed to connect server manager to provisioned server: %s',
                $connection_result->get_error_message()
            ));
            $subscription->add_order_note(sprintf(
                'Failed to connect server manager to provisioned server: %s',
                $connection_result->get_error_message()
            ));
        } else {
            error_log('[SIYA Server Manager - ServerOrchestrator] Successfully connected server manager to provisioned server.');
            $subscription->add_order_note('Successfully connected server manager to provisioned server.');
        }

        */
    }


























    // Step 5: Schedule server shutdown
    public function start_server_shutdown($subscription) {
        $subscription_id = $subscription->get_id();
        $server_post_instance = new ServerPost();
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
        
        if (!$server_post) {
            error_log('#032 [SIYA Server Manager - ServerOrchestrator] No server post found for shutdown.');
            return;
        }

        $this->server_circuit_breaker_status = get_post_meta($server_post->post_id, 'arsol_server_circuit_breaker_status', true);
        if ($this->server_circuit_breaker_status == 'tripped') {
            error_log('#033 [SIYA Server Manager - ServerOrchestrator] Server circuit breaker for subscription ' .  $subscription_id . ' is in the tripped position. Skipping shutdown.');
            return;
        }

        $server_post_id = $server_post->post_id;
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);

        // New check: Ensure server ID exists
        if (empty($server_provisioned_id)) {
            error_log('[SIYA Server Manager] Server provisioning ID not found, skipping shutdown.');
            return;
        }

        // New check: Ensure remote server exists
        $this->initialize_server_provider($server_provider_slug);
        $status = $this->server_provider->get_server_status($server_provisioned_id);
        if (is_wp_error($status) || !isset($status['provisioned_remote_status'])) {
            error_log('[SIYA Server Manager] Remote server not found, skipping shutdown.');
            return;
        }

        $this->server_provisioned_remote_status = get_post_meta($server_post_id, 'arsol_server_provisioned_remote_status', true);
        
        
        update_post_meta($server_post_id, 'arsol_server_suspension', 'pending-suspension');

       
       if ($this->server_provisioned_remote_status != 'active' || $this->server_provisioned_remote_status == 'starting') {
        error_log('#034 [SIYA Server Manager - ServerOrchestrator] Server status for ' . $server_post_id . ' is ' . $this->server_provisioned_remote_status . ' - only active servers can be shut down');
            update_post_meta($server_post_id, 'arsol_server_suspension', 'yes');
            return;
        } else {

            as_schedule_single_action(
                time(),
                'arsol_server_shutdown',
                [[
                    'subscription_id' => $subscription_id,
                    'server_post_id' => $server_post_id,
                    'server_provider_slug' => $server_provider_slug,
                    'server_provisioned_id' => $server_provisioned_id,
                ]],
                'arsol_class_server_orchestrator'
            );

        }

    }

    // Step 5: Finish server shutdown
    public function finish_server_shutdown($args) {
        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $server_provider_slug = $args['server_provider_slug'] ?? null;
        $server_provisioned_id = $args['server_provisioned_id'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;

        if (!$subscription_id || !$server_post_id || !$server_provider_slug || !$server_provisioned_id) {
            error_log('#035 [SIYA Server Manager - ServerOrchestrator] Missing parameters for shutdown.');
            return;
        }

        // Shutdown server
        $this->initialize_server_provider($server_provider_slug);
        $this->server_provider->shutdown_server($server_provisioned_id);

        // Update server suspension status
        $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);

        error_log(sprintf('#036 [SIYA Server Manager - ServerOrchestrator] Updated remote status metadata for server post ID %d: %s', $server_post_id, $remote_status['provisioned_remote_status']));

        // Verify server shutdown
        if ($remote_status['provisioned_remote_status'] === 'off') {
            error_log('#037 [SIYA Server Manager - ServerOrchestrator] Server ' . $server_post_id . ' successfully shut down.');
            update_post_meta($server_post_id, 'arsol_server_suspension', 'yes');
        } else {
            error_log('#038 [SIYA Server Manager - ServerOrchestrator] Server shutdown verification failed. Current status: ' . $remote_status['provisioned_remote_status']);

            if ($retry_count < 5) {
                error_log('#039 [SIYA Server Manager - ServerOrchestrator] Retrying shutdown in 1 minute. Attempt: ' . ($retry_count + 1));
                as_schedule_single_action(
                    time() + 60, // Retry in 1 minute
                    'arsol_server_shutdown',
                    [[
                        'subscription_id' => $subscription_id,
                        'server_post_id' => $server_post_id,
                        'server_provider_slug' => $server_provider_slug,
                        'server_provisioned_id' => $server_provisioned_id,
                        'retry_count' => $retry_count + 1
                    ]],
                    'arsol_class_server_orchestrator'
                );
            } else {
                error_log('#040 [SIYA Server Manager - ServerOrchestrator] Maximum retry attempts reached. Server shutdown failed.');
            }
        }
    }

    // Step 5: Schedule server powerup
    public function start_server_powerup($subscription) {
        $subscription_id = $subscription->get_id();
        $server_post_instance = new ServerPost();
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
        $server_post_id = $server_post->post_id;

        if (!$server_post) {
            error_log('#041 [SIYA Server Manager - ServerOrchestrator] No server post found for powerup.');
            return;
        }

        $this->server_provisioned_remote_status = get_post_meta($server_post_id, 'arsol_server_provisioned_remote_status', true);
        
        // Update server suspension status
        update_post_meta($server_post_id, 'arsol_server_suspension', 'pending-reconnection');

        // Check if server is already powered on
        if ($this->server_provisioned_remote_status != 'off') {
        error_log('#042 [SIYA Server Manager - ServerOrchestrator] Server status for ' . $server_post_id . ' is ' . $this->server_provisioned_remote_status . ' - only shut down servers can be powered on');
            return;
        } else {

            $server_post_id = $server_post->post_id;
            $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
            $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);

            // New check: Ensure server ID exists
            if (empty($server_provisioned_id)) {
                error_log('[SIYA Server Manager] Server provisioning ID not found, skipping powerup.');
                return;
            }

            // New check: Ensure remote server exists
            $this->initialize_server_provider($server_provider_slug);
            $status = $this->server_provider->get_server_status($server_provisioned_id);
            if (is_wp_error($status) || !isset($status['provisioned_remote_status'])) {
                error_log('[SIYA Server Manager] Remote server not found, skipping powerup.');
                return;
            }

            as_schedule_single_action(
                time(),
                'arsol_server_powerup',
                [[
                    'subscription_id' => $subscription_id,
                    'server_post_id' => $server_post_id,
                    'server_provider_slug' => $server_provider_slug,
                    'server_provisioned_id' => $server_provisioned_id
                ]],
                'arsol_class_server_orchestrator'
            );
        }
    }

    // Step 5: Finish server powerup
    public function finish_server_powerup($args) {
        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $server_provider_slug = $args['server_provider_slug'] ?? null;
        $server_provisioned_id = $args['server_provisioned_id'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;

        if (!$subscription_id || !$server_post_id || !$server_provider_slug || !$server_provisioned_id) {
            error_log('#043 [SIYA Server Manager - ServerOrchestrator] Missing parameters for powerup.');
            return;
        }

        // Power up server
        $this->initialize_server_provider($server_provider_slug);   
        $this->server_provider->poweron_server($server_provisioned_id);
        
        // Get remote status and update metadata
        $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);

        error_log(sprintf('#044 [SIYA Server Manager - ServerOrchestrator] Updated remote status metadata for server post ID %d: %s', $server_post_id, $remote_status['provisioned_remote_status']));

        // Verify server powered up
        if ($remote_status['provisioned_remote_status'] === 'active') {
            error_log('#045 [SIYA Server Manager - ServerOrchestrator] Server ' . $server_post_id . ' successfully powered up.');
            update_post_meta($server_post_id, 'arsol_server_suspension', 'no');
        } else {
            error_log('#046 [SIYA Server Manager - ServerOrchestrator] Server powerup verification failed. Current status: ' . $remote_status['provisioned_remote_status']);

            if ($retry_count < 5) {
                error_log('#047 [SIYA Server Manager - ServerOrchestrator] Retrying powerup in 1 minute. Attempt: ' . ($retry_count + 1));
                as_schedule_single_action(
                    time() + 60, // Retry in 1 minute
                    'arsol_server_powerup',
                    [[
                        'subscription_id' => $subscription_id,
                        'server_post_id' => $server_post_id,
                        'server_provider_slug' => $server_provider_slug,
                        'server_provisioned_id' => $server_provisioned_id,
                        'retry_count' => $retry_count + 1
                    ]],
                    'arsol_class_server_orchestrator'
                );
            } else {
                error_log('#048 [SIYA Server Manager - ServerOrchestrator] Maximum retry attempts reached. Server powerup failed.');
            }
        }
    }

    // Start server deletion process
    public function start_server_deletion($subscription) {
        
        error_log('#050 [SIYA Server Manager - ServerOrchestrator] Starting deletion process');
        
        if (!$subscription) {
            error_log('#051 [SIYA Server Manager - ServerOrchestrator] Subscription not found');
            return;
        } 

        $subscription_id = $subscription->get_id();

        $linked_server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        if (!$linked_server_post_id) {
            return;
        }

        error_log('#052 [SIYA Server Manager - ServerOrchestrator] Linked server post ID found: ' . $linked_server_post_id);

        update_post_meta($linked_server_post_id, 'arsol_server_suspension', 'pending-deletion');

        as_schedule_single_action(
            time(),
            'arsol_finish_server_deletion',
            [[
                'subscription_id' => $subscription_id,
                'server_post_id' => $linked_server_post_id
            ]],
            'arsol_class_server_orchestrator'
        );
        error_log('#056 [SIYA Server Manager - ServerOrchestrator] Milestone 2: Scheduled server deletion for' . $subscription_id . ' Server post ID' . $linked_server_post_id);
    
    }

    // Finish server deletion process
    public function finish_server_deletion($args) {

        error_log('Server deleition');

        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;

        if (!$subscription_id || !$server_post_id) {
            error_log('#057 [SIYA Server Manager - ServerOrchestrator] Milestone 3: Missing parameters for deletion.');
            return;
        }

        error_log(sprintf('#057b [SIYA Server Manager - ServerOrchestrator] Passed arguments: %s', json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
     
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        $server_deployed_server_id = get_post_meta($server_post_id, 'arsol_server_deployed_id', true);

        error_log(sprintf(
            '#058 [SIYA Server Manager - ServerOrchestrator] Server Provider Slug: %s, Server Provisioned ID: %s, Server Deployed Server ID: %s',
            $server_provider_slug,
            $server_provisioned_id,
            $server_deployed_server_id
        ));

        // Delete provisioned server
        if ($server_provisioned_id) {
            error_log('#063 [SIYA Server Manager - ServerOrchestrator] Milestone 9: Deleting provisioned server with ID ' . $server_provisioned_id);
            
            $this->initialize_server_provider($server_provider_slug);

            error_log('Milestone 9b');

            try {
                $deleted = $this->server_provider->destroy_server($server_provisioned_id);
            } catch (\Exception $e) {
                error_log(sprintf('#065a [SIYA Server Manager - ServerOrchestrator] Error during server destruction: %s', $e->getMessage()));
                $deleted = false;
            }

            error_log('Milestone 9c');

            if ($deleted) {
                update_post_meta($server_post_id, 'arsol_server_provisioned_status', 0);
                error_log('#064 [SIYA Server Manager - ServerOrchestrator] Milestone 10: Provisioned server deleted successfully.');
            } else {
                error_log('#065 [SIYA Server Manager - ServerOrchestrator] Milestone 11: Provisioned server deletion failed.');
                if ($retry_count < 5) {
                    as_schedule_single_action(
                        time() + 60,
                        'arsol_finish_server_deletion',
                        [[
                            'subscription_id' => $subscription_id,
                            'server_post_id' => $server_post_id,
                            'retry_count' => $retry_count + 1
                        ]],
                        'arsol_class_server_orchestrator'
                    );
                    return;
                } else {
                    error_log('#066 [SIYA Server Manager - ServerOrchestrator] Milestone 12: Maximum retry attempts reached. Provisioned server deletion failed.');
                    return;
                }
            }
        }

        // Delete RunCloud server
        if ($server_deployed_server_id) {
            error_log('#059 [SIYA Server Manager - ServerOrchestrator] Milestone 5: Deleting RunCloud server with ID ' . $server_deployed_server_id);
            $this->runcloud = new Runcloud();
            $deleted = $this->runcloud->delete_server($server_deployed_server_id);

            if ($deleted) {
                update_post_meta($server_post_id, 'arsol_server_deployed_status', 0);
                error_log('#060 [SIYA Server Manager - ServerOrchestrator] Milestone 6: RunCloud server deleted successfully.');
            } else {
                error_log('#061 [SIYA Server Manager - ServerOrchestrator] Milestone 7: RunCloud server deletion failed.');
                if ($retry_count < 5) {
                    as_schedule_single_action(
                        time() + 60,
                        'arsol_finish_server_deletion',
                        [[
                            'subscription_id' => $subscription_id,
                            'server_post_id' => $server_post_id,
                            'retry_count' => $retry_count + 1
                        ]],
                        'arsol_class_server_orchestrator'
                    );
                    return;
                } else {
                    error_log('#062 [SIYA Server Manager - ServerOrchestrator] Milestone 8: Maximum retry attempts reached. RunCloud server deletion failed.');
                    return;
                }
            }
        }


        // Update server suspension status to destroyed
        update_post_meta($server_post_id, 'arsol_server_suspension', 'destroyed');
        error_log('#067 [SIYA Server Manager - ServerOrchestrator] Milestone 13: Server suspension status updated to destroyed.');

        // Delete the server post
        wp_delete_post($server_post_id, true);
        error_log('#067a [SIYA Server Manager - ServerOrchestrator] Milestone 13a: Server post deleted.');
        
        // Update the server_linked_post_id on the subscription to destroyed
        $subscription = wcs_get_subscription($subscription_id);
        $subscription->update_meta_data('arsol_linked_server_post_id', 'Server destroyed');
        $subscription->save(); // Save the subscription to persist changes
        
    }

    // Helper Methods

    private function create_and_update_server_post($server_product_id, $server_post_instance, $subscription) {
        $post_id = $server_post_instance->create_server_post($this->subscription_id);
        
        // Update server post metadata
        if ($post_id) {
            $this->server_post_id = $post_id;
            $subscription->add_order_note(
                'Server post created successfully with ID: ' . $this->server_post_id
            );
            error_log('[SIYA Server Manager] Created server post with ID: ' . $this->server_post_id);

            // Generate SSH key pair
            $ssh_keys = $this->generate_key_pair();
            error_log('[SIYA Server Manager] Generated SSH key pair for server post ID: ' . $this->server_post_id);

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
                'arsol_server_max_staging_sites' => $server_product->get_meta('_arsol_max_staging_sites', true),
                'arsol_server_suspension' => 'no', // Add suspension status
                'arsol_ssh_private_key' => $ssh_keys['private_key'],
                'arsol_ssh_public_key' => $ssh_keys['public_key'],
                'arsol_ssh_username' => 'ARSOL' . $this->subscription_id // Add SSH username
            ];
            $server_post_instance->update_meta_data($this->server_post_id, $metadata);

            $subscription->update_meta_data('arsol_linked_server_post_id', $this->server_post_id);
            $subscription->save();

            // Check if we need to connect to the server manager
            /*
            $this->connect_server_manager = $server_product->get_meta('_arsol_connect_server_manager', true);
            if ($this->connect_server_manager === 'yes') {
                $this->runcloud = new Runcloud();
                $installation_script = $this->runcloud->get_installation_script($this->server_provisioned_id);
                update_post_meta($this->server_post_id, 'arsol_server_manager_installation_script', $installation_script);
            }
                */

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

    private function generate_key_pair() {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        // Extract the private key from $res to $private_key
        openssl_pkey_export($res, $private_key);

        // Extract the public key from $res to $public_key
        $public_key_details = openssl_pkey_get_details($res);
        $public_key = $public_key_details['key'];

        return [
            'private_key' => $private_key,
            'public_key' => $public_key
        ];
    }

    // Step 2: Provision server and update server post metadata
    protected function provision_server_at_provider($subscription) {
        try {
            // Define variables within the method
            $subscription_id = $subscription->get_id();
            $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
            $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);

            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Provisioning server for subscription ID: %d', $subscription_id));
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Server post ID: %d', $server_post_id));
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Server provider slug: %s', $server_provider_slug));


            // Check if the server post is an arsol_server and the provisioned status is 0
            $provisioned_status = get_post_meta($server_post_id, 'arsol_server_provisioned_status', true);

            if ($provisioned_status == 1) {
                $error_message = "Server post already provisioned.";
                error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] %s', $error_message));
                $subscription->add_order_note($error_message);
                return;
            }

            error_log(sprintf('[ 1 SIYA Server Manager - ServerOrchestrator] Server post not provisioned. Proceeding with provisioning...'));

            // Initialize the provider with explicit server provider slug
            $this->initialize_server_provider($server_provider_slug);

            error_log(sprintf('[2 SIYA Server Manager - ServerOrchestrator] Server provider initialized: %s', $server_provider_slug));
            
            // Use the initialized provider
            $server_data = $this->server_provider->provision_server($server_post_id);

            error_log(sprintf('[3 SIYA Server Manager - ServerOrchestrator] Provisioned server data:%s%s', 
                PHP_EOL,
                json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ));
            
            if (!$server_data) {
                $error_message = "Failed to provision server";
                error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] %s', $error_message));
                $subscription->add_order_note($error_message);
                $subscription->update_status('on-hold');
                throw new \Exception($error_message);
            }

            $subscription->update_status('active');

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
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] %s', $success_message));
            $subscription->add_order_note($success_message);

            // Update server post metadata using the standardized data
            $server_post_instance = new ServerPost;
            $metadata = [
                'arsol_server_provisioned_status' => 1,
                'arsol_server_provisioned_id' => $server_data['provisioned_id'],
                'arsol_server_provisioned_name' => $server_data['provisioned_name'],
                'arsol_server_provisioned_os' => $server_data['provisioned_os'],
                'arsol_server_provisioned_os_version' => $server_data['provisioned_os_version'], // Add this line
                'arsol_server_provisioned_ipv4' => $server_data['provisioned_ipv4'],
                'arsol_server_provisioned_ipv6' => $server_data['provisioned_ipv6'],
                'arsol_server_provisioning_provider' => $server_provider_slug,
                'arsol_server_provisioned_root_password' => $server_data['provisioned_root_password'],
                'arsol_server_provisioned_date' => $server_data['provisioned_date'],
                'arsol_server_provisioned_remote_status' => $server_data['provisioned_remote_status'],
                'arsol_server_provisioned_remote_raw_status' => $server_data['provisioned_remote_raw_status']
            ];
            $server_post_instance->update_meta_data($server_post_id, $metadata);

            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Provider Status Details:%sRemote Status: %s%sRaw Status: %s', 
                PHP_EOL,
                $server_data['provisioned_remote_status'],
                PHP_EOL,
                $server_data['provisioned_remote_raw_status']
            ));

            $subscription->add_order_note(sprintf(
                "Server metadata updated successfully:%s%s",
                PHP_EOL,
                print_r($metadata, true)
            ));

            return $server_data;
        } catch (\Exception $e) {
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Exception caught: %s', $e->getMessage()));
            throw $e;
        }
    }

    private function get_provisioned_server_ip($server_provider_slug, $server_provisioned_id) {
        $this->initialize_server_provider($server_provider_slug);
        return $this->server_provider->get_server_ip($server_provisioned_id);
    }


    public function check_existing_server($server_post_instance, $subscription) {
       
        $server_post = $server_post_instance->get_server_post_by_subscription($subscription);
       
       
        if ($server_post) {
            error_log('[SIYA Server Manager - ServerOrchestrator] Found existing server: ' . $server_post->post_id);
            return $server_post;
        }

        error_log('[SIYA Server Manager - ServerOrchestrator] No existing server found');
        return false;
    }


    public function extract_server_product_from_subscription($subscription) {
        error_log('[SIYA Server Manager - ServerOrchestrator] Starting server product extraction from subscription ' . $subscription->get_id());
    
        // Ensure the subscription object has the required method and is valid
        if (!method_exists($subscription, 'get_items') || !$subscription->get_items()) {
            error_log('[SIYA Server Manager - ServerOrchestrator] No items found in subscription');
            return false;
        }
    
        $matching_product_ids = [];
    
        // Loop through all items in the subscription
        foreach ($subscription->get_items() as $item) {
            // Get the product associated with the item
            $product = $item->get_product();
    
            if (!$product) {
                error_log('[SIYA Server Manager - ServerOrchestrator] Invalid product in subscription item');
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
                    error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Parent product not found for variation ID: %d', $product_id));
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
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Product ID %d has _arsol_server value: %s', 
                $product_id, 
                print_r($meta_value, true)
            ));
    
            // Check if the meta value is 'yes'
            if ($meta_value === 'yes') {
                error_log('[SIYA Server Manager - ServerOrchestrator] Found matching server product: ' . $product_id);
                $matching_product_ids[] = $product_id;
            }
        }
    
        // Handle the results
        if (empty($matching_product_ids)) {
            error_log('[SIYA Server Manager - ServerOrchestrator] No matching server products found');
            return false;
        }
    
        if (count($matching_product_ids) > 1) {
            error_log('[SIYA Server Manager - ServerOrchestrator] Multiple server products found: ' . implode(', ', $matching_product_ids));
            $subscription->add_order_note('Multiple server products found with _arsol_server = yes. Please review the subscription.');
            return null;
        }
    
        error_log('[SIYA Server Manager - ServerOrchestrator] Returning single matching product ID: ' . $matching_product_ids[0]);
        return $matching_product_ids[0];
    }

    // Modified helper method to initialize server provider
    private function initialize_server_provider($server_provider_slug = null) {
        // Use passed server provider slug or get from metadata if not provided
        if ($server_provider_slug) {
            $this->server_provider_slug = $server_provider_slug;
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

    private function update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id) {
        
        $this->initialize_server_provider($server_provider_slug);

        // Get remote status
        $remote_status = $this->server_provider->get_server_status($server_provisioned_id);
        $provisioned_remote_status = $remote_status['provisioned_remote_status'] ?? null;
        $provisioned_remote_raw_status = $remote_status['provisioned_remote_raw_status'] ?? null;

        // Update server status metadata
        $server_post_instance = new ServerPost($server_post_id);
        $server_post_instance->update_meta_data($server_post_id, [
            'arsol_server_provisioned_remote_status' => $provisioned_remote_status,
            'arsol_server_provisioned_remote_raw_status' => $provisioned_remote_raw_status,
            'arsol_server_provisioned_remote_status_time' => current_time('mysql'),
        ]);

        return $remote_status;
    }

    // New helper method to schedule actions
    private function schedule_action($hook, $args) {
        as_schedule_single_action(time(), $hook, [$args], 'arsol_class_server_orchestrator');
    }

    // New helper method to handle exceptions
    private function handle_exception($e, $subscription, $message) {
        error_log(sprintf('#005 [SIYA Server Manager - ServerOrchestrator] %s: %s', $message, $e->getMessage()));
        $subscription->add_order_note(sprintf("%s:%s%s", $message, PHP_EOL, $e->getMessage()));
        $subscription->update_status('on-hold');
    }

    // New method to open ports at the provider
    private function assign_firewall_rules_to_server($server_provider_slug, $server_provisioned_id) {
        $this->initialize_server_provider($server_provider_slug);
        $result = $this->server_provider->assign_firewall_rules_to_server($server_provisioned_id);
        if (!$result) {
            error_log('Failed to open ports at provider. Response: ' . json_encode($result));
        }
        return $result;
    }

}




