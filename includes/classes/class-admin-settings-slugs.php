<?php

namespace Siya\AdminSettings;

class Slugs {
    /**
     * Display the slugs settings page
     */
    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
