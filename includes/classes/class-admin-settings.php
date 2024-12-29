<?php

namespace Siya;

class AdminSettings {
    public function __construct() {
        add_action('admin_menu', array($this, 'runcloud_settings_menu'));
        add_action('admin_init', array($this, 'register_api_settings'));
    }

    public function runcloud_settings_menu() {
        add_options_page(
            'RunCloud and Hetzner Settings',  // Page title
            'API Settings',                   // Menu title
            'manage_options',                 // Capability
            'api-settings',                   // Menu slug
            array($this, 'runcloud_settings_page') // Callback function
        );
    }

    public function runcloud_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('RunCloud and Hetzner Settings', 'your-text-domain'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('api-settings-group');
                do_settings_sections('api-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_api_settings() {
        register_setting('api-settings-group', 'runcloud_api_key');
        register_setting('api-settings-group', 'hetzner_api_key');

        add_settings_section(
            'api_settings_section',
            __('API Settings', 'your-text-domain'),
            null,
            'api-settings'
        );

        add_settings_field(
            'runcloud_api_key',
            __('RunCloud API Key', 'your-text-domain'),
            array($this, 'runcloud_api_key_field'),
            'api-settings',
            'api_settings_section'
        );

        add_settings_field(
            'hetzner_api_key',
            __('Hetzner API Key', 'your-text-domain'),
            array($this, 'hetzner_api_key_field'),
            'api-settings',
            'api_settings_section'
        );
    }

    public function runcloud_api_key_field() {
        $api_key = get_option('runcloud_api_key');
        ?>
        <input type="text" name="runcloud_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }

    public function hetzner_api_key_field() {
        $api_key = get_option('hetzner_api_key');
        ?>
        <input type="text" name="hetzner_api_key" value="<?php echo esc_attr($api_key); ?>" />
        <?php
    }
}
