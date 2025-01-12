<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        $this->register_ssh_settings();
    }

    public function register_ssh_settings() {
        register_setting('siya_settings_ssh', 'arsol_ssh_private_key', [
            'sanitize_callback' => function($value) {
                error_log('[SIYA Server Manager][SSH] Saving private key');
                if (empty($value)) {
                    error_log('[SIYA Server Manager][SSH] Warning: Empty private key provided');
                }
                return $value;
            }
        ]);
        register_setting('siya_settings_ssh', 'arsol_ssh_public_key', [
            'sanitize_callback' => function($value) {
                error_log('[SIYA Server Manager][SSH] Saving public key');
                if (empty($value)) {
                    error_log('[SIYA Server Manager][SSH] Warning: Empty public key provided');
                }
                return $value;
            }
        ]);
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }

    public function get_ssh_keys() {
        $private_key = get_option('arsol_ssh_private_key');
        $public_key = get_option('arsol_ssh_public_key');
        
        if (empty($private_key) || empty($public_key)) {
            error_log('[SIYA Server Manager][SSH] Warning: One or both SSH keys are missing');
        } else {
            error_log('[SIYA Server Manager][SSH] Successfully retrieved SSH keys');
        }
        
        return [
            'private_key' => $private_key,
            'public_key' => $public_key
        ];
    }
}
