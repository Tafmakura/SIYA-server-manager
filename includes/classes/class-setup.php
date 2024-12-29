<?php 

namespace Siya;

class Setup {
    public function __construct() {
        $this->include_files();
        $this->initialize_hooks();
    }

    /**
     * Include necessary files.
     */
    private function include_files() {
        require_once plugin_dir_path(__DIR__) . '/functions/logic-functions.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-custom-post-types.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-settings.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-menus.php';
    }

    /**
     * Initialize WordPress hooks.
     */
    private function initialize_hooks() {
        add_action('init', array($this, 'initialize_custom_post_types'));
        add_action('admin_init', array($this, 'initialize_admin_settings'));
        add_action('admin_menu', array($this, 'initialize_admin_menus'));
    }

    /**
     * Initialize custom post types.
     */
    public function initialize_custom_post_types() {
        if (class_exists('Siya\CustomPostTypes')) {
            new \Siya\CustomPostTypes();
        }
    }

    /**
     * Initialize admin settings.
     */
    public function initialize_admin_settings() {
        new AdminSettings();
    }

    /**
     * Initialize admin menus.
     */
    public function initialize_admin_menus() {
        new AdminMenus();
    }
}
