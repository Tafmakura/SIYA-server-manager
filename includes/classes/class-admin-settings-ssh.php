<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        $this->register_api_settings();
    }

    public function register_api_settings() {
        register_setting('siya_settings_ssh', 'arsol_global_ssh_public_key');
        register_setting('siya_settings_ssh', 'arsol_global_ssh_private_key');

        add_settings_section(
            'siya_ssh_section',
            __('SSH Keys Settings', 'arsol_siya'),
            null,
            'siya_settings_ssh'
        );

        add_settings_field(
            'arsol_global_ssh_public_key',
            __('Public Key', 'arsol_siya'),
            array($this, 'public_key_field'),
            'siya_settings_ssh',
            'siya_ssh_section'
        );

        add_settings_field(
            'arsol_global_ssh_private_key',
            __('Private Key', 'arsol_siya'),
            array($this, 'private_key_field'),
            'siya_settings_ssh',
            'siya_ssh_section'
        );
    }

    public function public_key_field() {
        $public_key = get_option('arsol_global_ssh_public_key', '');
        echo '<textarea name="arsol_global_ssh_public_key" rows="5" cols="50" style="resize: none;">' . esc_textarea($public_key) . '</textarea>';
    }

    public function private_key_field() {
        $private_key = get_option('arsol_global_ssh_private_key', '');
        echo '<textarea name="arsol_global_ssh_private_key" rows="5" cols="50" style="resize: none;">' . esc_textarea($private_key) . '</textarea>';
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }
}
