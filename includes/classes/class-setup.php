<?php 

namespace Siya;

class Setup {
    public function __construct() {
        $this->include_files();
        $this->initialize_hooks();
        $this->initialize_classes_on_startup();
    }

    /**
     * Include necessary files.
     */
    private function include_files() {




        // Core Classes
        require_once plugin_dir_path(__DIR__) . '/classes/class-setup.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-menus.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-settings-general.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-settings-slugs.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-settings-api.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-admin-settings-ssh.php';

        require_once plugin_dir_path(__DIR__) . '/custom-post-types/class-setup-custom-post-types.php';

        
        // Interfaces
        
        //require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-provider.php';
        //require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-manager.php';
        

       
        
        // Integrations
        require_once plugin_dir_path(__DIR__) . '/integrations/class-integrations-setup.php';
   
        // UI
        require_once __SIYA_PLUGIN_ROOT__ . '/ui/components/class-components-setup.php';
    }

    /**
     * Initialize WordPress hooks.
     */
    private function initialize_hooks() {
        add_action('admin_init', array($this, 'initialize_admin_settings'));
        add_action('admin_menu', array($this, 'initialize_admin_menus'));
    }

    /**
     * Initialize classes onstart.
     */
    private function initialize_classes_on_startup() {
       

        if (class_exists('Siya\Setup\CustomPostTypes')) {
            $custom_post_types = new \Siya\Setup\CustomPostTypes();
        }
        if (class_exists('Siya\UI\Components\Setup\ComponentSetup')) {
            $component_setup = new \Siya\UI\Components\Setup\ComponentSetup();
        }
        if (class_exists('Siya\Integrations\Setup')) {
            $integrations_setup = new \Siya\Integrations\Setup();
        }

    }



    /**
     * Initialize custom post types.
     */
    public function initialize_custom_post_types() {
        new \Siya\Setup\CustomPostTypes();
    }

    /**
     * Initialize admin settings.
     */
    public function initialize_admin_settings() {
        if (class_exists('Siya\AdminSettings\General')) {
            new \Siya\AdminSettings\General();
        }
        if (class_exists('Siya\AdminSettings\API')) {
            new \Siya\AdminSettings\API();
        }
        if (class_exists('Siya\AdminSettings\Slugs')) {
            new \Siya\AdminSettings\Slugs();
        }
        if (class_exists('Siya\AdminSettings\SSH')) {
            new \Siya\AdminSettings\SSH();
        }
    }

    /**
     * Initialize admin menus.
     */
    public function initialize_admin_menus() {
        if (class_exists('Siya\AdminMenus')) {
            new \Siya\AdminMenus();
        }
    }

}
