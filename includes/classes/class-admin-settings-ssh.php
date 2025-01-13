<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        // No need to register settings for SSH keys anymore
    }

    public static function settings_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            update_option('arsol_global_ssh_public_key', sanitize_textarea_field($_POST['arsol_global_ssh_public_key'] ?? ''));
            update_option('arsol_global_ssh_private_key', sanitize_textarea_field($_POST['arsol_global_ssh_private_key'] ?? ''));
        }

        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }

    public function get_ssh_keys() {
        // This method can be removed or updated if needed
    }
}
