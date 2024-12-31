<?php

namespace SIYA\CustomPostTypes;

class ServerPost {
    const META_PREFIX = 'arsol_server_';
    public $post_id;
  
    public $server_post_name;
    public $server_post_creation_date;
    public $server_deployed_id;
    public $server_provisioned_id;
    public $server_subscription_id;
    public $server_max_applications;
    public $server_deployed_applications;
    public $server_max_staging_sites;
    public $server_deployed_staging_sites;
    public $server_type;
    public $server_provisioned_os;
    public $server_provisioned_os_version;
    public $server_provisioned_region;
    public $server_deployment_manager;
    public $server_deployed_name;
    public $server_deployed_status;
    public $server_deployed_date;
    public $server_provisioning_provider;
    public $server_provisoned_name;
    public $server_provisoned_status;
    public $server_provisioned_date;
    public $server_status_date;
    public $server_provisioned_ipv4;
    public $server_provisioned_ipv6;
    public $server_provisioned_root_password;

    public function __construct() {
        $this->load_meta_data();
    }

    private function load_meta_data() {
        $this->server_post_name = get_post_meta($this->post_id, self::META_PREFIX . 'post_name', true);
        $this->server_deployed_id = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_id', true);
        $this->server_provisioned_id = get_post_meta($this->post_id, self::META_PREFIX . 'provisioned_id', true);
        $this->server_subscription_id = get_post_meta($this->post_id, self::META_PREFIX . 'subscription_id', true);
        $this->server_max_applications = get_post_meta($this->post_id, self::META_PREFIX . 'max_applications', true);
        $this->server_deployed_applications = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_applications', true);
        $this->server_max_staging_sites = get_post_meta($this->post_id, self::META_PREFIX . 'max_staging_sites', true);
        $this->server_deployed_staging_sites = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_staging_sites', true);
        $this->server_type = get_post_meta($this->post_id, self::META_PREFIX . 'type', true);
        $this->server_provisioned_os = get_post_meta($this->post_id, self::META_PREFIX . 'os', true);
        $this->server_provisioned_os_version = get_post_meta($this->post_id, self::META_PREFIX . 'os_version', true);
        $this->server_provisioned_region = get_post_meta($this->post_id, self::META_PREFIX . 'region', true);
        $this->server_deployment_manager = get_post_meta($this->post_id, self::META_PREFIX . 'manager', true);
        $this->server_deployed_name = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_name', true);
        $this->server_deployed_status = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_status', true);
        $this->server_deployed_date = get_post_meta($this->post_id, self::META_PREFIX . 'deployed_date', true);
        $this->server_provisioning_provider = get_post_meta($this->post_id, self::META_PREFIX . 'provider', true);
        $this->server_provisoned_name = get_post_meta($this->post_id, self::META_PREFIX . 'provisioned_name', true);
        $this->server_provisoned_status = get_post_meta($this->post_id, self::META_PREFIX . 'provisioned_status', true);
        $this->server_provisioned_date = get_post_meta($this->post_id, self::META_PREFIX . 'provisioned_date', true);
        $this->server_status_date = get_post_meta($this->post_id, self::META_PREFIX . 'status_date', true);
        $this->server_provisioned_ipv4 = get_post_meta($this->post_id, self::META_PREFIX . 'ipv4', true);
        $this->server_provisioned_ipv6 = get_post_meta($this->post_id, self::META_PREFIX . 'ipv6', true);
        $this->server_provisioned_root_password = get_post_meta($this->post_id, self::META_PREFIX . 'root_password', true);
    }
    
    public function create_server_post($subscription_id) {
        $post_data = array(
            'post_title'    => 'Server ' . $subscription_id,
            'post_status'   => 'publish',
            'post_type'     => 'server'
        );
        return wp_insert_post($post_data);
    }

