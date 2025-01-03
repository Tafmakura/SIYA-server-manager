<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->add_siya_menu();
        $this->add_slugs_submenu();
        $this->add_api_submenu();
    }

    /**
     * Add top-level SIYA menu to the WordPress admin.
     */
    public function add_siya_menu() {
        add_menu_page(
            'General',                         // Page title
            'SIYA',                      // Menu title (changed)
            'manage_options',                  // Capability
            'siya',                            // Menu slug (changed)
            array('Siya\AdminSettings\General', 'settings_page'), // Callback function
            'dashicons-admin-generic',         // Icon URL
            6                                  // Position
        );
    }

    /**
     * Add Slugs submenu page
     */
    public function add_slugs_submenu() {
        add_submenu_page(
            'siya',                            // Parent slug (changed)
            'Slugs',                           // Page title
            'Slugs',                           // Menu title
            'manage_options',                  // Capability
            'siya-slugs-settings',             // Menu slug
            array('Siya\AdminSettings\Slugs', 'settings_page') // Callback function
        );
    }
    
    /**
     * Add API Settings submenu page
     */
    public function add_api_submenu() {
        add_submenu_page(
            'siya',                            // Parent slug (changed)
            'API Keys',                         // Page title
            'API Keys',                         // Menu title
            'manage_options',                   // Capability
            'siya-api-settings',                // Menu slug
            array('Siya\AdminSettings\API', 'runcloud_settings_page') // Callback function
        );
    }


}
