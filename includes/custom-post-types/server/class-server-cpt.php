<?php

namespace Siya\CustomPostTypes;

class ServerPost {
    // Base and general properties
    public $post_id;
    public $server_post_name;
    public $server_post_status;
    public $server_post_creation_date;
    public $server_subscription_id;
    public $server_max_applications;
    public $server_max_staging_sites;
    public $server_type;
    public $server_status_date;
    public $server_connection_status;
    public $server_product_id;
    public $sites_server;
    public $ecommerce_optimized;
    public $connect_server_manager;  // Added missing property
    public $server_provider_slug;
    public $server_group_slug;
    public $server_plan_slug;
    public $server_region_slug;
    public $server_image_slug;
    public $server_suspension; // Add suspension status
    public $arsol_state_10_provisioning;
    public $arsol_state_20_ip_address;
    public $arsol_state_30_deployment;
    public $arsol_state_40_firewall_rules;
    public $arsol_state_50_script_execution;
    public $arsol_state_60_script_installation;
    public $arsol_state_70_manager_connection;

    // Provisioned/Provisioning properties
    public $server_provisioned_id;
    public $server_provisioned_name;
    public $server_provisioned_status;
    public $server_provisioned_remote_status;
    public $server_provisioned_remote_raw_status;
    public $server_provisioned_date;
    public $server_provisioned_os;
    public $server_provisioned_os_version;
    public $server_provisioned_region;
    public $server_provisioned_ipv4;
    public $server_provisioned_ipv6;
    public $server_provisioned_root_password;
    public $server_provisioned_vcpu_count;
    public $server_provisioned_memory;
    public $server_provisioned_disk_size;
    public $server_provisioned_add_ons;
    public $server_provisioning_provider;

    // Deployed/Deploying properties
    public $server_deployed_id;
    public $server_deployed_name;
    public $server_deployed_status;
    public $server_deployed_remote_status;
    public $server_deployed_date;
    public $server_deployed_server_id;  // Added missing property
    public $server_deployed_applications;
    public $server_deployed_staging_sites;
    public $server_deployment_date;  // Added missing property
    public $server_manager;

    public function __construct($post_id = null) {
        if (is_int($post_id) && $post_id > 0) {
            $this->post_id = $post_id;
            $this->load_meta_data();
        }
       
    }

