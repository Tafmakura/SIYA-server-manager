<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        // No need to register settings for SSH keys anymore
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }

    public function get_ssh_keys() {
        // This method can be removed or updated if needed
    }
}
