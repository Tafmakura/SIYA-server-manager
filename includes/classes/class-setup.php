<?php 

namespace Siya;

class Setup {
    public function __construct() {
        $this->include_files();
        $this->initialize_hooks();
    }

    private function include_files() {
        require_once plugin_dir_path(__DIR__) . '/functions/admin-functions.php';
        require_once plugin_dir_path(__DIR__) . '/functions/logic-functions.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-custom-post-types.php';
    }

    private function initialize_hooks() {
        // Add any initialization hooks here
        add_action('init', array($this, 'initialize_custom_post_types'));
    }

    public function initialize_custom_post_types() {
        // Initialize custom post types
        if (class_exists('Siya\CustomPostTypes')) {
            new CustomPostTypes();
        }
    }
}