    private function load_meta_data() {
        // Base and general data
        $this->server_post_name = get_post_meta($this->post_id, 'arsol_server_post_name', true);
        $this->server_post_status = get_post_meta($this->post_id, 'arsol_server_post_status', true);
        $this->server_subscription_id = get_post_meta($this->post_id,  'arsol_server_subscription_id', true);
        $this->server_max_applications = get_post_meta($this->post_id, 'arsol_server_max_applications', true);
        $this->server_max_staging_sites = get_post_meta($this->post_id, 'arsol_server_max_staging_sites', true);
        $this->server_type = get_post_meta($this->post_id, 'arsol_server_type', true);
        $this->server_status_date = get_post_meta($this->post_id, 'arsol_server_status_date', true);
        $this->server_connection_status = get_post_meta($this->post_id, 'arsol_server_connection_status', true);
        $this->server_product_id = get_post_meta($this->post_id, 'arsol_server_product_id', true);
        $this->sites_server = get_post_meta($this->post_id, 'arsol_sites_server', true);
        $this->ecommerce_optimized = get_post_meta($this->post_id, 'arsol_ecommerce_optimized', true);
        $this->server_provider_slug = get_post_meta($this->post_id, 'arsol_server_provider_slug', true);
        $this->server_group_slug = get_post_meta($this->post_id, 'arsol_server_group_slug', true);
        $this->server_plan_slug = get_post_meta($this->post_id, 'arsol_server_plan_slug', true);
        $this->server_region_slug = get_post_meta($this->post_id, 'arsol_server_region_slug', true);
        $this->server_image_slug = get_post_meta($this->post_id, 'arsol_server_image_slug', true);
        $this->connect_server_manager = get_post_meta($this->post_id, '_arsol_server_manager_required', true);  // Add missing load
        $this->server_suspension = get_post_meta($this->post_id, 'arsol_server_suspension', true); // Add missing load
        $this->arsol_state_10_provisioning = get_post_meta($this->post_id, '_arsol_state_10_provisioning', true);
        $this->arsol_state_20_ip_address = get_post_meta($this->post_id, '_arsol_state_20_ip_address', true);
        $this->arsol_state_30_deployment = get_post_meta($this->post_id, '_arsol_state_30_deployment', true);
        $this->arsol_state_40_firewall_rules = get_post_meta($this->post_id, '_arsol_state_40_firewall_rules', true);
        $this->arsol_state_50_script_execution = get_post_meta($this->post_id, '_arsol_state_50_script_execution', true);
        $this->arsol_state_60_script_installation = get_post_meta($this->post_id, '_arsol_state_60_script_installation', true);
        $this->arsol_state_70_manager_connection = get_post_meta($this->post_id, '_arsol_state_70_manager_connection', true);
        
        // Provisioned/Provisioning data
        $this->server_provisioned_id = get_post_meta($this->post_id, 'arsol_server_provisioned_id', true);
        $this->server_provisioned_name = get_post_meta($this->post_id, 'arsol_server_provisioned_name', true);
        $this->server_provisioned_remote_status = get_post_meta($this->post_id, 'arsol_server_provisioned_remote_status', true);
        $this->server_provisioned_remote_raw_status = get_post_meta($this->post_id, 'arsol_server_provisioned_remote_raw_status', true);
        $this->server_provisioned_status = get_post_meta($this->post_id, '_arsol_state_10_provisioning', true);
        $this->server_provisioned_date = get_post_meta($this->post_id, 'arsol_server_provisioned_date', true);
        $this->server_provisioned_os = get_post_meta($this->post_id, 'arsol_server_provisioned_os', true);
        $this->server_provisioned_os_version = get_post_meta($this->post_id, 'arsol_server_provisioned_os_version', true);
        $this->server_provisioned_region = get_post_meta($this->post_id, 'arsol_server_provisioned_region', true);
        $this->server_provisioned_ipv4 = get_post_meta($this->post_id, 'arsol_server_provisioned_ipv4', true);
        $this->server_provisioned_ipv6 = get_post_meta($this->post_id, 'arsol_server_provisioned_ipv6', true);
        $this->server_provisioned_root_password = get_post_meta($this->post_id, 'arsol_server_provisioned_root_password', true);
        $this->server_provisioned_vcpu_count = get_post_meta($this->post_id, 'arsol_server_provisioned_vcpu_count', true);
        $this->server_provisioned_memory = get_post_meta($this->post_id, 'arsol_server_provisioned_memory', true);
        $this->server_provisioned_disk_size = get_post_meta($this->post_id, 'arsol_server_provisioned_disk_size', true);
        $this->server_provisioned_add_ons = get_post_meta($this->post_id, 'arsol_server_provisioned_add_ons', true);
        $this->server_provisioning_provider = get_post_meta($this->post_id, 'arsol_server_provisioning_provider', true);
        
        // Deployed/Deploying data
        $this->server_deployed_id = get_post_meta($this->post_id, 'arsol_server_deployed_id', true);
        $this->server_deployed_name = get_post_meta($this->post_id, 'arsol_server_deployed_name', true);
        $this->server_deployed_remote_status = get_post_meta($this->post_id, 'arsol_server_deployed_remote_status', true);
        $this->server_deployed_status = get_post_meta($this->post_id, '_arsol_state_30_deployment', true);
        $this->server_deployed_date = get_post_meta($this->post_id, 'arsol_server_deployed_date', true);
        $this->server_deployed_applications = get_post_meta($this->post_id, 'arsol_server_deployed_applications', true);
        $this->server_deployed_staging_sites = get_post_meta($this->post_id, 'arsol_server_deployed_staging_sites', true);
        $this->server_deployed_server_id = get_post_meta($this->post_id, 'arsol_server_deployed_id', true);  // Add missing load
        $this->server_deployment_date = get_post_meta($this->post_id, 'arsol_server_deployment_date', true);  // Add missing load
        $this->server_manager = get_post_meta($this->post_id, 'arsol_server_manager', true);  // Changed from arsol_server_deployment_manager
    }
    
