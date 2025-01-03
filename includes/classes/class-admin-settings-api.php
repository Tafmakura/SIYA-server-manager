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
        register_setting('api-settings-group', 'runcloud_api_key');
        register_setting('api-settings-group', 'hetzner_api_key');
        register_setting('api-settings-group', 'digitalocean_api_key');
        register_setting('api-settings-group', 'vultr_api_key');

        // Server Managers Section
        add_settings_section(
            'server_managers_section',
            __('Server Managers', 'arsol_siya'),
            null,
            'api-settings'
        );

        // Server Providers Section
        add_settings_section(
            'server_providers_section',
            __('Server Providers', 'arsol_siya'),
            null,
            'api-settings'
        );

        // Server Managers Fields
        add_settings_field(
            'runcloud_api_key',
            __('RunCloud API Key', 'arsol_siya'),
            array($this, 'runcloud_api_key_field'),
            'api-settings',
            'server_managers_section'
        );

        // Server Providers Fields
        add_settings_field(
            'hetzner_api_key',
            __('Hetzner API Key', 'arsol_siya'),
            array($this, 'hetzner_api_key_field'),
            'api-settings',
            'server_providers_section'
        );

        add_settings_field(
            'digitalocean_api_key',
            __('Digital Ocean API Key', 'arsol_siya'),
            array($this, 'digitalocean_api_key_field'),
            'api-settings',
            'server_providers_section'
        );

        add_settings_field(
            'vultr_api_key',
            __('Vultr API Key', 'arsol_siya'),
            array($this, 'vultr_api_key_field'),
            'api-settings',
            'server_providers_section'
        );
    }

    /**
     * Render the settings page.
     */
    public static function runcloud_settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page.php';
    }

    /**
     * Render the RunCloud API key field.
     */
    public function runcloud_api_key_field() {
        $api_key = get_option('runcloud_api_key');
        ?>
        <input type="text" name="runcloud_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }

    /**
     * Render the Hetzner API key field.
     */
    public function hetzner_api_key_field() {
        $api_key = get_option('hetzner_api_key');
        ?>
        <input type="text" name="hetzner_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }

    /**
     * Render the Digital Ocean API key field.
     */
    public function digitalocean_api_key_field() {
        $api_key = get_option('digitalocean_api_key');
        ?>
        <input type="text" name="digitalocean_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }

    /**
     * Render the Vultr API key field.
     */
    public function vultr_api_key_field() {
        $api_key = get_option('vultr_api_key');
        ?>
        <input type="text" name="vultr_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }
}
