<?php 

namespace Siya\Setup;

class CustomPostTypes {
    public function __construct() {
       
        $this->require_files();
        $this->instatiate_classes();

    }

    private function require_files() {
        // Custom Post Types
        require_once plugin_dir_path(__FILE__) . 'server/class-server-cpt-setup.php';

    }

    private function instatiate_classes() {
        new \Siya\CustomPostTypes\ServerPost\Setup();
    }


    
}
