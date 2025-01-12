<?php

namespace Siya\AdminSettings;

class SSH {
    public function __construct() {
        $this->register_ssh_settings();
    }

    public function register_ssh_settings() {
        register_setting('siya_settings_ssh', 'ssh_private_key');
        register_setting('siya_settings_ssh', 'ssh_public_key');
    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-ssh.php';
    }
}