    public function create_server_post($subscription_id) {
        $server_name = 'ARSOL' . $subscription_id;
        
        $post_data = array(
            'post_title'    => $server_name,
            'post_status'   => 'publish',
            'post_type'     => 'server'
        );

        $created_post_id = wp_insert_post($post_data);

        error_log('[SIYA Server Manager] Created server post with ID: ' . $created_post_id);

        return $created_post_id;
    }

    public static function get_server_post_by_id($server_id) {
        return new self($server_id);
    }
   
   
    public static function get_server_post_id_from_subscription($subscription) {
        // Get the subscription ID
        $subscription_id = $subscription->get_id();
    
        // Check if there's a directly linked server post ID in the meta
        $linked_server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
    
        if ($linked_server_post_id) {
            // If the linked server post ID is found, return it as an integer
            return (int) $linked_server_post_id;
        }
    
        // If no linked server post ID is found, perform a query to find it
        $args = array(
            'post_type' => 'server',
            'meta_query' => array(
                array(
                    'key' => 'arsol_server_subscription_id',
                    'value' => $subscription_id,
                ),
            ),
        );
    
        $query = new \WP_Query($args);
    
        if ($query->posts) {
            // If a matching server post is found, return the post ID as an integer
            return (int) $query->posts[0]->ID;
        }
    
        // If no server post is found, return false
        return false;
    }
    
    
    public function update_meta_data($post_id, array $meta_data) {
        $this->post_id = $post_id;
        
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, sanitize_text_field($value));
        }
        
        return true;
    }

    public function get_meta_data() {

        $post_id = $this->post_id;
        
        $meta_data = array(
            // Base and general data
            'arsol_server_post_status' => get_post_meta($post_id, 'arsol_server_post_status', true),
            'arsol_server_subscription_id' => get_post_meta($post_id, 'arsol_server_subscription_id', true),
            'arsol_server_max_applications' => get_post_meta($post_id, 'arsol_server_max_applications', true),
            'arsol_server_max_staging_sites' => get_post_meta($post_id, 'arsol_server_max_staging_sites', true),
            'arsol_server_type' => get_post_meta($post_id, 'arsol_server_type', true),
            'arsol_server_status_date' => get_post_meta($post_id, 'arsol_server_status_date', true),
            'arsol_sites_server' => get_post_meta($post_id, 'arsol_sites_server', true),
            'arsol_ecommerce_optimized' => get_post_meta($post_id, 'arsol_ecommerce_optimized', true),
            'arsol_server_provider_slug' => get_post_meta($post_id, 'arsol_server_provider_slug', true),
            'arsol_server_group_slug' => get_post_meta($post_id, 'arsol_server_group_slug', true),
            'arsol_server_plan_slug' => get_post_meta($post_id, 'arsol_server_plan_slug', true),
            'arsol_server_region_slug' => get_post_meta($post_id, 'arsol_server_region_slug', true),
            'arsol_server_image_slug' => get_post_meta($post_id, 'arsol_server_image_slug', true),
            'arsol_server_connection_status' => get_post_meta($post_id, 'arsol_server_connection_status', true),
            '_arsol_server_manager_required' => get_post_meta($post_id, '_arsol_server_manager_required', true),  // Added missing field
            'arsol_server_post_creation_date' => get_post_meta($post_id, 'arsol_server_post_creation_date', true),  // Added missing field
            'arsol_server_post_name' => get_post_meta($post_id, 'arsol_server_post_name', true),  // Added missing field
            'arsol_server_suspension' => get_post_meta($post_id, 'arsol_server_suspension', true), // Add missing field
            
            // Provisioned/Provisioning data
            'arsol_server_provisioned_id' => get_post_meta($post_id, 'arsol_server_provisioned_id', true),
            'arsol_server_provisioned_name' => get_post_meta($post_id, 'arsol_server_provisioned_name', true),
            'arsol_server_provisioned_remote_status' => get_post_meta($post_id, 'arsol_server_provisioned_remote_status', true),
            'arsol_server_provisioned_remote_raw_status' => get_post_meta($post_id, 'arsol_server_provisioned_remote_raw_status', true),
            '_arsol_state_10_provisioning' => get_post_meta($post_id, '_arsol_state_10_provisioning', true),
            'arsol_server_provisioned_date' => get_post_meta($post_id, 'arsol_server_provisioned_date', true),
            'arsol_server_provisioned_os' => get_post_meta($post_id, 'arsol_server_provisioned_os', true),
            'arsol_server_provisioned_os_version' => get_post_meta($post_id, 'arsol_server_provisioned_os_version', true),
            'arsol_server_provisioned_region' => get_post_meta($post_id, 'arsol_server_provisioned_region', true),
            'arsol_server_provisioned_ipv4' => get_post_meta($post_id, 'arsol_server_provisioned_ipv4', true),
            'arsol_server_provisioned_ipv6' => get_post_meta($post_id, 'arsol_server_provisioned_ipv6', true),
            'arsol_server_provisioned_root_password' => get_post_meta($post_id, 'arsol_server_provisioned_root_password', true),
            'arsol_server_provisioned_vcpu_count' => get_post_meta($post_id, 'arsol_server_provisioned_vcpu_count', true),
            'arsol_server_provisioned_memory' => get_post_meta($post_id, 'arsol_server_provisioned_memory', true),
            'arsol_server_provisioned_disk_size' => get_post_meta($post_id, 'arsol_server_provisioned_disk_size', true),
            'arsol_server_provisioned_add_ons' => get_post_meta($post_id, 'arsol_server_provisioned_add_ons', true),
            'arsol_server_provisioning_provider' => get_post_meta($post_id, 'arsol_server_provisioning_provider', true),
            '_arsol_state_20_ip_address' => get_post_meta($post_id, '_arsol_state_20_ip_address', true),
            '_arsol_state_30_deployment' => get_post_meta($post_id, '_arsol_state_30_deployment', true),
            '_arsol_state_40_firewall_rules' => get_post_meta($post_id, '_arsol_state_40_firewall_rules', true),
            '_arsol_state_50_script_execution' => get_post_meta($post_id, '_arsol_state_50_script_execution', true),
            '_arsol_state_60_script_installation' => get_post_meta($post_id, '_arsol_state_60_script_installation', true),
            '_arsol_state_70_manager_connection' => get_post_meta($post_id, '_arsol_state_70_manager_connection', true),
            
            // Deployed/Deploying data
            'arsol_server_deployed_id' => get_post_meta($post_id, 'arsol_server_deployed_id', true),
            'arsol_server_deployed_name' => get_post_meta($post_id, 'arsol_server_deployed_name', true),
            'arsol_server_deployed_remote_status' => get_post_meta($post_id, 'arsol_server_deployed_remote_status', true),
            '_arsol_state_30_deployment' => get_post_meta($post_id, '_arsol_state_30_deployment', true),
            'arsol_server_deployed_date' => get_post_meta($post_id, 'arsol_server_deployed_date', true),
            'arsol_server_deployed_applications' => get_post_meta($post_id, 'arsol_server_deployed_applications', true),
            'arsol_server_deployed_staging_sites' => get_post_meta($post_id, 'arsol_server_deployed_staging_sites', true),
            'arsol_server_manager' => get_post_meta($post_id, 'arsol_server_manager', true),  // Changed from arsol_server_deployment_manager
            'arsol_server_deployment_date' => get_post_meta($post_id, 'arsol_server_deployment_date', true),  // Added missing field
        );
        
        return $meta_data;
    }

}