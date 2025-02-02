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
        require_once plugin_dir_path(__FILE__) . 'application/class-application-cpt-setup.php';
        require_once plugin_dir_path(__FILE__) . 'application-blueprint/class-application-blueprint-cpt-setup.php';
        require_once plugin_dir_path(__FILE__) . 'blueprint/class-blueprint-cpt-setup.php';
    }

    private function instantiate_classes() {
        new \Siya\CustomPostTypes\ServerPost\Setup();
        new \Siya\CustomPostTypes\ApplicationPost\Setup();
        new \Siya\CustomPostTypes\ApplicationBlueprintPost\Setup();
        new \Siya\CustomPostTypes\BlueprintPost\Setup();
    }
}
