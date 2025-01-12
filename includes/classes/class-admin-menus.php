<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->add_siya_menu();
        $this->add_slugs_submenu();
        $this->add_api_submenu();
        $this->add_ssh_submenu();
    }

    /**
     * Add top-level SIYA menu to the WordPress admin.
     */
    public function add_siya_menu() {
        add_menu_page(
            'SIYA',                           // Page title
            'SIYA',                           // Menu title
            'manage_options',                  // Capability
            'siya',                            // Menu slug
            array('Siya\AdminSettings\General', 'settings_page'), // Callback function
            'dashicons-admin-generic',         // Icon URL
            6                                  // Position
        );

        // Add General as first submenu
        add_submenu_page(
            'siya',                           // Parent slug
            'General',                        // Page title
            'General',                        // Menu title
            'manage_options',                 // Capability
            'siya',                          // Menu slug (same as parent to make it first item)
            array('Siya\AdminSettings\General', 'settings_page') // Callback function
        );
    }

    /**
     * Add Slugs submenu page
     */
    public function add_slugs_submenu() {
        add_submenu_page(
            'siya',                            // Parent slug
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
            'siya',                            // Parent slug
            'API Keys',                         // Page title
            'API Keys',                         // Menu title
            'manage_options',                   // Capability
            'siya-api-settings',                // Menu slug
            array('Siya\AdminSettings\API', 'settings_page') // Callback function
        );
    }

    /**
     * Add SSH Keys submenu page
     */
    public function add_ssh_submenu() {
        add_submenu_page(
            'siya',
            'SSH Keys',
            'SSH Keys',
            'manage_options',
            'siya-ssh-settings',
            array('Siya\AdminSettings\SSH', 'settings_page')
        );
    }
}
