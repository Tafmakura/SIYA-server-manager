<?php

namespace Siya;

class AdminSettings {
    public function __construct() {
        $this->register_api_settings();
    }

    /**
     * Register API settings.
     */
    public function register_api_settings() {
        register_setting('api-settings-group', 'runcloud_api_key');
        register_setting('api-settings-group', 'hetzner_api_key');

        add_settings_section(
            'api_settings_section', // ID
            __('API Settings', 'your-text-domain'), // Title
            null, // Callback
            'api-settings' // Page
        );

        add_settings_field(
            'runcloud_api_key', // ID
            __('RunCloud API Key', 'your-text-domain'), // Title
            array($this, 'runcloud_api_key_field'), // Callback
            'api-settings', // Page
            'api_settings_section' // Section
        );

        add_settings_field(
            'hetzner_api_key', // ID
            __('Hetzner API Key', 'your-text-domain'), // Title
            array($this, 'hetzner_api_key_field'), // Callback
            'api-settings', // Page
            'api_settings_section' // Section
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
}
