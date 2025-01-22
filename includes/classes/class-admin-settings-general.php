<?php

namespace Siya\AdminSettings;

class General {
    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-general.php';
    }

    public function register_settings() {
        register_setting('siya_settings_general', 'arsol_allow_admin_server_delition');
    }
}