    public static function get_server_post_id_by_subscription($subscription_id) {
        $args = array(
            'post_type' => 'server',
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

    public static function get_server_post_by_subscription_id($subscription_id) {
        $server_id = self::get_server_post_id_by_subscription($subscription_id);
        return $server_id ? self::get_server_post_by_id($server_id) : null;
    }

    public static function get_server_post_by_id($server_id) {
        return new self($server_id);
    }

    public static function get_server_post_by_subscription($subscription_id) {
        $args = array(
            'post_type' => 'server',
            'meta_query' => array(
                array(
                    'key' => self::META_PREFIX . 'subscription_id',
                    'value' => $subscription_id
                )
            )
        );
        $query = new \WP_Query($args);
        return $query->posts ? new self($query->posts[0]->ID) : null;
    }

    public function update_meta_data($post_id, array $meta_data) {
        $this->post_id = $post_id;
        
        $defaults = array(
            'provisioned_id' => '',
            'provisioned_os' => '',
            'provisioned_os_version' => '',
            'provisioned_region' => '',
            'provisioning_provider' => '',
            'provisioned_name' => '',
            'provisioned_status' => '',
            'provisioned_date' => '',
            'provisioned_ipv4' => '',
            'provisioned_ipv6' => '',
            'provisioned_root_password' => '',
            'provisioned_applications' => 0,
            'provisioned_staging_sites' => 0,
            'deployed_id' => '',
            'deployed_applications' => 0,
            'deployed_staging_sites' => 0,
            'deployment_manager' => '',
            'deployed_name' => '',
            'deployed_status' => '',
            'deployed_date' =>'',
            'subscription_id' => '',
            'max_applications' => 0,
            'max_staging_sites' => 0,
            'type' => '',
            'manager' => '',
            'status_date' => current_time('mysql'),
        );
        
        $data = wp_parse_args($meta_data, $defaults);
        
        foreach ($data as $key => $value) {
            update_post_meta($post_id, self::META_PREFIX . $key, sanitize_text_field($value));
        }
        
        return true;
    }

    public function get_meta_data($post_id) {
        $this->post_id = $post_id;
        
        $meta_data = array(
            'provisioned_id' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_id', true),
            'provisioned_os' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_os', true),
            'provisioned_os_version' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_os_version', true),
            'provisioned_region' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_region', true),
            'provisioning_provider' => get_post_meta($post_id, self::META_PREFIX . 'provisioning_provider', true),
            'provisioned_name' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_name', true),
            'provisioned_status' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_status', true),
            'provisioned_date' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_date', true),
            'provisioned_ipv4' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_ipv4', true),
            'provisioned_ipv6' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_ipv6', true),
            'provisioned_root_password' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_root_password', true),
            'provisioned_applications' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_applications', true),
            'provisioned_staging_sites' => get_post_meta($post_id, self::META_PREFIX . 'provisioned_staging_sites', true),
            'deployed_id' => get_post_meta($post_id, self::META_PREFIX . 'deployed_id', true),
            'deployed_applications' => get_post_meta($post_id, self::META_PREFIX . 'deployed_applications', true),
            'deployed_staging_sites' => get_post_meta($post_id, self::META_PREFIX . 'deployed_staging_sites', true),
            'deployment_manager' => get_post_meta($post_id, self::META_PREFIX . 'deployment_manager', true),
            'deployed_name' => get_post_meta($post_id, self::META_PREFIX . 'deployed_name', true),
            'deployed_status' => get_post_meta($post_id, self::META_PREFIX . 'deployed_status', true),
            'deployed_date' => get_post_meta($post_id, self::META_PREFIX . 'deployed_date', true),
            'subscription_id' => get_post_meta($post_id, self::META_PREFIX . 'subscription_id', true),
            'max_applications' => get_post_meta($post_id, self::META_PREFIX . 'max_applications', true),
            'max_staging_sites' => get_post_meta($post_id, self::META_PREFIX . 'max_staging_sites', true),
            'type' => get_post_meta($post_id, self::META_PREFIX . 'type', true),
            'manager' => get_post_meta($post_id, self::META_PREFIX . 'manager', true),
            'status_date' => get_post_meta($post_id, self::META_PREFIX . 'status_date', true),
    );
        
        return array_filter($meta_data);
    }

}