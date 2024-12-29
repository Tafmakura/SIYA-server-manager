<?php

namespace Siya;

class AdminMenus {
    public function __construct() {
        $this->add_siya_menu();
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
            array('Siya\AdminSettings', 'runcloud_settings_page'), // Callback function
            'dashicons-admin-generic',         // Icon URL
            6                                  // Position
        );
    }
}
