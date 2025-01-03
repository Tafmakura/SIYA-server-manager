<?php

namespace Siya\AdminSettings;

class Slugs {
    /**
     * Display the slugs settings page
     */
    public function settings_page() {
        require_once SIYA_PLUGIN_PATH . 'templates/admin/settings-page-slugs.php';
    }
}
