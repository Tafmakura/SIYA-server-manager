<?php

namespace Siya\UI\Components\Setup;

class ComponentSetup {
    public function __construct() {

        // Initialize the component setup
        $this->initialize_class();
        $this->include_components();
        $this->include_sub_components();
        $this->initialize_components();

    }

    public function include_components() {
        // Code to include main components
    }

    public function include_sub_components() {
        // Code to include sub-components
        require_once __DIR__ . '/admin/sub-components/status-pill-simple.php';
    }

    public function initialize_components() {
        // Code to initialize components
    }

    public function initialize_class() {
        // Component setup code here
    }

}
