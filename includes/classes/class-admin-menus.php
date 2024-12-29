<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->add_siya_menu();
    }

    /**
     * Add top-level SIYA menu and API Settings submenu to the WordPress admin.
     */
    public function add_siya_menu() {
        add_menu_page(
            'SIYA',                            // Page title
            'SIYA',                            // Menu title
            'manage_options',                  // Capability
            'siya-menu',                       // Menu slug
            '',                                // Callback function (empty for top-level menu)
            'dashicons-admin-generic',         // Icon URL
            6                                  // Position
        );

        add_submenu_page(
            'siya-menu',                       // Parent slug
            'RunCloud and Hetzner Settings',   // Page title
            'API Settings',                    // Menu title
            'manage_options',                  // Capability
            'api-settings',                    // Menu slug
            array('Siya\AdminSettings', 'runcloud_settings_page') // Callback function
        );
    }
}
