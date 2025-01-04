<?php

namespace Siya\AdminSettings;

if (!defined('ABSPATH')) {
    exit;
}

class Slugs {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize in constructor
        $this->init();
    }

    private function init() {
        // Use instance methods for hooks
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    public function register_settings() {
        // Server Manager Settings
        register_setting('siya_slugs_settings', 'siya_server_dashboard_slug');
        register_setting('siya_slugs_settings', 'siya_servers_list_slug');
        
        // WordPress Plan Settings
        register_setting('siya_slugs_settings', 'siya_wp_plans_slug');
        
        // Provider Plans Settings
        register_setting('siya_slugs_settings', 'siya_digitalocean_plans', array(
            'sanitize_callback' => array($this, 'sanitize_provider_plans')
        ));
        register_setting('siya_slugs_settings', 'siya_hetzner_plans', array(
            'sanitize_callback' => array($this, 'sanitize_provider_plans')
        ));
        register_setting('siya_slugs_settings', 'siya_vultr_plans', array(
            'sanitize_callback' => array($this, 'sanitize_provider_plans')
        ));
    }

    public function add_settings_page() {
        add_submenu_page(
            'siya-server-manager', // Parent slug
            'Slugs Settings',      // Page title
            'Slugs Settings',      // Menu title
            'manage_options',      // Capability
            'siya-slugs-settings', // Menu slug
            array($this, 'render_settings_page') // Changed callback name for clarity
        );
    }

    // Renamed from settings_page to render_settings_page
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Make settings instance available to template
        $settings = $this;
        require_once SIYA_PLUGIN_DIR . 'templates/admin/settings-page-slugs.php';
    }

    public function sanitize_provider_plans($plans) {
        if (!is_array($plans)) {
            return array();
        }

        $sanitized_plans = array();
        foreach ($plans as $plan) {
            if (empty($plan['slug'])) {
                continue;
            }

            $sanitized_plans[] = array(
                'slug' => sanitize_text_field($plan['slug']),
                'description' => sanitize_textarea_field($plan['description'])
            );
        }

        return $sanitized_plans;
    }

    // Getter methods
    public function get_dashboard_slug() {
        return get_option('siya_server_dashboard_slug', 'server-dashboard');
    }

    public function get_servers_list_slug() {
        return get_option('siya_servers_list_slug', 'servers');
    }

    public function get_wp_plans_slug() {
        return get_option('siya_wp_plans_slug', 'wordpress-plans');
    }

    public function get_provider_plans($provider) {
        $option_name = 'siya_' . $provider . '_plans';
        return get_option($option_name, array());
    }

    public function get_provider_plan($provider, $plan_slug) {
        $plans = $this->get_provider_plans($provider);
        foreach ($plans as $plan) {
            if ($plan['slug'] === $plan_slug) {
                return $plan;
            }
        }
        return null;
    }

    // Save methods
    public function save_provider_plans($provider, $plans) {
        $option_name = 'siya_' . $provider . '_plans';
        $sanitized_plans = $this->sanitize_provider_plans($plans);
        return update_option($option_name, $sanitized_plans);
    }

    public function add_provider_plan($provider, $slug, $description) {
        $plans = $this->get_provider_plans($provider);
        
        // Check if plan already exists
        foreach ($plans as $plan) {
            if ($plan['slug'] === $slug) {
                return false;
            }
        }

        $plans[] = array(
            'slug' => sanitize_text_field($slug),
            'description' => sanitize_textarea_field($description)
        );

        return $this->save_provider_plans($provider, $plans);
    }

    public function delete_provider_plan($provider, $slug) {
        $plans = $this->get_provider_plans($provider);
        foreach ($plans as $key => $plan) {
            if ($plan['slug'] === $slug) {
                unset($plans[$key]);
                return $this->save_provider_plans($provider, array_values($plans));
            }
        }
        return false;
    }
}
