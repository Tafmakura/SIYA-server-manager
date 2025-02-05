<?php

namespace Siya\AdminSettings;

class API  {
    
    public function __construct() {
        $this->register_api_settings();
    }

    /**
     * Register API settings.
     */
    public function register_api_settings() {
        // Register settings
        register_setting('siya_settings_api', 'runcloud_api_key');
        register_setting('siya_settings_api', 'hetzner_api_key');
        register_setting('siya_settings_api', 'digitalocean_api_key');
        register_setting('siya_settings_api', 'vultr_api_key');

        // Add hooks for field data /*
        add_action('siya_server_managers_fields', array($this, 'get_server_managers_data'));
        add_action('siya_server_providers_fields', array($this, 'get_server_providers_data'));
    }

    public function get_server_managers_data() {
        return [
            'runcloud' => [
                'label' => __('RunCloud API Key', 'arsol_siya'),
                'value' => get_option('runcloud_api_key')
            ]
        ];
    }

    public function get_server_providers_data() {
        return [
            'hetzner' => [
                'label' => __('Hetzner API Key', 'arsol_siya'),
                'value' => get_option('hetzner_api_key')
            ],
            'digitalocean' => [
                'label' => __('Digital Ocean API Key', 'arsol_siya'),
                'value' => get_option('digitalocean_api_key')
            ],
            'vultr' => [
                'label' => __('Vultr API Key', 'arsol_siya'),
                'value' => get_option('vultr_api_key')
            ]
        ];
    }

    /**
     * Render the settings page.
     */
    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../ui/templates/admin/settings-page-api.php';
    }
}
