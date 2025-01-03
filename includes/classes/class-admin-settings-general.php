<?php

namespace Siya\AdminSettings;

class General {
    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-general.php';
    }
}
