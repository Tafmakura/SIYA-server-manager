<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        // Ensure the settings are registered on admin initialization
        add_action('admin_init', array($this, 'register_ssh_settings'));
    }

    public function register_ssh_settings() {
        // Register each setting
        register_setting('siya_settings_ssh', 'arsol_global_ssh_public_key');
        register_setting('siya_settings_ssh', 'arsol_global_ssh_private_key');

        // Add a settings section
        add_settings_section(
            'siya_ssh_section',
            __('SSH Keys Settings', 'arsol_siya'),
            null,
            'siya_settings_ssh'
        );

        // Add fields to the settings section
        add_settings_field(
            'arsol_global_ssh_public_key',
            __('Public Key', 'arsol_siya'),
            array($this, 'render_public_key_field'),
            'siya_settings_ssh',
            'siya_ssh_section'
        );

        add_settings_field(
            'arsol_global_ssh_private_key',
            __('Private Key', 'arsol_siya'),
            array($this, 'render_private_key_field'),
            'siya_settings_ssh',
            'siya_ssh_section'
        );
    }

    public function render_public_key_field() {
        $public_key = get_option('arsol_global_ssh_public_key', '');
        echo "<textarea name='arsol_global_ssh_public_key' rows='5' cols='50'>" . esc_textarea($public_key) . "</textarea>";
    }

    public function render_private_key_field() {
        $private_key = get_option('arsol_global_ssh_private_key', '');
        echo "<textarea name='arsol_global_ssh_private_key' rows='5' cols='50'>" . esc_textarea($private_key) . "</textarea>";
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }
}

