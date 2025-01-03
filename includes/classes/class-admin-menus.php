<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->add_siya_menu();
        $this->add_api_submenu();
    }

    /**
     * Add top-level SIYA menu to the WordPress admin.
     */
    public function add_siya_menu() {
        add_menu_page(
            'SIYA',                            // Page title
            'SIYA',                            // Menu title
            'manage_options',                  // Capability
            'siya-menu',                       // Menu slug
            array('Siya\AdminSettings\API', 'runcloud_settings_page'), // Callback function
            'dashicons-admin-generic',         // Icon URL
            6                                  // Position
        );
    }

    /**
     * Add API Settings submenu page
     */
    public function add_api_submenu() {
        add_submenu_page(
            'siya-menu',                       // Parent slug
            'API Settings',                    // Page title
            'API Settings',                    // Menu title
            'manage_options',                  // Capability
            'siya-api-settings',               // Menu slug
            array('Siya\AdminSettings\API', 'runcloud_settings_page') // Callback function
        );
    }
}
