<?php

namespace Siya\AdminSettings;

class Slugs {
    /**
     * Display the slugs settings page
     */
    public static function settings_page() {
        require_once SIYA_PLUGIN_PATH . 'templates/admin/settings-page-slugs.php';
    }
}
