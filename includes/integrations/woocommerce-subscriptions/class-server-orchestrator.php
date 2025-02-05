<?php //

namespace Siya\Integrations\WoocommerceSubscriptions;

use Siya\CustomPostTypes\ServerPost;
use Siya\Integrations\ServerManagers\Runcloud;
use Siya\Integrations\ServerProviders\DigitalOcean;
use Siya\Integrations\ServerProviders\Hetzner;
use Siya\Integrations\ServerProviders\Vultr;
use Siya\Integrations\WoocommerceSubscriptions\ServerCircuitBreaker;

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
    private $sites_server;
    private $ecommerce_optimized;
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
        add_action('woocommerce_subscription_status_active', array($this, 'start_server_preflight_check'), 20, 1);

        // Register new hooks to trigger shutdown
        add_action('woocommerce_subscription_status_on-hold', array($this, 'start_server_shutdown'), 20, 1);
        add_action('woocommerce_subscription_status_expired', array($this, 'start_server_shutdown'), 20, 1);
        add_action('arsol_server_shutdown', array($this, 'finish_server_shutdown'), 20, 1);

        // Add new action hook for the scheduled processes
        add_action('arsol_provision_remote_server_hook', array($this, 'provision_remote_server'), 20, 1);
        add_action('arsol_wait_for_server_active_state_hook', array($this, 'wait_for_server_active_state'), 20, 1);
        add_action('arsol_provision_remote_server', array($this, 'provision_remote_server'), 20, 1);

        // Add new action hooks for server powerup
      //TODO DELETE add_action('woocommerce_subscription_status_active', array($this, 'start_server_powerup'), 20, 1);
        add_action('arsol_server_powerup', array($this, 'finish_server_powerup'), 20, 1);

        add_action('woocommerce_subscription_status_cancelled', array($this, 'start_server_deletion'), 10, 1);
        add_action('woocommerce_subscription_status_trash', array($this, 'start_server_deletion'), 10, 1);

        // Add new action hook for deploying to RunCloud
        add_action('arsol_start_server_manager_connection_hook', array($this, 'start_server_manager_connection'), 20, 2);

        // Add new action hooks for server connection
        add_action('arsol_verify_server_manager_connection_hook', [$this, 'verify_server_manager_connection']);

        add_action('arsol_finish_server_deletion_hook', [$this, 'finish_server_deletion']);  

        // Add new action hooks for firewall rules and agent installation
        add_action('arsol_apply_firewall_rules_hook', [$this, 'apply_firewall_rules']);
        add_action('arsol_install_server_manager_agent_on_server_hook', [$this, 'install_server_manager_agent_on_server']);
        add_action('arsol_verify_server_manager_agent_installation_on_server_hook', [$this, 'verify_server_manager_agent_installation_on_server'], 10, 2);
        add_action('arsol_verify_server_manager_connection_to_server_hook', [$this, 'verify_server_manager_connection_to_server'], 10, 2);

        add_action('admin_post_repair', [$this, 'trigger_server_repair_from_url']);
        add_action('admin_post_reboot', [$this, 'trigger_server_reboot_from_url']);
    }

    // Prepare the on button
    public function start_server_preflight_check($subscription) {
        error_log('#PFC001 [SIYA Server Manager - ServerOrchestrator] Starting server preflight check');
        
        try {
            // Primary Check: Look for linked server product
            error_log('#PFC002 [SIYA Server Manager - ServerOrchestrator] Extracting server product from subscription');
            $this->server_product_id = $this->extract_server_product_from_subscription($subscription);

            if (!$this->server_product_id) {
                error_log('#PFC003 [SIYA Server Manager - ServerOrchestrator] No server product found in subscription, moving on');
                return;
            }
            
            error_log('#PFC004 [SIYA Server Manager - ServerOrchestrator] Server product found: ' . $this->server_product_id);

            // Test the circuit
            error_log('#PFC005 [SIYA Server Manager - ServerOrchestrator] Testing circuit breaker');

            // Check if circuit breaker state is available and 0 (open)
            $server_post_id = ServerPost::get_server_post_id_from_subscription($subscription);

            error_log('#PFC005a [SIYA Server Manager - ServerOrchestrator] Server post ID: ' . $server_post_id);

            // Run circuit breaker test - this will handle state changes if needed
            $circuit_breaker_instance = new ServerCircuitBreaker();
            $circuit_breaker_instance->test_circuit($subscription);

            // Get circuit breaker status from metadata
            $circuit_breaker_status = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

            if ($circuit_breaker_status !== null && $circuit_breaker_status === 0) { // 0 means closed/open circuit
                error_log('#PFC005c [SIYA Server Manager - ServerOrchestrator] Circuit breaker is open, starting power-up');
                $this->start_server_powerup($subscription); 
            } else {
                $this->start_server_provision($subscription);
            }

            error_log('#PFC006 [SIYA Server Manager - ServerOrchestrator] Preflight check completed');


        } catch (\Exception $e) {

            error_log('#PFC007 [SIYA Server Manager - ServerOrchestrator] Exception caught: ' . $e->getMessage());
            
            // Trip circuit breaker before handling exception
            ServerCircuitBreaker::trip_circuit_breaker($subscription);
            
            // Catch and handle the exception 
            $this->handle_exception($e, true);
        }
    }

    public function trigger_server_repair_from_url() {
        check_admin_referer('repair_nonce', '_wpnonce');

        $subscription_id = isset($_REQUEST['subscription_id']) ? absint($_REQUEST['subscription_id']) : 0;
        if (!$subscription_id) {
            wp_die('Invalid subscription ID.');
        }

        $subscription = wcs_get_subscription($subscription_id);
        if (!$subscription) {
            wp_die('Subscription not found.');
        }

        $this->start_server_preflight_check($subscription);

        wp_redirect(wp_get_referer());
        exit;
    }

    public function trigger_server_reboot_from_url() {
        check_admin_referer('reboot_nonce', '_wpnonce');

        $subscription_id = isset($_REQUEST['subscription_id']) ? absint($_REQUEST['subscription_id']) : 0;
        if (!$subscription_id) {
            wp_die('Invalid subscription ID.');
        }

        $subscription = wcs_get_subscription($subscription_id);
        if (!$subscription) {
            wp_die('Subscription not found.');
        }

        $this->start_server_reboot($subscription);

        wp_redirect(wp_get_referer());
        exit;
    }

    // Prepare server repair
    public function start_server_repair($subscription) {
        try {
            
            error_log('#SR001 [SIYA Server Manager - ServerOrchestrator] Starting server repair process');

             // Get server post_id from subscription 
            $server_post_id = ServerPost::get_server_post_id_from_subscription($subscription);

            // If no linked server post ID is found, log the error and exit
            if(!$server_post_id) {

                // Error log
                error_log('#SR002 [SIYA Server Manager - ServerOrchestrator] No server post found');
                
                $this->start_server_provision($subscription);
            
            }
            
            $server_provision_status = get_post_meta($server_post_id, '_arsol_state_10_provisioning', true);
   
            error_log('#SR003 [SIYA Server Manager - ServerOrchestrator] Server post id: ' . $server_post_id);

            // Check if server post exists
       
       

            // Check if server previsously provisioned successfully 
            if($server_provision_status == 2){

                // Get server remote status
                error_log('#SR004 [SIYA Server Manager - ServerOrchestrator] Getting server remote status');
                error_log('#get_installation_scriptSR005 [SIYA Server Manager - ServerOrchestrator] Server remote status: ' . print_r($server_remote_status, true));

                $server_remote_status = $this->get_and_update_server_remote_status(
                    $server_post_id,
                    get_post_meta($server_post_id, 'arsol_server_provider_slug', true),
                    get_post_meta($server_post_id, 'arsol_server_provisioned_id', true)
                );

                if($server_remote_status != 'active' ){
                    error_log('#SR006 [SIYA Server Manager - ServerOrchestrator] Server not active, initiating power up sequence');

                    // Power up server 
                    $this->start_server_powerup($subscription);

                    // Wait for status
                    try {

                        error_log('#SR007 [SIYA Server Manager - ServerOrchestrator] Waiting for server to become active');
                        $status_check = $this->wait_for_remote_server_status($server_post_id, 'active');
                        
                        if (!$status_check) {
                            error_log('#SR008 [SIYA Server Manager - ServerOrchestrator] Server status check failed');
                            $this->throw_exception('Server status check failed');
                        }
                        
                        error_log('#SR009 [SIYA Server Manager - ServerOrchestrator] Server successfully powered up');
                        
                    } catch (\Exception $e) {
                        error_log('#SR010 [SIYA Server Manager - ServerOrchestrator] Exception during power up: ' . $e->getMessage());
                        // Handle the exception and exit
                        $this->handle_exception($e, true);
                        return false;
                    }
                } 

            }  
           
            if( $server_provision_status != 2 ) {

                error_log('#SR011 [SIYA Server Manager - ServerOrchestrator] starting maintenance');

                // Construct message
                $message = 'Maintenance started on server';

                // Add order note for the scheduled action
                $subscription->add_order_note($message);

                // Log the scheduled action 
                error_log('#SR012 [SIYA Server Manager - ServerOrchestrator] ' . $message);

                // Provision server
                error_log('#SR013 [SIYA Server Manager - ServerOrchestrator] Starting remote server provisioning');
      
                $this->start_server_provision($subscription);

                error_log('#SR014 [SIYA Server Manager - ServerOrchestrator] Remote server provisioning scheduled, repair process started');

            }

        } catch (\Exception $e) {

            error_log('#SR015 [SIYA Server Manager - ServerOrchestrator] Exception in server repair: ' . $e->getMessage());
            $this->handle_exception($e);
            
            // Trip circuit breaker for server repairs
            ServerCircuitBreaker::trip_circuit_breaker($subscription);

            return false;
        }
    }

    // Step 1: Start server provisioning process (Create server post)
    public function start_server_provision($subscription) {
       
        try {

            $this->subscription = $subscription;
            $this->subscription_id = $subscription->get_id(); 
            $this->server_product_id = $this->extract_server_product_from_subscription($subscription);
            $this->server_product = wc_get_product($this->server_product_id); 
            $this->server_provider_slug = $this->server_product->get_meta('arsol_server_provider_slug');
                
            if (!$this->server_product_id) {
                error_log('#001 [SIYA Server Manager - ServerOrchestrator] No server product found in subscription, moving on');
                
                $this->throw_exception('No server product found in subscription');
            
            }

            error_log ('#001 [SIYA Server Manager - ServerOrchestrator] Starting server provisioning process for subscription ' . $this->subscription_id);

          
            // Check if the server post already exists
            $server_post_id = ServerPost::get_server_post_id_from_subscription($subscription);

            error_log('#001a [SIYA Server Manager - ServerOrchestrator] Checking if server post exists: ' . $server_post_id);

            // If the server post does not exist, create it in the database
            if (!$server_post_id) {

                try {

                    error_log('#002 [SIYA Server Manager - ServerOrchestrator] creating new server post');
                           
                    $server_post_id = $this->create_and_update_server_post($subscription);

                    if (is_wp_error($server_post_id)) {
                        throw new Exception($server_post->get_error_message(), $server_post->get_error_code());
                    }

                    // Update server metadata
                    update_post_meta($server_post_id, '_arsol_state_05_server_post', 2);

                    //Update poststatus variable to 2
                    $this->server_post_status = 2;


                } catch (\Exception $e) {
                    
                    // Rethrow
                    $this->handle_exception($e, true);

                }
                    
            } else {

                 // Define the message
                 $message = 'Server post exists. Server post ID: ' . $server_post_id;

            }

            // Check latest server post status 
            $this->server_post_status = get_post_meta($server_post_id, '_arsol_state_05_server_post', true);

            if ($this->server_post_status == 2) {


                error_log('STATE CHECK (05): Server post okay.');

            
                error_log(sprintf(
                    'Server post ID: %s, Server product ID: %s, Subscription ID: %s',
                    $server_post_id,
                    $this->server_product_id,
                    $this->subscription_id
                ));
    
                if ($server_post_id && $this->server_product_id && $this->subscription_id) {
                    
                    $task_id = uniqid();
                    as_schedule_single_action(time(), 
                        'arsol_provision_remote_server_hook', 
                        [
                            'server_post_id' => $server_post_id,
                            'task_id' => $task_id
                        ], 
                        'arsol_class_server_orchestrator'
                    );

                    // Construct message
                    $message = 'Scheduled remote server provisioning for subscription #' . $this->subscription_id . PHP_EOL . '(Task ID: ' . $task_id . ')';

                    // Add order note for the scheduled action
                    $subscription->add_order_note($message);

                    // Log the scheduled action 
                    error_log($message);

                } else {
    
                    throw new \Exception('Missing required parameters for scheduled action.');
    
                }
               
            
            } 

         
        } catch (\Exception $e) {

            // Update State meta
            update_post_meta($server_post_id, '_arsol_state_05_server_post', -1);

            // Handle the exception and continue
            $this->handle_exception($e);

            $subscription->add_order_note(
                'Circuit breaker triggered due to server provisioning failure. Please review the logs for details.'
            );

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($subscription);

            return false; // Add fallback return false

        }

    }
    
    public function provision_remote_server($server_post_id, $task_id = null) {
        try {
            // Extract the arguments from action scheduler
            $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
            $subscription = wcs_get_subscription($subscription_id);
            $server_product_id = get_post_meta($server_post_id, 'arsol_server_product_id', true);
            $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);

            // Load all parameters from the server post metadata
            $server_post_instance = new ServerPost($server_post_id);
            $metadata = $server_post_instance->get_meta_data();

            // Load required parameters from metadata
            $connect_server_manager = $metadata['_arsol_server_manager_required'] ?? null;

            error_log(sprintf(
                'Server post ID: %s, Server product ID: %s, Subscription ID: %s',
                $server_post_id,
                $server_product_id,
                $subscription_id
            ));

            // Check if the server has already been provisioned
            $server_provisioned_status = get_post_meta($server_post_id, '_arsol_state_10_provisioning', true); 
            
            if ($server_provisioned_status != 2) {
                try {
                    // Step 2: Provision server if not already provisioned
                    $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);

                    error_log('Server Provider Slug:' . $server_provider_slug);

                    $this->initialize_server_provider($server_provider_slug);

                    // Attempt to provision the server
                    $server_data = $this->provision_server_at_provider($subscription);

                    // Update server metadata on successful provisioning
                    update_post_meta($server_post_id, '_arsol_state_10_provisioning', 2); // Status 2 indicates success

                    error_log(sprintf('#010 [SIYA Server Manager - ServerOrchestrator] Provisioned server data:%s%s', 
                        PHP_EOL,
                        json_encode($server_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    ));

                } catch (\Exception $e) {
                    $error_definition = 'Error creating and updating server post';
                    // Rethrow
                    $this->handle_exception($e, true);
                }
            }

            // Check updated server metadata
            $server_provisioned_status = get_post_meta($server_post_id, '_arsol_state_10_provisioning', true); 
            if ($server_provisioned_status == 2) {
                try {
                    // Step 3: Schedule asynchronous action with predefined parameters to complete server provisioning
                    $metadata = $server_post_instance->get_meta_data();

                    // Load parameters into local variables
                    $server_provisioned_status = $metadata['_arsol_state_10_provisioning'] ?? null;
                    $server_provisioned_id = $metadata['arsol_server_provisioned_id'] ?? null;
                    $server_provisioned_name = $metadata['arsol_server_provisioned_name'] ?? null;
                    $server_provisioned_os = $metadata['arsol_server_provisioned_os'] ?? null;
                    $server_provisioned_ipv4 = $metadata['arsol_server_provisioned_ipv4'] ?? null;
                    $server_provisioned_ipv6 = $metadata['arsol_server_provisioned_ipv6'] ?? null;
                    $server_provisioned_root_password = $metadata['arsol_server_provisioned_root_password'] ?? null;
                    $server_provisioned_date = $metadata['arsol_server_provisioned_date'] ?? null;
                    $server_provisioned_remote_status = $metadata['arsol_server_provisioned_remote_status'] ?? null;
                    $server_provisioned_remote_raw_status = $metadata['arsol_server_provisioned_remote_raw_status'] ?? null;

                    $task_id = uniqid();
                    as_schedule_single_action(time(), 'arsol_wait_for_server_active_state_hook', [
                        'server_post_id' => $server_post_id,
                        'task_id' => $task_id ?: uniqid()
                    ], 'arsol_class_server_orchestrator');

                    $subscription->add_order_note(
                        'Scheduled background server status update.' . PHP_EOL . '(Task ID: ' . ($task_id ?: uniqid()) . ')'
                    );

                    error_log('#012 [SIYA Server Manager - ServerOrchestrator] Scheduled background server status update for subscription ' . $subscription_id);

                } catch (\Exception $e) {
                    $error_definition = 'Error scheduling background server status update';
                    $this->handle_exception($e);
                }
            }

        } catch (\Exception $e) {

            $this->handle_exception($e);
            
            // Update server metadata on failed provisioning and trip CB
            update_post_meta($server_post_id, '_arsol_state_10_provisioning', -1);

            // Handle the exception and exit
            $this->handle_exception($e);

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($subscription);
            
            return false; // Add fallback return false
        }
    
    }
    
    // Step 3: Wait for server active state (Check server status) 
    public function wait_for_server_active_state($server_post_id, $task_id = null) {

        try {

            error_log('#015 [SIYA Server Manager - ServerOrchestrator] Scheduled server status update started');
            
            $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
            $server_manager = get_post_meta($server_post_id, 'arsol_server_manager', true);
            $connect_server_manager = get_post_meta($server_post_id, '_arsol_server_manager_required', true);
            $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
            $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
            $subscription = wcs_get_subscription($subscription_id);
            $poll_interval = 10;
            $time_out = 120;
        
        
            // Initialize task ID variable
            $task_id = uniqid();
        
        
            // Fetch current IP status
            $server_ip_status = get_post_meta($server_post_id, '_arsol_state_20_ip_address', true);
        
        
            // If IP status is not 2, we want to check and update the server status
            if ($server_ip_status != 2) {

                error_log('#016 [SIYA Server Manager - ServerOrchestrator] IP status is not 2. Checking and updating server status.');

                // Check if the remote server is active
                try {

                    $status_check = $this->wait_for_remote_server_status($server_post_id, 'active');
                    
                    if (!$status_check) {

                        $this->throw_exception('Server status check failed');
                    
                    }

                } catch (\Exception $e) {
                    
                    // Handle the exception and exit
                    $this->handle_exception($e, true);
                    return false;
                
                }
        

                try {
                    // Fetch the provisioned server IPs
                    $server_ipv4 = $this->get_provisioned_server_ip(get_post_meta($server_post_id, 'arsol_server_provider_slug', true), get_post_meta($server_post_id, 'arsol_server_provisioned_id', true), 'ipv4');

                    // Check if the IP threw WP errors
                    if (is_wp_error($server_ipv4)) {
                        $error_message = $server_ipv4->get_error_message();
                        $this->throw_exception($error_message);
                    }

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

            } else {

                error_log('STATE CHECK (20): IP status is okay.');
            }
        
            // Proceed with RunCloud deployment or mark as active
            if ($connect_server_manager === 'yes') {
                // Schedule deploy_to_runcloud_and_update_metadata using Action Scheduler (only once)
                $task_id = uniqid();
                as_schedule_single_action(
                    time(),
                    'arsol_start_server_manager_connection_hook',
                    [$server_post_id, $task_id ?: uniqid()],
                    'arsol_class_server_orchestrator'
                );
        
                // Add order note to inform the user about scheduling
                $message = 'Scheduled the start of the server manager connection.' . PHP_EOL . '(Task ID: ' . ($task_id ?: uniqid()) . ')';
        
                // Add order note for the scheduled action
                $subscription->add_order_note($message);
        
                // Log the message
                error_log($message);

            } else {

                // No server manager required, mark the subscription as active
                $success_message = 'Server is ready, no server manager required. Good day and good luck!';
        
                // Update subscription status to active
                $subscription->add_order_note($success_message);
        
                // Log the success message
                error_log($success_message);

                try {
                    // Reset circuit
                    $circuit_breaker_instance = new ServerCircuitBreaker();
                    $circuit_breaker_instance->reset_circuit_breaker($subscription);
                } catch (\Exception $e) {
                    // Handle the exception
                    $this->handle_exception($e);
                    return false;
                }

            }

        } catch (\Exception $e) {

            // Update server metadata on failed provisioning and trip CB
            update_post_meta($this->server_post_id, '_arsol_state_20_ip_address', -1);

            // Handle the exception and exit
            $this->handle_exception($e);

            return false; // Add fallback return false

        }
    
    }
    
    // Step 4 (Optional): Create server in Runcloud
    public function start_server_manager_connection($server_post_id, $task_id = null) {
    
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
           
            // Schedule apply_firewall_rules using Action Scheduler
            $task_id = uniqid(); 
            as_schedule_single_action(
                time(),
                'arsol_apply_firewall_rules_hook',
                [$server_post_id, $task_id ?: uniqid()],
                'arsol_class_server_orchestrator'
            );

            // Add order note for scheduling the next step
            $subscription->add_order_note(
                'Scheduled the completion of the server manager connection.' . PHP_EOL . '(Task ID: ' . ($task_id ?: uniqid()) . ')'
            );
            
            // Log the scheduling
            error_log('[SIYA Server Manager - ServerOrchestrator] Scheduled the completion of the server manager connection.');
        } 
    
    }

    public function apply_firewall_rules($server_post_id, $task_id = null) {
        // Now handling manager connection finishing logic here
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        $firewall_status = get_post_meta($server_post_id, '_arsol_state_40_firewall_rules', true);
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);

        if (!$subscription) {
            error_log('Failed to retrieve subscription for ID: ' . $subscription_id);
            return;
        }
        error_log('Subscription retrieved successfully: ' . $subscription->get_id());
        $subscription->add_order_note('Server manager connection finishing logic triggered in apply_firewall_rules.');
        if ($firewall_status != 2) {

            try {
                
                $open_ports_result = $this->assign_firewall_rules_to_server($server_provider_slug, $server_provisioned_id);
                
                // Update server metadata on successful provisioning
                if ($open_ports_result) {
                    update_post_meta($server_post_id, '_arsol_state_40_firewall_rules', 2);

                    // Success message
                    $message = 'Successfully opened firewall ports on server.';

                    // Update server note
                    $subscription->add_order_note(
                        $message
                    );

                    // Log the success message
                    error_log($message);
                    
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

        // Schedule installing server manager agent
        $task_id = uniqid(); 
        as_schedule_single_action(
            time(),
            'arsol_install_server_manager_agent_on_server_hook',
            [$server_post_id, $task_id ?: uniqid()],
            'arsol_class_server_orchestrator'
        );
         // Add order note for scheduling the next step
         $subscription->add_order_note(
            'Scheduled the installation of the server manager agent on server.' . PHP_EOL . '(Task ID: ' . ($task_id ?: uniqid()) . ')'
        );
        
    }

    public function install_server_manager_agent_on_server($server_post_id, $task_id = null) {
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);

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

                    // Set the verification wait time
                    $verification_wait_time = 120;

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

        // Schedule the verification with a wait time for new script installations and no wait time for existing installations
        as_schedule_single_action(time() + ($verification_wait_time ?? 0), 
            'arsol_verify_server_manager_agent_installation_on_server_hook', 
            [$server_post_id, $task_id ?: uniqid()],  
            'arsol_class_server_orchestrator'
        );
        
        // Message
        $message = 'Scheduled verification of server installation agent script on server.' . PHP_EOL . '(Task ID: ' . ($task_id ?: uniqid()) . ')';
        
        
        // Update server note
        $subscription->add_order_note(
            $message
        );

        // Log the message
        error_log($message);
    }

    public function verify_server_manager_agent_installation_on_server($server_post_id, $task_id) {
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        $server_manager_instance = new Runcloud();

        error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Verifying server manager agent installation for server post ID: %d', $server_post_id));

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

                        if (isset($status['status'])) {
                            if ($status['status'] === 'running') {
                                update_post_meta($server_post_id, '_arsol_state_50_script_execution', 2);
                                update_post_meta($server_post_id, '_arsol_state_60_script_installation', 2);
                                update_post_meta($server_post_id, 'arsol_server_manager_installation_status', $status['status']);

                                // Success message
                                $message = 'Script installation verified.';
                                $subscription->add_order_note($message);
                                error_log($message);
                                break; // Exit on success

                            } elseif ($status['status'] === 'failed') {
                                update_post_meta($server_post_id, '_arsol_state_50_script_execution', -1); // Set script execution to failed
                                
                                // Throw exception on failure
                                $this->throw_exception('[SIYA Server Manager - ServerOrchestrator] Script installation failed.');
                                return false; // Exit on failure
                            } elseif ($status['status'] === 'not-installed') {
                                update_post_meta($server_post_id, '_arsol_state_50_script_execution', -1); // Set script execution to failed
                                error_log('Script not installed. Retrying...');
                                continue; // Retry if not installed
                            }
                        } else {
                            error_log('Installation status is missing or invalid.');
                        }

                    } catch (\Exception $e) {
                        // Log the exception message
                        error_log('Exception: ' . $e->getMessage());
                        // Handle exception and rethrow
                        $this->handle_exception($e, true);
                    }

                    error_log('[SIYA Server Manager - ServerOrchestrator] Retrying script installation verification after 30 seconds.');
                    sleep(30); // Retry after 30 seconds
                }

                if ((time() - $startTime) >= $installationTimeout) {
                    $timeout_message = '[SIYA Server Manager - ServerOrchestrator] Script installation timeout exceeded for server post ID: ' . $server_post_id;
                    error_log($timeout_message);
                    $subscription->add_order_note($timeout_message);
                    update_post_meta($server_post_id, '_arsol_state_60_script_installation', -1);
                    $this->handle_exception(new \Exception('Timeout while installing script'));
                    ServerCircuitBreaker::trip_circuit_breaker($this->subscription);
                    return false; // Return false after timeout handling
                }


            } else {
                error_log('STATE CHECK (60): Script installation is okay.');
            }

            // Schedule server manager connection verification
            as_schedule_single_action(time(), 
                'arsol_verify_server_manager_connection_to_server_hook', 
                [$server_post_id, $task_id],  
                'arsol_class_server_orchestrator'
            );

            $message = 'Scheduled server manager connection verification.' . PHP_EOL . '(Task ID: ' . $task_id . ')';
            $subscription->add_order_note(
                $message
            );
            error_log($message);

        } catch (\Exception $e) {

            $error_definition = 'Error executing installation script';

            // Update server metadata on failed provisioning and trip CB
            update_post_meta($server_post_id, '_arsol_state_60_script_installation', -1);

            // Handle the exception and exit
            $this->handle_exception($e);

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

            return false; // Add fallback return false

        }
    }

    public function verify_server_manager_connection_to_server($server_post_id, $task_id) {
        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);
        $server_manager_instance = new Runcloud();

        error_log(sprintf('[SIYA Server Manager - ServerOrchestrator] Verifying server manager connection for server post ID: %d', $server_post_id));

        // Prevent PHP from timing out
        set_time_limit(0);

        try {
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

                            $success_message = sprintf(
                                'Server manager connected to server successfully!%sConnected: %s%sOnline: %s%sAgent Version: %s',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_connected', true) ?: 'Not provided',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_online', true) ?: 'Not provided',
                                PHP_EOL,
                                get_post_meta($server_post_id, 'arsol_server_manager_agent_version', true) ?: 'Unknown'
                            );

                            $subscription->add_order_note($success_message);
                            error_log($success_message);
                            break; // Exit the loop on success
                        }

                        if (empty($connStatus['connected']) || empty($connStatus['online'])) {
                            error_log('[SIYA Server Manager - ServerOrchestrator] Server manager is not connected or online.');
                        }

                    } catch (\Exception $e) {
                        $this->handle_exception($e, true);
                    }

                    error_log('[SIYA Server Manager - ServerOrchestrator] Retrying connection status after 15 seconds.');
                    sleep(15); // Retry after 15 seconds
                }

                if ((time() - $connectStart) >= $connectTimeout) {
                    $timeout_message = '[SIYA Server Manager - ServerOrchestrator] Connection status timeout exceeded for server post ID: ' . $server_post_id;
                    error_log($timeout_message);
                    $subscription->add_order_note($timeout_message);
                    
                    $this->throw_exception($timeout_message);
               
                    return false; // Return false after timeout handling
                }

            } else {
                error_log('STATE CHECK (70): Server manager connection status is okay.');
            }

            $success_message = 'Server manager connected to server successfully! Activating subscription to active... Good day and good luck!';
            $subscription->add_order_note($success_message);
            error_log($success_message);

            try {

                // Reset circuit
                $circuit_breaker_instance = new ServerCircuitBreaker();
                $circuit_breaker_instance->reset_circuit_breaker($subscription);

            } catch (\Exception $e) {

                // Handle the exception
                $this->handle_exception($e, true);
                return false;
            }

        } catch (\Exception $e) {
           
            $error_definition = 'Error verifying server manager connection';

            // Update server metadata on failed provisioning and trip CB
            update_post_meta($server_post_id, '_arsol_state_70_manager_connection', -1);

            // Handle the exception and exit
            $this->handle_exception($e);

            // Trigger the circuit breaker
            ServerCircuitBreaker::trip_circuit_breaker($this->subscription);

            return false; // Add fallback return false
            
        }
    }

    
    public function start_server_shutdown($subscription) {

        // Early validation of required parameters
        $subscription_id = $subscription->get_id();
        $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
        
        // If no linked server post ID is found, log the error and exit
        if (!$server_post_id) {
   
            $this->throw_exception('No server post found for shutdown.');
            return;
        }

        // Check circuit breaker status for shutdown
        $this->server_circuit_breaker_position = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);
        
        if ($this->server_circuit_breaker_position == 1) {
 
            $this->throw_exception('Server circuit breaker is in a half-open state. Skipping shutdown.');
            return;
        }

        // Fetch the necessary metadata
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        
        // If any critical metadata is missing, log the error and exit
        if (!$server_provider_slug || !$server_provisioned_id) {

            $this->throw_exception('Missing server provisioning details required to power up the server. Server Post ID: ' . $server_post_id);
            return false;// Add fallback return false
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
        $remote_status = $this->get_and_update_server_remote_status($server_post_id, $server_provider_slug, $server_provisioned_id);
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
       
        // Get server post_id from subscription 
        $server_post_id =  $server_post_id = ServerPost::get_server_post_id_from_subscription($subscription);

        // If no linked server post ID is found, log the error and exit
        if(!$server_post_id) {
            
            $this->throw_exception('Server post not found for power-up. Subscription ID: ' . $subscription->get_id());
            return false; // Add fallback return false
        }
        
        // If subscription is on hold refuse
        if ($subscription->get_status() === 'on-hold') {
            
            $this->throw_exception('Subscription is on hold. Cannot power up the server.');
            return false; // Add fallback return false
        }

        // Fetch server metadata required for the power-up process
        $server_provider_slug = get_post_meta($server_post_id, 'arsol_server_provider_slug', true);
        $server_provisioned_id = get_post_meta($server_post_id, 'arsol_server_provisioned_id', true);
        
        // If any critical metadata is missing, log the error and exit
        if (!$server_provider_slug || !$server_provisioned_id) {

            $this->throw_exception('Missing server provisioning details required to power up the server. Server Post ID: ' . $server_post_id);
            return false;// Add fallback return false
        }

        $subscription_id = $subscription->get_id();

        // Initialize the server provider instance
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
        $remote_status = $this->get_and_update_server_remote_status($server_post_id, $server_provider_slug, $server_provisioned_id);
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

    public function start_server_reboot($subscription) {
        // Implement the logic to start server reboot
        // Similar to start_server_powerup but for rebooting the server
        error_log ('#048 [SIYA Server Manager - ServerOrchestrator] Starting server reboot process');
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


    private function create_and_update_server_post($subscription) {

        $server_product_id = $this->extract_server_product_from_subscription($subscription);
        $server_post_instance = new ServerPost();
        
        error_log(' subscription id: ' . $subscription->get_id());

        $subscription_id = $subscription->get_id(); 
        $server_post_id = $server_post_instance->create_server_post($this->subscription_id);
        
        // Update server post metadata
        if ($server_post_id) {


            // Get the URL for the subscription/order
            $server_post_url = get_edit_post_link($this->server_post_id);
            $message = sprintf(
                'Server post for server ARSOL%d with Post ID %d created successfully! <a href="%s" target="_blank">view</a>',
                $subscription_id,
                $server_post_id,
                esc_url($server_post_url) // Ensure the URL is properly escaped
            );

            // Add server post URL to the subscription note
            $subscription->add_order_note(
                $message
            );

            // Log the message
            error_log('[SIYA Server Manager] ' . $message);

            // Get server product metadata
            $server_product = wc_get_product($server_product_id);

            $manager_required = $server_product->get_meta('arsol_server_manager_required', true);
            $server_groups = $server_product->get_meta('arsol_assigned_server_groups', true) ;
            $server_tags = $server_product->get_meta('arsol_assigned_server_tags', true) ;


            // Update server post metadata with correct meta keys
            $metadata = [
                'arsol_server_subscription_id' => $subscription_id,
                'arsol_server_post_name' => 'ARSOL' . $subscription_id,
                'arsol_server_post_creation_date' => current_time('mysql'),
                'arsol_server_post_status' => 2,
                'arsol_server_product_id' => $server_product_id,
                'arsol_sites_server' => $server_product->get_meta('_arsol_sites_server', true),
                'arsol_ecommerce_optimized' => $server_product->get_meta('_arsol_ecommerce_optimized', true),
                '_arsol_server_manager_required' => $server_product->get_meta('_arsol_server_manager_required', true),
                'arsol_server_provider_slug' => $server_product->get_meta('_arsol_server_provider_slug', true),
                'arsol_server_group_slug' => $server_product->get_meta('_arsol_server_plan_group_slug', true),
                'arsol_server_plan_slug' => $server_product->get_meta('_arsol_server_plan_slug', true),
                'arsol_server_region_slug' => $server_product->get_meta('_arsol_server_region', true),
                'arsol_server_image_slug' => $server_product->get_meta('_arsol_server_image', true),
                'arsol_server_max_applications' => $server_product->get_meta('_arsol_max_applications', true),
                'arsol_server_max_staging_sites' => $server_product->get_meta('_arsol_max_staging_sites', true),
                'arsol_server_suspension' => 'no', // Add suspension status
            ];
            $server_post_instance->update_meta_data($server_post_id, $metadata);

            // Update server post metadata and save
            $subscription->update_meta_data('arsol_linked_server_post_id', $server_post_id);
            $subscription->save();


            try {
                
                $tag_meta_value = $server_product->get_meta('_arsol_assigned_server_tags', true);
                $tag_taxonomy = 'arsol_server_tag';

                // Only attempt to assign tags if meta value exists
                if (!empty($tag_meta_value)) {
                    $tag_success = $this->assign_taxonomy_terms_to_server_post($server_post_id, $tag_meta_value, $tag_taxonomy);
                    if (!$tag_success) {
                        error_log(sprintf('Failed to assign tags to server post ID: %d', $server_post_id));
                        $this->throw_exception('Failed to assign tags to server post');
                    }
                }

                // Assign server groups to the server post
                $group_meta_value = $server_product->get_meta('_arsol_assigned_server_groups', true);
                $group_taxonomy = 'arsol_server_group';

                // Only attempt to assign groups if meta value exists
                if (!empty($group_meta_value)) {
                    $group_success = $this->assign_taxonomy_terms_to_server_post($server_post_id, $group_meta_value, $group_taxonomy);
                    if (!$group_success) {
                        error_log(sprintf('Failed to assign groups to server post ID: %d', $server_post_id));
                        $this->throw_exception('Failed to assign groups to server post');
                    }
                }

                Error_log('Server post created and updated successfully');


            } catch (\Exception $e) {

               // Centralize exception handling for retries
               $this->handle_exception($e,true);

            }

            return $server_post_id;

        } elseif ($server_post_id instanceof \WP_Error) {
            
            $subscription->add_order_note(
                'Failed to create server post. Error: ' . $server_post_id->get_error_message()
            );
        
            $this->throw_exception('Failed to create server post');

        }
    }

    /**
     * Assigns taxonomy terms by term IDs to a server post based on the meta value.
     * Returns true if successful, false if it fails.
     *
     * @param int    $post_id The post ID to assign terms to.
     * @param mixed  $meta_value The meta value containing term IDs.
     * @param string $taxonomy The taxonomy to assign the terms to.
     * @return bool True if terms are assigned successfully, false otherwise.
     */
    function assign_taxonomy_terms_to_server_post($post_id, $meta_value, $taxonomy) {
        if (!$post_id || !$meta_value) {
            return false; // No post ID or meta value, return false
        }
    
        // Unserialize the meta value if it's serialized
        $unserialized_value = maybe_unserialize($meta_value);
    
        // Initialize an array to collect term IDs
        $term_ids = [];
    
        // Check if the value is an array (which it should be in your case)
        if (is_array($unserialized_value)) {
            // Loop through the outer array
            foreach ($unserialized_value as $key => $value) {
                // Check if the value is a nested array
                if (is_array($value)) {
                    // Loop through the nested array and add term IDs to the collection
                    foreach ($nested_value as $nested_value) {
                        $term_ids[] = (int) $nested_value; // Treat as term ID
                    }
                } else {
                    $term_ids[] = (int) $value; // Treat as term ID
                }
            }
    
            // Ensure term IDs are unique
            $term_ids = array_unique($term_ids);
    
            // Attempt to assign term IDs to the post
            $assigned_terms = wp_set_object_terms($post_id, $term_ids, $taxonomy);
    
            // Check if the terms were successfully assigned
            if ($assigned_terms !== false) {
                // Log for debugging, differentiate based on taxonomy
                error_log("Assigned multiple terms to taxonomy '$taxonomy' ");
                return true; // Return true on success
            } else {
                // Log error
                error_log("Failed to assign multiple terms to taxonomy '$taxonomy' " );
                return false; // Return false on failure
            }
        } else {
            // If the meta value is not an array, handle it as a single term ID
            $term_ids = [(int) $unserialized_value]; // Treat as term ID
    
            // Attempt to assign the term ID to the post
            $assigned_terms = wp_set_object_terms($post_id, $term_ids, $taxonomy);
    
            // Check if the term was successfully assigned
            if ($assigned_terms !== false) {
                // Log for debugging
                error_log("Assigned single term to taxonomy '$taxonomy' ");
                return true; // Return true on success
            } else {
                // Log error
                error_log("Failed to assign single term to taxonomy '$taxonomy' ");
                return false; // Return false on failure
            }
        }
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

            $this->handle_exception($e);

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
        } else if (!$server_provider_slug) {
            $this->server_provider_slug = get_post_meta($this->server_post_id, 'arsol_server_provider_slug', true);
        }

        // Initialize the appropriate provider instance
        switch ($server_provider_slug) {
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
                $this->throw_exception('Unknown server provider: ' . $server_provider_slug);
        }
    }

    private function get_and_update_server_remote_status($server_post_id, $server_provider_slug, $server_provisioned_id) {

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

        /**
         * Handle exceptions with optional rethrowing and stack trace logging.
         *
         * @param $e
         * @param int $error_level The error level (default is E_USER_WARNING).
         * @param bool $rethrow Whether to rethrow the exception or not (default is false).
         * @param bool $stack_trace Whether to log the stack trace (default is true).
         */
        protected function handle_exception($e, bool $rethrow = false, int $error_level = E_USER_WARNING, bool $stack_trace = false) {
            // Get the error code from the exception (if available)
            $error_code_msg = $e->getCode() ? sprintf("Error Code: %s\n", $e->getCode()) : '';

            // Get error message from exception
            $error_message = $e->getMessage();

            // Get the stack trace
            $trace_array = $e->getTrace();
            
            // Fetch the first trace entry for the file and line
            $first_trace = $trace_array[0] ?? null;

            // Fetch the second trace entry for the function name (if available)
            $second_trace = $trace_array[1] ?? null;
            $function_name = $second_trace['function'] ?? 'Unknown function';
            $class_name = $second_trace['class'] ?? 'Unknown class'; // Get the parent class name

            // Build the caller info message
            $caller_info = sprintf(
                "SIYA Error Message: \"%s: %s\"--\n Exception triggered in > %s > function/method, called from class: > %s > contained in the file > %s > on line: (%d)",
                $error_code_msg,    // Error Code (if available)
                $error_message,     // Error Message
                $function_name,     // Function name from second trace entry
                $class_name,        // Parent class name from second trace entry
                $first_trace['file'] ?? 'Unknown file', // First trace entry file
                $first_trace['line'] ?? 'Unknown line', // First trace entry line
            );

            // Add caller info to the error message
            $error_message = $caller_info;

            // Add stack trace if requested
            if ($stack_trace) {
                $formatted_trace = [];
                $counter = 1; // Start numbering from 1 for the stack trace
                foreach ($trace_array as $trace_entry) {
                    $entry = sprintf(
                        "Stack Trace #%d:\nFile: %s\nLine: %s\nFunction: %s\nClass: %s\n",
                        $counter++,
                        $trace_entry['file'] ?? 'Unknown file',
                        $trace_entry['line'] ?? 'Unknown line',
                        $trace_entry['function'] ?? 'Unknown function',
                        $trace_entry['class'] ?? 'Unknown class'
                    );
                    $formatted_trace[] = $entry;
                    $formatted_trace[] = "---"; // Add horizontal rule between trace entries
                }

                // Join all formatted trace entries with line breaks
                $error_message .= "\nStack trace:\n" . implode("\n", $formatted_trace);
            }

            // Optionally, trigger an error if not rethrowing
            if (!$rethrow) {
                trigger_error($error_message, $error_level);
            } else {
                // If rethrowing, log it with a specific notice
                throw $e;
            }
        }




    // New helper method to throw exceptions
    private function throw_exception($message) {
    
        // Throw the exception with the constructed message
        throw new \Exception($message);
    
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

    public function wait_for_remote_server_status($server_post_id, $target_status, $poll_interval = 10, $time_out = 120, $max_retries = 10) {

        $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
        $subscription = wcs_get_subscription($subscription_id);

        // Validate required arguments
        if (!$server_post_id || !$target_status) {
            $error_message = 'Missing required arguments: server_post_id or target_status';
            error_log("[SIYA] $error_message");
            throw new \InvalidArgumentException($error_message);
        }

        error_log("[SIYA] Checking for server status='{$target_status}' on server post ID={$server_post_id}.");
        $start_time = time();
        $attempts = 0;

        try {
            while ((time() - $start_time) < $time_out) {
                try {
                    // Fetch the current server status
                    $remote_status = $this->get_and_update_server_remote_status(
                        $server_post_id,
                        get_post_meta($server_post_id, 'arsol_server_provider_slug', true),
                        get_post_meta($server_post_id, 'arsol_server_provisioned_id', true)
                    );

                    error_log('#017 [SIYA Server Manager - ServerOrchestrator] Checking remote status: ' . json_encode($remote_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    // If remote status matches the target status (e.g., "active")
                    if (!empty($remote_status['provisioned_remote_status']) && $remote_status['provisioned_remote_status'] === $target_status) {
                        error_log('#018 [SIYA Server Manager - ServerOrchestrator] Remote status matched target status: ' . $target_status);

                        return true; // Return true if the target status is reached
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

                    // Throw timeout exception if maximum retries or timeout is reached
                    throw new \Exception("Server status check timed out after {$time_out} seconds. Current retry attempt: {$attempts}");
                    
                    // If max retries have been reached, stop the process
                    return false; // Max retries reached or timeout
                }

                $attempts++;
                sleep($poll_interval);
            }
            
        } catch (\Exception $e) {

            error_log("[SIYA] Server failed to reach the target status '{$target_status}' within time.");
            $this->handle_exception($e);
            return false;
        
        }

        error_log("[SIYA] Server failed to reach the target status '{$target_status}' within time.");
        return false;
    }

}




