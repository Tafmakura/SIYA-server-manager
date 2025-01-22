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
    protected $server_circuit_breaker_position;


    /**
     * Numeric Status Values:
     * 0 = Not started
     * 1 = In progress
     * 2 = Completed
     * -1 = Failed
     * -2 = Deleted
     */



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
        add_action('arsol_wait_for_server_active_state_hook', array($this, 'wait_for_server_active_state'), 20, 1);

        // Add new action hooks for server powerup
        add_action('woocommerce_subscription_status_active', array($this, 'start_server_powerup'), 20, 1);
        add_action('arsol_server_powerup', array($this, 'finish_server_powerup'), 20, 1);

        add_action('woocommerce_subscription_status_cancelled', array($this, 'start_server_deletion'), 10, 1);
        add_action('woocommerce_subscription_status_trash', array($this, 'start_server_deletion'), 10, 1);

        // Add new action hook for deploying to RunCloud
        add_action('arsol_start_server_manager_connection_hook', array($this, 'start_server_manager_connection'), 20, 2);

        // Add new action hook for the scheduled server manager connection completion
        add_action('arsol_finish_server_manager_connection_hook', array($this, 'finish_server_manager_connection'), 20, 1);

        // Add new action hooks for server connection
        add_action('arsol_verify_server_manager_connection_hook', [$this, 'verify_server_manager_connection']);

        add_action('arsol_finish_server_deletion_hook', [$this, 'finish_server_deletion']);  

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

            // Place subscription on hold until deployment is done 
            $subscription->add_order_note(
                'Server provisioning started. Subscription will be placed on hold until provisioning is complete. Instruction from payment gateway to change status from Pending to Active will be noted but ignored. '
            );

            $subscription->update_status('on-hold');
           
            // Check if the server post already exists
            $this->server_post_id = get_post_meta($this->subscription_id, 'arsol_linked_server_post_id', true);
            $this->server_post_status = get_post_meta($this->server_post_id, '_arsol_state_05_server_post', true); 

            if ($this->server_post_status != 2) {
                
                try { // Step 1: Create server post only if it doesn't exist

                    error_log('#002 [SIYA Server Manager - ServerOrchestrator] Creating new server post');
                    $server_post_instance = new ServerPost();
                    $existing_server_post = $this->check_existing_server($server_post_instance, $this->subscription);

                    if (!$existing_server_post) {

                        error_log('#002 [SIYA Server Manager - ServerOrchestrator] creating new server post');

                        $server_post_instance = new ServerPost();
                        $existing_server_post = $this->check_existing_server($server_post_instance, $this->subscription);

                        $server_post = $this->create_and_update_server_post($this->server_product_id, $server_post_instance, $subscription);

                        // Update State meta
                        update_post_meta($this->server_post_id, '_arsol_state_05_server_post', 2);
                    
                    }

                       
                } catch (\Exception $e) {
                        
                    $error_definition = 'Error creating and updating server post';

                    // Capture and rethrow the exception
                    $this->handle_exception($e);
                
                }
            
            } else {
               
                error_log('STATE CHECK (05): Server post okay.');

            }
            
            try { // Step 2: Schedule asynchronous action with predefined parameters to complete server provisioning

                $task_id = uniqid();
                $this->schedule_action('arsol_finish_server_provision', [
                    'subscription_id' => $this->subscription_id,
                    'server_post_id' => $this->server_post_id,
                    'server_product_id' => $this->server_product_id,
                    'server_provider_slug' => $this->server_provider_slug,
                    'task_id' => $task_id
                ]);
                $subscription->add_order_note(
                    'Scheduled background server provisioning.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                );

                error_log('#004 [SIYA Server Manager - ServerOrchestrator] Scheduled background server provision for subscription ' . $this->subscription_id);
            
            } catch (\Exception $e) {

                $error_definition = 'Error scheduling background server provisioning';

                // Capture and rethrow the exception
                $this->handle_exception($e);

            }
     
      
        } catch (\Exception $e) {

            $error_definition = 'Error creating and updating server post > ';
            $this->handle_exception($e);
        
            // Update State meta
            update_post_meta($this->server_post_id, '_arsol_state_05_server_post', -1);

            $this->handle_exception($e);

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($subscription);

            return false; // Add fallback return false

        }

    }
    
    public function finish_server_provision($args) {
        
        try { // Finish server provisioning process (Provision server)

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
            $this->connect_server_manager = $metadata['_arsol_server_manager_required'] ?? null;
            $this->server_provider_slug = $metadata['arsol_server_provider_slug'] ?? null;
            $this->server_group_slug = $metadata['arsol_server_group_slug'] ?? null;
            $this->server_plan_slug = $metadata['arsol_server_plan_slug'] ?? null;
            $this->server_region_slug = $metadata['arsol_server_region_slug'] ?? null;
            $this->server_image_slug = $metadata['arsol_server_image_slug'] ?? null;
            $this->server_max_applications = $metadata['arsol_server_max_applications'] ?? null;
            $this->server_max_staging_sites = $metadata['arsol_server_max_staging_sites'] ?? null;


            // Check if the server has already been provisioned
            $this->server_provisioned_status = get_post_meta($this->server_post_id, '_arsol_state_10_provisioning', true); 
            if ($this->server_provisioned_status != 2) {
              
                try {  // Step 2: Provision server if not already provisioned
 
                    // Initialize the appropriate server provider with the slug
                    $this->initialize_server_provider($this->server_provider_slug);
    
                    // Attempt to provision the server
                    $server_data = $this->provision_server_at_provider($this->subscription);

                    // Update server metadata on successful provisioning
                    update_post_meta($this->server_post_id, '_arsol_state_10_provisioning', 2); // Status 2 indicates success
    
                    error_log(sprintf('#010 [SIYA Server Manager - ServerOrchestrator] Provisioned server data:%s%s', 
                        PHP_EOL,
                        json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ));
    
                } catch (\Exception $e) {

                    $error_definition = 'Error creating and updating server post';

                    // Capture and rethrow the exception
                    $this->handle_exception($e);

                }

            }

            // Check updated server metadata
            $this->server_provisioned_status = get_post_meta($this->server_post_id, '_arsol_state_10_provisioning', true); 
            if ($this->server_provisioned_status == 2) {

               
                try { // Step 3: Schedule asynchronous action with predefined parameters to complete server provisioning

                    // Get created post meta data
                    $metadata = $server_post_instance->get_meta_data();
                    
                    // Load parameters into class properties
                    $this->server_provisioned_status = $metadata['_arsol_state_10_provisioning'] ?? null;
                    $this->server_provisioned_id = $metadata['arsol_server_provisioned_id'] ?? null;
                    $this->server_provisioned_name = $metadata['arsol_server_provisioned_name'] ?? null;
                    $this->server_provisioned_os = $metadata['arsol_server_provisioned_os'] ?? null;
                    $this->server_provisioned_ipv4 = $metadata['arsol_server_provisioned_ipv4'] ?? null;
                    $this->server_provisioned_ipv6 = $metadata['arsol_server_provisioned_ipv6'] ?? null;
                    $this->server_provisioned_root_password = $metadata['arsol_server_provisioned_root_password'] ?? null;
                    $this->server_provisioned_date = $metadata['arsol_server_provisioned_date'] ?? null;
                    $this->server_provisioned_remote_status = $metadata['arsol_server_provisioned_remote_status'] ?? null;
                    $this->server_provisioned_remote_raw_status = $metadata['arsol_server_provisioned_remote_raw_status'] ?? null;
               
                } catch (\Exception $e) {

                    $error_definition = 'Error loading server post metadata';
                    $this->handle_exception($e);

                }

            }
            
            try { // Step 4: Schedule asynchronous action with predefined parameters to complete server provisioning
                
                $task_id = uniqid();

                $this->schedule_action('arsol_wait_for_server_active_state_hook', [
                    'server_provider'           => $this->server_provider_slug,
                    'connect_server_manager'    => $this->connect_server_manager,
                    'server_manager'            => $this->server_manager,
                    'server_provisioned_id'     => $this->server_provisioned_id,
                    'target_status'             => 'active',
                    'server_post_id'            => $this->server_post_id,
                    'poll_interval'             => 10,
                    'time_out'                  => 120,
                    'task_id'                   => $task_id
                ]);

                $this->subscription->add_order_note(
                    'Scheduled background server status update.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                );

                error_log('#012 [SIYA Server Manager - ServerOrchestrator] Scheduled background server status update for subscription ' . $this->subscription_id);

            } catch (\Exception $e) {

                $error_definition = 'Error scheduling background server status update';
                $this->handle_exception($e);

            }
    
        } catch (\Exception $e) {

            $this->handle_exception($e);
            
            // Update server metadata on failed provisioning and trip CB
            update_post_meta($this->server_post_id, '_arsol_state_10_provisioning', -1);

            // Handle the exception and exit
            $this->handle_exception($e);

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);
            
            return false; // Add fallback return false

        }

    }
    
    // Step 3: Wait for server active state (Check server status) 
    public function wait_for_server_active_state($args) {
        error_log('#015 [SIYA Server Manager - ServerOrchestrator] Scheduled server status update started');
        
        $server_provider_slug = $args['server_provider'];
        $server_manager = $args['server_manager'];
        $connect_server_manager = $args['connect_server_manager'];
        $server_provisioned_id = $args['server_provisioned_id'];
        $target_status = $args['target_status'];
        $server_post_id = $args['server_post_id'];
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        $poll_interval = $args['poll_interval'];
        $time_out = $args['time_out'];
        $max_retries = $args['max_retries'] ?? 10; // Default to 10 retries if not provided
        $retry_count = $args['retry_count'] ?? 0;
    
        // Initialize task ID variable
        $task_id = uniqid();
    
        // Fetch current IP status
        $server_ip_status = get_post_meta($server_post_id, '_arsol_state_20_ip_address', true);
    
        // If IP status is not 2, we want to check and update the server status
        if ($server_ip_status != 2) {
            error_log('#016 [SIYA Server Manager - ServerOrchestrator] IP status is not 2. Checking and updating server status.');
    
            // Start the polling loop only if the IPs are not validated yet
            $start_time = time();

            while ((time() - $start_time) < $time_out) {

                try {
                    // Fetch the current server status
                    $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);
                    
                    error_log('#017 [SIYA Server Manager - ServerOrchestrator] Checking remote status: ' . json_encode($remote_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
                    // If remote status matches the target status (e.g., "active")
                    $target_status = 'active';

                    if ($remote_status['provisioned_remote_status'] === $target_status) {
                        error_log('#018 [SIYA Server Manager - ServerOrchestrator] Remote status matched target status: ' . $target_status);
                   
                        try {

                            // Fetch the provisioned server IPs
                            $server_ipv4 = $this->get_provisioned_server_ip($server_provider_slug, $server_provisioned_id, 'ipv4');
                        
                        } catch (\Exception $e) {

                            $error_definition = 'Error fetching provisioned server IP';

                            // Update server metadata on failed provisioning and trip CB
                            update_post_meta($this->server_post_id, '_arsol_state_20_ip_addres', -1);

                            // Handle the exception and exit
                            $this->handle_exception($e);

                            // Trigger the circuit breaker
                            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                            return false; // Add fallback return false

                        }

                        // Get the IPv6 address to add to metadata if available
                        $server_ipv6 = $this->get_provisioned_server_ip($server_provider_slug, $server_provisioned_id, 'ipv6');
                        
                        // Save IP addresses to post meta for RunCloud deployment
                        update_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', $server_ipv4);
                        update_post_meta($server_post_id, 'arsol_server_provisioned_ipv6', $server_ipv6);
                        update_post_meta($server_post_id, '_arsol_state_20_ip_address', 2);

                        // Add order note with IP addresses
                        $success_message = sprintf(
                            'Successfully acquired IP addresses!%sIPv4: %s%sIPv6: %s',
                            PHP_EOL,
                            $server_ipv4 ?: 'Not provided',
                            PHP_EOL,
                            $server_ipv6 ?: 'Not provided'
                        );

                        $subscription->add_order_note($success_message);
                        error_log($success_message);

                        break; // Exit the loop after acquiring the IP addresses
                        
                    } 

                } catch (\Exception $e) {
                   
                    $error_definition = 'Error checking server status';

                    // Handle the exception and exit
                    $this->handle_exception($e);
                    
                    return false; // Add fallback return false

                }
            
                // Timeout check inside the loop
                if ((time() - $start_time) >= $time_out) {
                    // Timeout reached without acquiring the target status
                    error_log("#023 [SIYA Server Manager] Timeout reached for server post ID: " . $server_post_id);
                    $subscription->add_order_note('Server provisioning failed: Timeout reached.');
                    
                    // Check if the retry count is within the allowed maximum
                    if ($retry_count < $max_retries) {
                        error_log(sprintf('#024 [SIYA Server Manager] Retrying server status update. Attempt: %d', $retry_count + 1));

                        // Increment retry count and schedule next retry
                        $task_id = uniqid(); // New task ID for retry
                        as_schedule_single_action(
                            time() + 10, // Retry after 10 seconds
                            'arsol_wait_for_server_active_state_hook',
                            [[
                                'server_provider' => $server_provider_slug,
                                'connect_server_manager' => $connect_server_manager,
                                'server_manager' => $server_manager,
                                'server_provisioned_id' => $server_provisioned_id,
                                'target_status' => $target_status,
                                'server_post_id' => $server_post_id,
                                'poll_interval' => $poll_interval,
                                'time_out' => $time_out,
                                'retry_count' => $retry_count + 1,
                                'task_id' => $task_id
                            ]],
                            'arsol_class_server_orchestrator'
                        );

                        // Add order note for retry
                        $subscription->add_order_note('Could not get a server status. Scheduled a retry.' . PHP_EOL . '(Task ID: ' . $task_id . ')');
                        
                        return false; // Retry

                    }

                    // If max retries have been reached, stop the process
                    return false; // Max retries reached or timeout
                }

                // Wait for the next polling interval before retrying
                sleep($poll_interval);
            }
           
        } else {

            error_log ('STATE CHECK (20): IP status is okay.');
        }    
    
        // Proceed with RunCloud deployment or mark as active
        if ($connect_server_manager === 'yes') {
           
            // Schedule deploy_to_runcloud_and_update_metadata using Action Scheduler (only once)
            as_schedule_single_action(
                time(),
                'arsol_start_server_manager_connection_hook',
                [[
                    'server_post_id' => $server_post_id,
                    'task_id' => $task_id
                ]],
                'arsol_class_server_orchestrator'
            );

            // Add order note to inform the user about scheduling
            $message = 'Scheduled the start of the server manager connection.' . PHP_EOL . '(Task ID: ' . $task_id . ')';
            
            // Add order note for the scheduled action
            $subscription->add_order_note(
                $message
            );

            // Log the message
            error_log($message);

        } else {

            // No server manager required, mark the subscription as active
            $success_message = 'Server is ready, no server manager required. Activating subscription... Good day and good luck!';
            
            // Update subscription status to active
            $subscription->add_order_note($success_message);

            // Log the success message
            error_log($success_message);

            // Activate Subscription
            $subscription->update_status('active');
        }

    }
    
    // Step 4 (Optional): Create server in Runcloud
    public function start_server_manager_connection($args) {
    
        $server_post_id = $args['server_post_id'];
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        error_log(sprintf('#023 [SIYA Server Manager - ServerOrchestrator] Starting deployment to RunCloud for subscription %d', $subscription_id));
    
        // Get server metadata from post
        $server_post_instance = new ServerPost($server_post_id);
        $server_name = 'ARSOL' . $subscription_id;
        $web_server_type = 'nginx';
        $installation_type = 'native';
        $provider = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $ipv4 = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', true);
        $ipv6 = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv6', true);
    
        // Check server_deployed_status to avoid redundant deployment
        $server_deployed_status = get_post_meta($server_post_id, '_arsol_state_30_deployment', true);
    
        // Proceed only if $server_deployed_status is not 2

        if ($server_deployed_status != 2) {

            if (empty($server_deployed_status)) {
                error_log('#025 [SIYA Server Manager - ServerOrchestrator] Server_deployed_status is empty, proceeding with deployment.');
            }
     
            // Initialize RunCloud class if not already initialized
            if (!$this->runcloud) {
                $this->runcloud = new Runcloud();
            }
            
            try {

                // Deploy server to RunCloud
                $runcloud_response = $this->runcloud->create_server_in_server_manager(
                    $server_name,
                    $ipv4,
                    $web_server_type,
                    $installation_type, 
                    $provider
                );

            } catch (\Exception $e) {

                $error_definition = 'Error deploying server to Servermanager';
                
                // Handle the exception
                $this->handle_exception($e);

                return false; // Add fallback return false
            
            }


            // Debug log the RunCloud response
            error_log(sprintf(
                '#027 [SIYA Server Manager - ServerOrchestrator] RunCloud Response:%s%s',
                PHP_EOL, 
                print_r($runcloud_response, true)
            ));

            if ($runcloud_response['status'] == 200 || $runcloud_response['status'] == 201) {
            
                // Update server metadata
                $metadata = [
                    'arsol_server_deployed_id' => json_decode($runcloud_response['body'], true)['id'] ?? null,
                    'arsol_server_deployment_date' => current_time('mysql'),
                    '_arsol_state_30_deployment' => 2, // Set status to 2 on success
                    'arsol_server_connection_status' => 0,
                    'arsol_server_manager' => 'runcloud'  // Changed from arsol_server_deployment_manager
                ];
                $server_post_instance->update_meta_data($server_post_id, $metadata);

                // Add message for successful deployment
                $success_message = sprintf(
                    'Server deployed on server manager successfully!%sProvider: %s%sDeployed ID: %s',
                    PHP_EOL,
                    'RunCloud',
                    PHP_EOL,
                    $metadata['arsol_server_deployed_id']
                );

                // Add order note for successful deployment
                $subscription->add_order_note($success_message);

                // Log the success message
                error_log($success_message);

            } else {

                // Update server metadata on failed provisioning and trip CB
                update_post_meta($this->server_post_id, '_arsol_state_30_deployment', -1);

                // Construct error message
                $error_message = sprintf(
                    "Error deploying server on the server manager platform!\nStatus: %s\nResponse: %s",
                    $runcloud_response['status'],
                    $runcloud_response['body']
                );

                // Trigger the circuit breaker
                ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                return false; // Add fallback return false

            }
        
        } else {

            error_log('STATE CHECK (30): Server deployment status is okay.');
        
        }
    
        // Check server_deployed_status to avoid redundant deployment
        $server_deployed_status = get_post_meta($server_post_id, '_arsol_state_30_deployment', true);

        if ($server_deployed_status == 2) {
           
            // Schedule finish_server_manager_connection using Action Scheduler
            as_schedule_single_action(time(), 
                'arsol_finish_server_manager_connection_hook', 
                [[
                    'server_post_id' => $server_post_id,
                    'task_id' => $args['task_id']
                ]],  
                'arsol_class_server_orchestrator');
    
            // Add order note for scheduling the next step
            $subscription->add_order_note(
                'Scheduled the completion of the server manager connection.' . PHP_EOL . '(Task ID: ' . $args['task_id'] . ')'
            );
            
            // Log the scheduling
            error_log('[SIYA Server Manager - ServerOrchestrator] Scheduled the completion of the server manager connection.');
        } 
    
    }

    // Install Runcloud agent script on provisioned server to connect server to Runcloud
    public function finish_server_manager_connection($args) {
        
        // Retrieve server and subscription data
        $server_post_id = $args['server_post_id'];
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
    
        if (!$subscription) {
            error_log('Failed to retrieve subscription for ID: ' . $subscription_id);
            return; // Exit on failure
        }
    
        // Log subscription retrieval
        error_log('Subscription retrieved successfully: ' . $subscription->get_id());
    
        $server_deployed_id = get_post_meta($server_post_id, 'arsol_server_deployed_id', true);
        $server_ip = get_post_meta($server_post_id, 'arsol_server_provisioned_ipv4', true);
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
    
        if (!$server_deployed_id || !$server_ip) {
            error_log('Missing server ID or IP address for connection.');
            $subscription->add_order_note('Missing server ID or IP address for connection.');

            return; // Exit on failure

        }
    
        // Open ports if they haven't been successfully opened before
        $firewall_status = get_post_meta($server_post_id, '_arsol_state_40_firewall_rules', true);
        
        if ($firewall_status != 2) {

            try {
                
                $open_ports_result = $this->assign_firewall_rules_to_server($server_provider_slug, $server_provisioned_id);
                
                // Update server metadata on successful provisioning
                if ($open_ports_result) {
                    update_post_meta($server_post_id, '_arsol_state_40_firewall_rules', 2);
                } 
                
            } catch (\Exception $e) {
   
                // Update server metadata on failed provisioning and trip CB
                update_post_meta($server_post_id, '_arsol_state_40_firewall_rules', -1);

                // Handle the exception and exit
                $this->handle_exception($e);

                // Trigger the circuit breaker
                ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                return false; // Add fallback return false

            }
    
        } else {

            error_log('STATE CHECK (40): Firewall rules are okay.');
        
        }
    
        // Execute RunCloud script if it hasn't been successfully executed before
        $script_execution_status = get_post_meta($server_post_id, '_arsol_state_50_script_execution', true);

        if ($script_execution_status != 2) {
            
            $this->runcloud = new Runcloud();
            
            try {
                
                $connection_result = $this->runcloud->execute_installation_script_on_server($server_post_id);

                if ($connection_result) {
                  
                    // Success: update metadata
                    update_post_meta($server_post_id, '_arsol_state_50_script_execution', 2);

                    // Success message
                    $message = 'Successfully executed agent installation script on server.';

                    // Update server note
                    $subscription->add_order_note(
                        $message
                    );

                    // Log the success message
                    error_log($message);

                }
            
            } catch (\Exception $e) {

                $error_definition = 'Error executing installation script';

                // Update server metadata on failed provisioning and trip CB
                update_post_meta($server_post_id, '_arsol_state_50_script_execution', -1);

                // Handle the exception and exit
                $this->handle_exception($e);

                // Trigger the circuit breaker
                ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                return false; // Add fallback return false
              
            }  

        } else {

            error_log('STATE CHECK (50): Script execution okay.');
        
        }

        // Schedule server manager connection verification

        $task_id = uniqid();

        as_schedule_single_action(time() + 120, 
            'arsol_verify_server_manager_connection_hook', 
            [[
                'subscription_id' => $subscription->get_id(),
                'server_post_id' => $server_post_id,
                'server_id' => $server_deployed_id,
                'ssh_host' => $server_ip,
                'ssh_username' => get_post_meta($server_post_id, 'arsol_ssh_username', true),
                'ssh_private_key' => get_post_meta($server_post_id, 'arsol_ssh_private_key', true),
                'ssh_port' => 22,
                'task_id' => $task_id
            ]],  
            'arsol_class_server_orchestrator'
        );
        
        // Message
        $message = 'Scheduled agent installation and server connection verification.' . PHP_EOL . '(Task ID: ' . $task_id . ')';
        
        // Update server note
        $subscription->add_order_note(
            $message
        );

        // Log the message
        error_log($message);
    }    
    
    // Verify server manager connection to provisioned server

    public function verify_server_manager_connection($args) {
        $server_post_id = $args['server_post_id'];
        $subscription_id = $args['subscription_id'];
        $subscription = wcs_get_subscription($subscription_id);
        $server_manager_instance = new Runcloud();

        error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Verifying server manager connection for server post ID: %d', $server_post_id));

        // Prevent PHP from timing out
        set_time_limit(0);

        try {
            // Check script installation status if not already successful (status 2)
            $scriptInstallationStatus = get_post_meta($server_post_id, '_arsol_state_60_script_installation', true);

            if ($scriptInstallationStatus != 2) {
                $installationTimeout = apply_filters('arsol_server_manager_script_installation_timeout', 5 * 60);
                $startTime = time();

                while ((time() - $startTime) < $installationTimeout) {
                    
                    try {

                        $status = $server_manager_instance->get_installation_status($server_post_id);

                        if (isset($status['status']) && $status['status'] === 'running') {
                            update_post_meta($server_post_id, '_arsol_state_60_script_installation', 2);
                            update_post_meta($server_post_id, 'arsol_server_manager_installation_status', $status['status']);

                            // Success message
                            $message = 'Script installation completed successfully.';
                            $subscription->add_order_note($message);
                            error_log($message);

                            break; // Exit on success

                        } elseif ( $status['status'] === 'failed') {

                            // Update server metadata on failed installation and trip CB
                            update_post_meta($server_post_id, '_arsol_state_60_script_installation', -1);

                            $this->throw_exception('[SIYA Server Manager - ServerOrchestrator] Script installation failed.');
                            
                            // Trigger the circuit breaker
                            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                            return false; // Exit on failure


                        } elseif ($status['status'] === 'not-installed') {

                            // Reset the previous flag so that a script reinstall can be initiated
                            update_post_meta($server_post_id, '_arsol_state_50_script_execution', -1);

                            $this->throw_exception('[SIYA Server Manager - ServerOrchestrator] Script could not be found on server.');
                            
                            // Trigger the circuit breaker
                            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);
                            
                            return false; // Exit on failure
                        
                        }

                    } catch (\Exception $e) {

                        // Centralize exception handling for retries
                        $this->handle_exception($e);
                    
                    }

                    error_log('[SIYA Server Manager - ServerOrchestrator] Retrying script installation after 30 seconds.');

                    sleep(30); // Retry after 30 seconds
                    
                }

                // Timeout Handling for script installation
                if ((time() - $startTime) >= $installationTimeout) {
                   
                    $timeout_message = '[SIYA Server Manager - ServerOrchestrator] Script installation timeout exceeded for server post ID: ' . $server_post_id;
                    error_log($timeout_message);
                    $subscription->add_order_note($timeout_message);

                    // Update server metadata on failed installation and trip CB
                    update_post_meta($server_post_id, '_arsol_state_60_script_installation', -1);

                    // Handle the exception and exit on timeout
                    $this->handle_exception(new \Exception('Timeout while installing script'));

                    // Trigger the circuit breaker on timeout
                    ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                    return false; // Return false after timeout handling
                }

            } else {

                error_log('STATE CHECK (60): Script installation is okay.');
            
            }

            // Check connection status if not already successful (status 2)
            $connectionStatus = get_post_meta($server_post_id, '_arsol_state_70_manager_connection', true);

            if ($connectionStatus != 2) {

                $connectTimeout = apply_filters('siya_server_connection_timeout', 60);
                $connectStart = time();

                while ((time() - $connectStart) < $connectTimeout) {

                    try {

                        $connStatus = $server_manager_instance->get_connection_status($server_post_id);

                        if (!empty($connStatus['connected']) && !empty($connStatus['online'])) {
                            update_post_meta($server_post_id, '_arsol_state_70_manager_connection', 2);
                            update_post_meta($server_post_id, 'arsol_server_manager_connected', $connStatus['connected']);
                            update_post_meta($server_post_id, 'arsol_server_manager_online', $connStatus['online']);
                            update_post_meta($server_post_id, 'arsol_server_manager_agent_version', $connStatus['agentVersion'] ?? 'Unknown');

                            // Success message
                            $success_message = sprintf(
                                'Server manager connected to server successfully!%sConnected: %s%sOnline: %s%sAgent Version: %s',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_connected', true) ?: 'Not provided',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_online', true) ?: 'Not provided',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_agent_version', true) ?: 'Unknown'
                            );

                            // Update server note
                            $subscription->add_order_note($success_message);
                            error_log($success_message);

                            break; // Exit the loop on success
                        }

                        if (empty($connStatus['connected']) || empty($connStatus['online'])) {
                            $this->throw_exception('[SIYA Server Manager - ServerOrchestrator] Server manager is not connected or online.');
                            error_log('[SIYA Server Manager - ServerOrchestrator] Server manager is not connected or online.');
                        }

                    } catch (\Exception $e) {

                        // Centralize exception handling for retries
                        $this->handle_exception($e);

                    }

                    error_log('[SIYA Server Manager - ServerOrchestrator] Retrying connection status after 15 seconds.');

                    sleep(15); // Retry after 15 seconds

                }

                // Timeout Handling for connection
                if ((time() - $connectStart) >= $connectTimeout) {
                    $timeout_message = '[SIYA Server Manager - ServerOrchestrator] Connection status timeout exceeded for server post ID: ' . $server_post_id;
                    error_log($timeout_message);
                    $subscription->add_order_note($timeout_message);

                    // Update server metadata on failed connection and trip CB
                    update_post_meta($server_post_id, '_arsol_state_70_manager_connection', -1);

                    // Handle the exception and exit on timeout
                    $this->handle_exception(new \Exception('Timeout while fetching connection status'));

                    // Trigger the circuit breaker on timeout
                    ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

                    return false; // Return false after timeout handling
                }

            } else {

                error_log('STATE CHECK (70): Server manager connection status is okay.');
            
            }

            // Success message after verifying both installation and connection
            $success_message = 'Server manager connected to server successfully! Activating subscription to active... Good day and good luck!';
            $subscription->add_order_note($success_message);
            error_log($success_message);

            // Activate the subscription
            $subscription->update_status('active');

        } catch (\Exception $e) {

            // Handle the exception and exit
            $this->handle_exception($e);
            return false; // Add fallback return false
        }
    }

    
    public function start_server_shutdown($subscription) {
        // Early validation of required parameters
        $subscription_id = $subscription->get_id();
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        // If no linked server post ID is found, log the error and exit
        if (!$server_post_id) {
            error_log('#032 [SIYA Server Manager - ServerOrchestrator] No server post found for shutdown. Subscription ID: ' . $subscription_id);
            return;
        }

        // Check circuit breaker status for shutdown
        $this->server_circuit_breaker_position = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
        if ($this->server_circuit_breaker_position == -1 || $this->server_circuit_breaker_position == 1) {
            error_log('#033 [SIYA Server Manager - ServerOrchestrator] Server circuit breaker for subscription ' . $subscription_id . ' is tripped or in progress. Skipping shutdown.');
            return;
        }

        // Fetch the necessary metadata
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        
        // If provisioning ID is missing, log the error and exit
        if (empty($server_provisioned_id)) {
            error_log('[SIYA Server Manager] Server provisioning ID not found, skipping shutdown. Server Post ID: ' . $server_post_id);
            return;
        }

        // Initialize the server provider
        $this->initialize_server_provider($server_provider_slug);
        $task_id = uniqid();
        
        // Schedule the shutdown action
        as_schedule_single_action(
            time(),
            'arsol_server_shutdown',
            [[
                'subscription_id' => $subscription_id,
                'server_post_id' => $server_post_id,
                'server_provider_slug' => $server_provider_slug,
                'server_provisioned_id' => $server_provisioned_id,
                'task_id' => $task_id
            ]],
            'arsol_class_server_orchestrator'
        );

        $subscription->add_order_note(
            'Server shutdown initiated.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
        );

        $subscription->add_order_note('Server shutdown initiated.');
    }

    public function finish_server_shutdown($args) {
        // Early validation of required parameters
        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $server_provider_slug = $args['server_provider_slug'] ?? null;
        $server_provisioned_id = $args['server_provisioned_id'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;
        $task_id = $args['task_id'] ?? uniqid();

        // If any required parameter is missing, log the error and exit early
        if (!$subscription_id || !$server_post_id || !$server_provider_slug || !$server_provisioned_id) {
            error_log('#035 [SIYA Server Manager - ServerOrchestrator] Missing parameters for shutdown. Args: ' . json_encode($args));
            return;
        }

        // Retrieve the subscription instance
        $subscription = wcs_get_subscription($subscription_id);

        // Proceed with the shutdown process
        $this->initialize_server_provider($server_provider_slug);
        $this->server_provider->shutdown_server($server_provisioned_id);

        // Update server suspension status
        $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);
        error_log(sprintf('#036 [SIYA Server Manager - ServerOrchestrator] Updated remote status metadata for server post ID %d: %s', $server_post_id, $remote_status['provisioned_remote_status']));

        // Verify server shutdown
        if ($remote_status['provisioned_remote_status'] === 'off') {
            error_log('#037 [SIYA Server Manager - ServerOrchestrator] Server ' . $server_post_id . ' successfully shut down.');
            update_post_meta($server_post_id, 'arsol_server_suspension', 'yes');
            $subscription->add_order_note('Server shutdown verified: the server is now off.');
        } else {
            error_log('#038 [SIYA Server Manager - ServerOrchestrator] Server shutdown verification failed. Current status: ' . $remote_status['provisioned_remote_status']);

            // Retry logic
            if ($retry_count < 5) {
                error_log('#039 [SIYA Server Manager - ServerOrchestrator] Retrying shutdown in 1 minute. Attempt: ' . ($retry_count + 1));
                $subscription->add_order_note(sprintf(
                    'Attempt %d: Retrying server shutdown in 1 minute. Current status: %s.' . PHP_EOL . '(Task ID: %s)',
                    $retry_count + 1,
                    $remote_status['provisioned_remote_status'],
                    $task_id
                ));
                as_schedule_single_action(
                    time() + 60, // Retry in 1 minute
                    'arsol_server_shutdown',
                    [[
                        'subscription_id' => $subscription_id,
                        'server_post_id' => $server_post_id,
                        'server_provider_slug' => $server_provider_slug,
                        'server_provisioned_id' => $server_provisioned_id,
                        'retry_count' => $retry_count + 1,
                        'task_id' => $task_id
                    ]],
                    'arsol_class_server_orchestrator'
                );
                $subscription->add_order_note(
                    'Retrying server shutdown.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                );
            } else {
                error_log('#040 [SIYA Server Manager - ServerOrchestrator] Maximum retry attempts reached. Server shutdown failed.');
                $subscription->add_order_note('Maximum retry attempts reached. Server shutdown failed.');
            }
        }

        $subscription->add_order_note('Server shutdown process completed or in progress...');
    }

    public function start_server_powerup($subscription) {
        // Early validation of required parameters
        $subscription_id = $subscription->get_id();
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        // If no linked server post ID is found, log the error and exit
        if (!$server_post_id) {
            error_log(sprintf('#041 No linked server post ID found for power-up. Subscription ID: %s', $subscription_id));
            return;
        }

        // Check circuit breaker status for power-up (added rule to allow power up when circuit breaker is testing circuit but not when it is off)
        $this->server_circuit_breaker_position = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
        if ($this->server_circuit_breaker_position == -1) {
            error_log('#042 [SIYA Server Manager - ServerOrchestrator] Server circuit breaker for subscription ' . $subscription_id . ' is tripped or in progress. Skipping power-up.');
            return;
        }

        // Fetch server metadata required for the power-up process
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        
        // If any critical metadata is missing, log the error and exit
        if (!$server_provider_slug || !$server_provisioned_id) {
            error_log(sprintf('[SIYA Server Manager] Missing server provisioning details. Server Post ID: %s', $server_post_id));
            return;
        }

        // Initialize the server provider instance
        $this->initialize_server_provider($server_provider_slug);
        $task_id = uniqid();

        // Schedule the power-up action
        as_schedule_single_action(time(), 'arsol_server_powerup', [[
            'subscription_id' => $subscription_id,
            'server_post_id' => $server_post_id,
            'server_provisioned_id' => $server_provisioned_id,
            'server_provider_slug' => $server_provider_slug,
            'task_id' => $task_id
        ]], 'arsol_class_server_orchestrator');

        $subscription->add_order_note(
            'Server power-up scheduled.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
        );
    }

    public function finish_server_powerup($args) {

        // Early validation of required parameter
        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $server_provisioned_id = $args['server_provisioned_id'] ?? null;
        $server_provider_slug = $args['server_provider_slug'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;
        $task_id = $args['task_id'] ?? uniqid();

        // If any required parameter is missing, log the error and exit early
        if (!$subscription_id || !$server_post_id || !$server_provisioned_id || !$server_provider_slug) {
            error_log(sprintf('#043 Missing parameters for power-up. Args: %s', json_encode($args)));
            return;
        }

        // Retrieve the subscription instance
        $subscription = wcs_get_subscription($subscription_id);

        // Initialize the server provider instance
        $this->initialize_server_provider($server_provider_slug);

        // Attempt to power on the server
        $this->server_provider->poweron_server($server_provisioned_id);

        // Update the server's remote status
        $remote_status = $this->update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id);
        error_log(sprintf('#044 Updated remote status metadata for Server Post ID: %s', $server_post_id));

        // Check if the server is now active or in the process of starting
        if (in_array($remote_status['provisioned_remote_status'], ['active', 'starting'], true)) {
            error_log(sprintf('#045 Server successfully powered up. Server Post ID: %s', $server_post_id));
            update_post_meta($server_post_id, 'arsol_server_suspension', 'no');
            $subscription->add_order_note('Server power-up verified: the server is now active or starting.');
        } else {
            // Retry logic with exponential backoff
            if ($retry_count < 5) {
                $delay = 60 * pow(2, $retry_count); // Exponential backoff
                error_log(sprintf('#046 Retrying power-up for Server Post ID: %s in %d seconds. Retry Count: %d', $server_post_id, $delay, $retry_count + 1));
                $subscription->add_order_note(sprintf(
                    'Attempt %d: Retrying server power-up in %d seconds. Current status: %s.' . PHP_EOL . '(Task ID: %s)',
                    $retry_count + 1,
                    $delay,
                    $remote_status['provisioned_remote_status'],
                    $task_id
                ));

                // Schedule the next retry
                as_schedule_single_action(time() + $delay, 'arsol_server_powerup', [[
                    'subscription_id' => $subscription_id,
                    'server_post_id' => $server_post_id,
                    'server_provisioned_id' => $server_provisioned_id,
                    'server_provider_slug' => $server_provider_slug,
                    'retry_count' => $retry_count + 1,
                    'task_id' => $task_id
                ]], 'arsol_class_server_orchestrator');
                $subscription->add_order_note(
                    'Retrying server power-up.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                );
            } else {
                error_log(sprintf('#047 Maximum retry attempts reached. Server power-up failed. Server Post ID: %s', $server_post_id));
                $subscription->add_order_note('Maximum retry attempts reached. Server power-up failed.');
            }
        }
    }

   // Start server deletion process
    public function start_server_deletion($subscription) {
        
        error_log('#050 [SIYA Server Manager - ServerOrchestrator] Starting deletion process');
        
        // Validate if the subscription exists
        if (!$subscription) {
            error_log('#051 [SIYA Server Manager - ServerOrchestrator] Subscription not found');
            return;
        }

        // Extract the subscription ID and linked server post ID
        $subscription_id = $subscription->get_id();
        $linked_server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);

        // Exit if no linked server post ID found
        if (!$linked_server_post_id) {
            error_log('#052 [SIYA Server Manager - ServerOrchestrator] No linked server post found');
            return;
        }

        // Log the found server post ID
        error_log('#052 [SIYA Server Manager - ServerOrchestrator] Linked server post ID found: ' . $linked_server_post_id);

        // Update the server suspension status to 'pending-deletion'
        update_post_meta($linked_server_post_id, 'arsol_server_suspension', 'pending-deletion');

        // Schedule server deletion completion
        $task_id = uniqid();
       
        as_schedule_single_action(
            time(),
            'arsol_finish_server_deletion_hook',
            [[
                'subscription_id' => $subscription_id,
                'server_post_id' => $linked_server_post_id,
                'retry_count' => 0,
                'task_id' => $task_id
            ]],
            'arsol_class_server_orchestrator'
        );
        $subscription->add_order_note(
            'Scheduled server deletion.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
        );

        error_log('#056 [SIYA Server Manager - ServerOrchestrator] Milestone 2: Scheduled server deletion for ' . $subscription_id . ' Server post ID ' . $linked_server_post_id);
    }

    // Finish server deletion process
    public function finish_server_deletion($args) {
        error_log('#057 [SIYA Server Manager - ServerOrchestrator] Starting server deletion process');

        // Extract the necessary parameters from $args
        $subscription_id = $args['subscription_id'] ?? null;
        $server_post_id = $args['server_post_id'] ?? null;
        $retry_count = $args['retry_count'] ?? 0;

        // Validate required parameters
        if (!$subscription_id || !$server_post_id) {
            error_log('#057 [SIYA Server Manager - ServerOrchestrator] Missing parameters for deletion: ' . json_encode($args));
            return;
        }

        // Log the passed arguments for debugging
        error_log(sprintf('#057b [SIYA Server Manager - ServerOrchestrator] Passed arguments: %s', json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
        
        // Retrieve server provider info and IDs
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        $server_deployed_server_id = get_post_meta($server_post_id, 'arsol_server_deployed_id', true);

        // Log metadata information
        error_log(sprintf(
            '#058 [SIYA Server Manager - ServerOrchestrator] Server Provider Slug: %s, Server Provisioned ID: %s, Server Deployed Server ID: %s',
            $server_provider_slug,
            $server_provisioned_id,
            $server_deployed_server_id
        ));

        // Delete provisioned server if ID is available
        if ($server_provisioned_id) {
            error_log('#063 [SIYA Server Manager - ServerOrchestrator] Deleting provisioned server with ID ' . $server_provisioned_id);
            
            // Initialize server provider and attempt server destruction
            $this->initialize_server_provider($server_provider_slug);

            try {
                $deleted = $this->server_provider->destroy_server($server_provisioned_id);
            } catch (\Exception $e) {
                error_log(sprintf('#065a [SIYA Server Manager - ServerOrchestrator] Error during server destruction: %s', $e->getMessage()));
                $deleted = false;
            }

            if ($deleted) {
                update_post_meta($server_post_id, '_arsol_state_10_provisioning', -2);
                error_log('#064 [SIYA Server Manager - ServerOrchestrator] Provisioned server deleted successfully.');
            } else {
                error_log('#065 [SIYA Server Manager - ServerOrchestrator] Provisioned server deletion failed.');
                if ($retry_count < 5) {
                    // Retry server deletion after 60 seconds
                    $task_id = uniqid();

                    as_schedule_single_action(
                        time() + 60,
                        'arsol_finish_server_deletion_hook',
                        [[
                            'subscription_id' => $subscription_id,
                            'server_post_id' => $server_post_id,
                            'retry_count' => $retry_count + 1,
                            'task_id' => $task_id
                        ]],
                        'arsol_class_server_orchestrator'
                    );

                    $subscription->add_order_note(
                        'Retrying server deletion.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                    );

                    return;

                } else {
                    error_log('#066 [SIYA Server Manager - ServerOrchestrator] Maximum retry attempts reached. Provisioned server deletion failed.');
                    return;
                }
            }
        }

        // Delete RunCloud server if deployed server ID is available
        if ($server_deployed_server_id) {
            error_log('#059 [SIYA Server Manager - ServerOrchestrator] Deleting RunCloud server with ID ' . $server_deployed_server_id);
            $this->runcloud = new Runcloud();
            $deleted = $this->runcloud->delete_server($server_deployed_server_id);

            if ($deleted) {
                update_post_meta($server_post_id, '_arsol_state_30_deployment', -2);
                error_log('#060 [SIYA Server Manager - ServerOrchestrator] RunCloud server deleted successfully.');
            } else {
                error_log('#061 [SIYA Server Manager - ServerOrchestrator] RunCloud server deletion failed.');
                if ($retry_count < 5) {
                    // Retry server deletion after 60 seconds
                    $task_id = uniqid();

                    as_schedule_single_action(
                        time() + 60,
                        'arsol_finish_server_deletion',
                        [[
                            'subscription_id' => $subscription_id,
                            'server_post_id' => $server_post_id,
                            'retry_count' => $retry_count + 1,
                            'task_id' => $task_id
                        ]],
                        'arsol_class_server_orchestrator'
                    );
                    $subscription->add_order_note(
                        'Retrying server deletion.' . PHP_EOL . '(Task ID: ' . $task_id . ')'
                    );

                    return;

                } else {
                    error_log('#062 [SIYA Server Manager - ServerOrchestrator] Maximum retry attempts reached. RunCloud server deletion failed.');
                    return;
                }
            }
        }

        // Update server suspension status to 'destroyed'
        update_post_meta($server_post_id, 'arsol_server_suspension', 'destroyed');
        error_log('#067 [SIYA Server Manager - ServerOrchestrator] Server suspension status updated to destroyed.');

        // Delete the server post
        wp_delete_post($server_post_id, true);
        error_log('#067a [SIYA Server Manager - ServerOrchestrator] Server post deleted.');

        // Update the server linked post ID on the subscription to 'Server destroyed'
        $subscription = wcs_get_subscription($subscription_id);
        $subscription->update_meta_data('arsol_linked_server_post_id', 'Server destroyed');
        $subscription->save(); // Save the subscription to persist changes
        error_log('#068 [SIYA Server Manager - ServerOrchestrator] Subscription linked server post updated to "Server destroyed".');
    }


    // Helper Methods

    private function create_and_update_server_post($server_product_id, $server_post_instance, $subscription) {
        $post_id = $server_post_instance->create_server_post($this->subscription_id);
        
        // Update server post metadata
        if ($post_id) {
            $this->server_post_id = $post_id;

            // Get the URL for the subscription/order
            $server_post_url = get_edit_post_link($this->server_post_id);
            $message = sprintf(
                'Server post for server ARSOL%d with Post ID %d created successfully! <a href="%s" target="_blank">view</a>',
                $this->subscription_id,
                $this->server_post_id,
                esc_url($server_post_url) // Ensure the URL is properly escaped
            );

            // Add server post URL to the subscription note
            $subscription->add_order_note(
                $message
            );

            // Log the message
            error_log('[SIYA Server Manager] ' . $message);

            // Generate SSH key pair
            $ssh_keys = $this->generate_key_pair();
            error_log('[SIYA Server Manager] Generated SSH key pair for server post ID: ' . $this->server_post_id);

            // Get server product metadata
            $server_product = wc_get_product($this->server_product_id);

            $manager_required = $server_product->get_meta('arsol_server_manager_required', true);
            $server_groups = $server_product->get_meta('arsol_assigned_server_groups', true) ;
            $server_tags = $server_product->get_meta('arsol_assigned_server_tags', true) ;

            error_log('[SIYA Server Manager] Server product metadata: ' . print_r($server_product->get_meta(), true));
            error_log('[SIYA Server Manager] Server product manager required: ' . $manager_required);
            error_log('[SIYA Server Manager] Server product groups: ' . print_r($server_groups, true));
            error_log('[SIYA Server Manager] Serialized server groups: ' . serialize($server_groups));
            error_log('[SIYA Server Manager] Server product tags: ' . print_r($server_tags, true));


            // Update server post metadata with correct meta keys
            $metadata = [
                'arsol_server_subscription_id' => $this->subscription_id,
                'arsol_server_post_name' => 'ARSOL' . $this->subscription_id,
                'arsol_server_post_creation_date' => current_time('mysql'),
                'arsol_server_post_status' => 2,
                'arsol_server_product_id' => $this->server_product_id,
                'arsol_wordpress_server' => $server_product->get_meta('_arsol_wordpress_server', true),
                'arsol_wordpress_ecommerce' => $server_product->get_meta('_arsol_wordpress_ecommerce', true),
                '_arsol_server_manager_required' => $server_product->get_meta('_arsol_server_manager_required', true),
                'arsol_server_provider_slug' => $server_product->get_meta('_arsol_server_provider_slug', true),
                'arsol_server_group_slug' => $server_product->get_meta('_arsol_server_plan_group_slug', true),
                'arsol_server_plan_slug' => $server_product->get_meta('_arsol_server_plan_slug', true),
                'arsol_server_region_slug' => $server_product->get_meta('_arsol_server_region', true),
                'arsol_server_image_slug' => $server_product->get_meta('_arsol_server_image', true),
                'arsol_server_max_applications' => $server_product->get_meta('_arsol_max_applications', true),
                'arsol_server_max_staging_sites' => $server_product->get_meta('_arsol_max_staging_sites', true),
                'arsol_server_suspension' => 'no', // Add suspension status
                'arsol_ssh_private_key' => $ssh_keys['private_key'],
                'arsol_ssh_public_key' => $ssh_keys['public_key'],
                'arsol_ssh_username' => 'ARSOL' . $this->subscription_id, // Add SSH username
                'arsol_assigned_server_groups' => $server_product->get_meta('arsol_assigned_server_groups', true) ?: [],
                'arsol_assigned_server_tags' => $server_product->get_meta('arsol_assigned_server_tags', true) ?: [],
            ];
            $server_post_instance->update_meta_data($this->server_post_id, $metadata);

            $subscription->update_meta_data('arsol_linked_server_post_id', $this->server_post_id);
            $subscription->save();


            // Check if we need to connect to the server manager
            /*
            $this->connect_server_manager = $server_product->get_meta('_arsol_server_manager_required', true);
            if ($this->connect_server_manager === 'yes') {
                $this->runcloud = new Runcloud();
                $installation_script = $this->runcloud->get_installation_script($this->server_provisioned_id);
                update_post_meta($this->server_post_id, 'arsol_server_manager_installation_script', $installation_script);
            }
                */

            error_log('[SIYA Server Manager] Updated server post meta data ' . $this->server_post_id);

            // After creating/updating server post
            $groups = $this->get_product_assigned_groups($server_product_id);
            $tags = $this->get_product_assigned_tags($server_product_id);

            $this->sync_server_groups($post_id, $groups);
            $this->sync_server_tags($post_id, $tags);

            return true;
        } elseif ($post_id instanceof \WP_Error) {
            $subscription->add_order_note(
                'Failed to create server post. Error: ' . $post_id->get_error_message()
            );
        
            $this->throw_exception('Failed to create server post');
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
            $provisioned_status = get_post_meta($server_post_id, '_arsol_state_10_provisioning', true);

            if ($provisioned_status == 2) {
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
                $this->throw_exception($error_message);
            }

            // Update server post metadata using the standardized data
            $server_post_instance = new ServerPost;
            $metadata = [
                '_arsol_state_10_provisioning' => 2,
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
                'arsol_server_provisioned_remote_raw_status' => $server_data['provisioned_remote_raw_status'],
                'arsol_server_provisioned_disk_size' => $server_data['provisioned_disk_size'] // Add this line
            ];
            $server_post_instance->update_meta_data($server_post_id, $metadata);

            // Update subscription notes and logs with the provisioned server data
            $success_message = sprintf(
                "Server provisioned successfully! %s" .
                "Server Provider: %s%s" .
                "Server ID: %s%s" .
                "Server Name: %s%s" .
                "OS: %s%s" .
                "OS Version: %s%s" .
                "IPv4: %s%s" .
                "IPv6: %s%s" .
                "CPU Cores: %s%s" .
                "Memory: %s%s" .
                "Disk Size: %s%s" .
                "Region: %s%s" .
                "Time: %s",
                PHP_EOL,
                $server_provider_slug ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_id'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_name'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_os'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_os_version'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_ipv4'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_ipv6'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_vcpu_count'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_memory'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_disk_size'] ?: 'Not provided', PHP_EOL, // Update this line
                $server_data['provisioned_region_slug'] ?: 'Not provided', PHP_EOL,
                $server_data['provisioned_date'] ?: 'Not provided'
            );

            $subscription->add_order_note($success_message);

            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] %s', $success_message));

            return $server_data;

        } catch (\Exception $e) {
            error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Exception caught: %s', $e->getMessage()));
            $this->throw_exception($e->getMessage(), 'Error provisioning server');
        }
    }

    private function get_provisioned_server_ip($server_provider_slug, $server_provisioned_id, $ip_version = 'ipv4') {
    
        $this->initialize_server_provider($server_provider_slug);
        
        try {
            // Fetching server IPs
            $server_ips = $this->server_provider->get_server_ip($server_provisioned_id);
            
        } catch (\Exception $e) {

            // Logging error if the server IP fetching fails
            $this->handle_exception($e);
            return false; // Add fallback return false

        }
    
        // Extract IPv4 and IPv6 addresses
        $ipv4 = isset($server_ips['ipv4']) ? $server_ips['ipv4'] : null;
        $ipv6 = isset($server_ips['ipv6']) ? $server_ips['ipv6'] : null;
    
        // Handle cases where IPv4 and/or IPv6 are missing
        if (empty($ipv4) && empty($ipv6)) {
            
            $this->throw_exception('No IP address found for the provisioned server');
        
        }
    
        // Return the appropriate IP address based on the requested version
        if ($ip_version === 'ipv4' && !empty($ipv4)) {
            return $ipv4;
        } elseif ($ip_version === 'ipv6' && !empty($ipv6)) {
            return $ipv6;
        }
        
        return null;  // Return null if neither IP version is available
    
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
                $this->throw_exception('Unknown server provider: ' . $this->server_provider_slug);
        }
    }

    private function update_server_status($server_post_id, $server_provider_slug, $server_provisioned_id) {

        // Get remote status
        $this->initialize_server_provider($server_provider_slug);
        $remote_status = $this->server_provider->get_server_status($server_post_id);

        error_log ('#049 [SIYA Server Manager - ServerOrchestrator] Updated server status: ' . json_encode($remote_status));
        
        if (!$remote_status || !isset($remote_status['provisioned_remote_status']) || !isset($remote_status['provisioned_remote_raw_status'])) {
            error_log ('Failed to retrieve valid server status from provider.');
            $this->throw_exception('Failed to retrieve valid server status from provider.');
        }

        $provisioned_remote_status = $remote_status['provisioned_remote_status'];
        $provisioned_remote_raw_status = $remote_status['provisioned_remote_raw_status'];

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

    /**
         * Handle exceptions with optional rethrowing and stack trace.
         *
         * @param Throwable $e The exception object.
         * @param bool $rethrow Whether to rethrow the exception or not.
         * @param bool $stack_trace Whether to log the stack trace (default is true).
         */
        private function handle_exception($e, bool $rethrow = false, bool $stack_trace = true) {
            // Get the error code from the exception and include it in the log if available
            $error_code_msg = $e->getCode() ? sprintf("Error Code: %s\n", $e->getCode()) : '';

            // Capture the stack trace of the exception
            $trace = '';
            if ($stack_trace) {
                // Format the stack trace
                $trace_array = $e->getTrace();
                $formatted_trace = [];

                foreach ($trace_array as $trace_entry) {
                    $entry = "File: " . (isset($trace_entry['file']) ? $trace_entry['file'] : 'Unknown file') . "\n";
                    $entry .= "Line: " . (isset($trace_entry['line']) ? $trace_entry['line'] : 'Unknown line') . "\n";
                    $entry .= "Function/Method: " . (isset($trace_entry['function']) ? $trace_entry['function'] : 'Unknown function') . "\n";

                    if (isset($trace_entry['class'])) {
                        $entry .= "Class: " . $trace_entry['class'] . "\n";
                    }

                    // Add a separator between stack trace entries
                    $formatted_trace[] = $entry . "\n";
                }

                $trace = implode("", $formatted_trace);
            }

            // If not rethrowing, log the exception message with appropriate format
            if (!$rethrow) {
                // Log with stack trace if requested
                if ($stack_trace) {
                    error_log(sprintf(
                        "%sFunction/Method: %s::%s\nException: %s\n\nStack trace:\n%s",
                        $error_code_msg,
                        __CLASS__,
                        __FUNCTION__,
                        $e->getMessage(),
                        $trace
                    ));
                } else {
                    // Log without stack trace, with file and line number details
                    error_log(sprintf(
                        "%sFunction/Method: %s::%s\nException: %s\n\nCaught at: %s (Line %d)\n",
                        $error_code_msg,
                        __CLASS__,
                        __FUNCTION__,
                        $e->getMessage(),
                        __FILE__,
                        __LINE__
                    ));
                }
            }

            // If rethrowing, log that the exception was rethrown and rethrow the original exception as-is
            if ($rethrow) {
                // Get the caller's file, line number, and class name (the location where handle_exception was called)
                $backtrace = debug_backtrace();
                $caller = $backtrace[1]; // The caller is at index 1

                // Get the caller class name, if available
                $caller_class = isset($caller['class']) ? $caller['class'] : 'Unknown Class';

                // Log that the exception was rethrown, including the calling class, method, file, and line number
                error_log(sprintf('%s::%s Rethrew an exception at line %d in %s',
                    $caller_class,
                    $caller['function'],
                    $caller['line'],
                    $caller['file']
                ));

                // Rethrow the original exception without modifying it
                throw $e;
            }
        }






    // New helper method to throw exceptions
    private function throw_exception($error_message, $error_definition = null, $line_number = false) {
        
        $class_name = __CLASS__;
        
        // Constructing the error message
        $exception_message = "SIYA Server Manager - [$class_name]: " . ($error_definition ?: 'Undefined error') . ": $error_message";
        
        // Include line number if $line_number is true
        if ($line_number === true) {
            $exception_message .= " on line " . __LINE__;
        }
    
        // Throw the exception with the constructed message
        throw new \Exception($exception_message);
    }

    // New method to open ports at the provider
    private function assign_firewall_rules_to_server($server_provider_slug, $server_provisioned_id) {
        
        $error_definition = 'Failed to assign firewall rules to the server';
        
        $this->initialize_server_provider($server_provider_slug);
        
        try {

            $result = $this->server_provider->assign_firewall_rules_to_server($server_provisioned_id);

        } catch (\Exception $e) {
            
            $error_definition = 'Error assigning firewall rules to the server';

            // Handle the exception
            $this->handle_exception($e);
            return false; // Add fallback return false

        }

        return true; // Return true if the firewall rules are successfully assigned

    }

    private function get_product_assigned_groups($product_id) {
        $assigned_groups = get_post_meta($product_id, '_arsol_assigned_server_groups', true);
        error_log('Raw assigned groups from product ' . $product_id . ': ' . print_r($assigned_groups, true));
        
        // Convert term slugs to term IDs
        $term_ids = [];
        if (!empty($assigned_groups)) {
            foreach ((array)$assigned_groups as $group_slug) {
                $term = get_term_by('slug', $group_slug, 'arsol_server_group');
                if ($term) {
                    $term_ids[] = $term->term_id;
                }
            }
        }
        
        error_log('Converted group term IDs: ' . print_r($term_ids, true));
        return $term_ids;
    }

    private function get_product_assigned_tags($product_id) {
        $assigned_tags = get_post_meta($product_id, '_arsol_assigned_server_tags', true);
        error_log('Raw assigned tags from product ' . $id . ': ' . print_r($assigned_tags, true));
        
        // Convert term slugs to term IDs
        $term_ids = [];
        if (!empty($assigned_tags)) {
            foreach ((array)$assigned_tags as $tag_slug) {
                $term = get_term_by('slug', $tag_slug, 'arsol_server_tag');
                if ($term) {
                    $term_ids[] = $term->term_id;
                }
            }
        }
        
        error_log('Converted tag term IDs: ' . print_r($term_ids, true));
        return $term_ids;
    }

    private function sync_server_groups($server_post_id, $group_ids) {
        if (empty($group_ids)) {
            error_log('No group IDs to sync for server ' . $server_post_id);
            return true;
        }
        error_log('Syncing server groups: ' . print_r($group_ids, true));
        $result = wp_set_object_terms($server_post_id, $group_ids, 'arsol_server_group');
        if (is_wp_error($result)) {
            error_log('Error syncing groups: ' . $result->get_error_message());
            return false;
        }
        error_log('Successfully synced groups for server ' . $server_post_id);
        return true;
    }

    private function sync_server_tags($server_post_id, $tag_ids) {
        if (empty($tag_ids)) {
            error_log('No tag IDs to sync for server ' . $server_post_id);
            return true;
        }
        error_log('Syncing server tags: ' . print_r($tag_ids, true));
        $result = wp_set_object_terms($server_post_id, $tag_ids, 'arsol_server_tag');
        if (is_wp_error($result)) {
            error_log('Error syncing tags: ' . $result->get_error_message());
            return false;
        }
        error_log('Successfully synced tags for server ' . $server_post_id);
        return true;
    }

}




