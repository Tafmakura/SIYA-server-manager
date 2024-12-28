<?php 

namespace Siya;

class Setup {
    public function __construct() {
        $this->include_files();
        $this->initialize_hooks();
    }

    private function include_files() {
        // Debugging statements to verify paths
        error_log('Admin functions path: ' . plugin_dir_path(__FILE__) . '/../functions/admin-functions.php');
        error_log('Logic functions path: ' . plugin_dir_path(__FILE__) . '/../functions/logic-functions.php');

    //    require_once plugin_dir_path(__FILE__) . '/../functions/admin-functions.php';
     //   require_once plugin_dir_path(__FILE__) . '/../functions/logic-functions.php';
    }

    private function initialize_hooks() {
        // Add any initialization hooks here
    }
}
