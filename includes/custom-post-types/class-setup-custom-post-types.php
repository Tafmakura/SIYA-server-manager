<?php 

namespace Siya\Setup;

class CustomPostTypes {
    public function __construct() {
       
        $this->require_files();
        $this->instantiate_classes();

    }

    private function require_files() {
        // Custom Post Types
        require_once plugin_dir_path(__FILE__) . 'server/class-server-cpt-setup.php';
        require_once plugin_dir_path(__FILE__) . 'app/class-app-cpt-setup.php';
        require_once plugin_dir_path(__FILE__) . 'app-blueprint/class-app-blueprint-cpt-setup.php';
    }

    private function instantiate_classes() {
        new \Siya\CustomPostTypes\ServerPost\Setup();
        new \Siya\CustomPostTypes\AppPost\Setup();
        new \Siya\CustomPostTypes\AppBlueprintPost\Setup();
    }
}
