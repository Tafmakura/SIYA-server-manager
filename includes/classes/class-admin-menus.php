<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->runcloud_settings_menu();
    }

    /**
     * Add settings menu to the WordPress admin.
     */
    public function runcloud_settings_menu() {
        add_options_page(
            'RunCloud and Hetzner Settings',  // Page title
            'API Settings',                   // Menu title
            'manage_options',                 // Capability
            'api-settings',                   // Menu slug
            array('Siya\AdminSettings', 'runcloud_settings_page') // Callback function
        );
    }
}
