<?php

namespace SIYA\CustomPostTypes;

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
    public $wordpress_server;
    public $wordpress_ecommerce;
    public $server_provider_slug;
    public $server_group_slug;
    public $server_plan_slug;
    public $server_region_slug;
    public $server_image_slug;
    
    // Provisioned/Provisioning properties
    public $server_provisioned_id;
    public $server_provisioned_name;
    public $server_provisioned_status;
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
    public $server_deployed_date;
    public $server_deployed_applications;
    public $server_deployed_staging_sites;
    public $server_deployment_manager;

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
        $this->wordpress_server = get_post_meta($this->post_id, 'arsol_wordpress_server', true);
        $this->wordpress_ecommerce = get_post_meta($this->post_id, 'arsol_wordpress_ecommerce', true);
        $this->server_provider_slug = get_post_meta($this->post_id, 'arsol_server_provider_slug', true);
        $this->server_group_slug = get_post_meta($this->post_id, 'arsol_server_group_slug', true);
        $this->server_plan_slug = get_post_meta($this->post_id, 'arsol_server_plan_slug', true);
        $this->server_region_slug = get_post_meta($this->post_id, 'arsol_server_region_slug', true);
        $this->server_image_slug = get_post_meta($this->post_id, 'arsol_server_image_slug', true);
        
        // Provisioned/Provisioning data
        $this->server_provisioned_id = get_post_meta($this->post_id, 'arsol_server_provisioned_id', true);
        $this->server_provisioned_name = get_post_meta($this->post_id, 'arsol_server_provisioned_name', true);
        $this->server_provisioned_status = get_post_meta($this->post_id, 'arsol_server_provisioned_status', true);
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
        $this->server_deployed_status = get_post_meta($this->post_id, 'arsol_server_deployed_status', true);
        $this->server_deployed_date = get_post_meta($this->post_id, 'arsol_server_deployed_date', true);
        $this->server_deployed_applications = get_post_meta($this->post_id, 'arsol_server_deployed_applications', true);
        $this->server_deployed_staging_sites = get_post_meta($this->post_id, 'arsol_server_deployed_staging_sites', true);
        $this->server_deployment_manager = get_post_meta($this->post_id, 'arsol_server_deployment_manager', true);
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
   
    public static function get_server_post_by_subscription($subscription) {
        $subscription_id = $subscription->get_id();
        return self::get_server_post_by_subscription_id($subscription_id);
    }
    
   
    public static function get_server_post_by_subscription_id($subscription_id) {
        $args = array(
            'post_type' => 'server',
            'meta_query' => array(
                array(
                    'key' => 'arsol_server_subscription_id',
                    'value' => $subscription_id
                )
            )
        );
        $query = new \WP_Query($args);
        return $query->posts ? new self($query->posts[0]->ID) : null;
    }

    public function update_meta_data($post_id, array $meta_data) {
        $this->post_id = $post_id;
        
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, sanitize_text_field($value));
        }
        
        return true;
    }

    public function get_meta_data($post_id) {
        $this->post_id = $post_id;
        
        $meta_data = array(
            // Base and general data
            'arsol_server_post_status' => get_post_meta($post_id, 'arsol_server_post_status', true),
            'arsol_server_subscription_id' => get_post_meta($post_id, 'arsol_server_subscription_id', true),
            'arsol_server_max_applications' => get_post_meta($post_id, 'arsol_server_max_applications', true),
            'arsol_server_max_staging_sites' => get_post_meta($post_id, 'arsol_server_max_staging_sites', true),
            'arsol_server_type' => get_post_meta($post_id, 'arsol_server_type', true),
            'arsol_server_status_date' => get_post_meta($post_id, 'arsol_server_status_date', true),
            'arsol_wordpress_server' => get_post_meta($post_id, 'arsol_wordpress_server', true),
            'arsol_wordpress_ecommerce' => get_post_meta($post_id, 'arsol_wordpress_ecommerce', true),
            'arsol_server_provider_slug' => get_post_meta($post_id, 'arsol_server_provider_slug', true),
            'arsol_server_group_slug' => get_post_meta($post_id, 'arsol_server_group_slug', true),
            'arsol_server_plan_slug' => get_post_meta($post_id, 'arsol_server_plan_slug', true),
            'arsol_server_region_slug' => get_post_meta($post_id, 'arsol_server_region_slug', true),
            'arsol_server_image_slug' => get_post_meta($post_id, 'arsol_server_image_slug', true),
            'arsol_server_connection_status' => get_post_meta($post_id, 'arsol_server_connection_status', true),
            
            // Provisioned/Provisioning data
            'arsol_server_provisioned_id' => get_post_meta($post_id, 'arsol_server_provisioned_id', true),
            'arsol_server_provisioned_name' => get_post_meta($post_id, 'arsol_server_provisioned_name', true),
            'arsol_server_provisioned_status' => get_post_meta($post_id, 'arsol_server_provisioned_status', true),
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
            
            // Deployed/Deploying data
            'arsol_server_deployed_id' => get_post_meta($post_id, 'arsol_server_deployed_id', true),
            'arsol_server_deployed_name' => get_post_meta($post_id, 'arsol_server_deployed_name', true),
            'arsol_server_deployed_status' => get_post_meta($post_id, 'arsol_server_deployed_status', true),
            'arsol_server_deployed_date' => get_post_meta($post_id, 'arsol_server_deployed_date', true),
            'arsol_server_deployed_applications' => get_post_meta($post_id, 'arsol_server_deployed_applications', true),
            'arsol_server_deployed_staging_sites' => get_post_meta($post_id, 'arsol_server_deployed_staging_sites', true),
            'arsol_server_deployment_manager' => get_post_meta($post_id, 'arsol_server_deployment_manager', true),
        );
        
        return array_filter($meta_data);
    }
    
}