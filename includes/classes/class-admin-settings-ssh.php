<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        $this->register_ssh_settings();
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
            'siya_settings_ssh',
            'siya_ssh_section'
        );

        add_settings_field(
            'arsol_global_ssh_private_key',
            __('Private Key', 'arsol_siya'),
            'siya_settings_ssh',
            'siya_ssh_section'
        );
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }
}

