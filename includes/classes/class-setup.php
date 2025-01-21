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
        require_once plugin_dir_path(__DIR__) . '/integrations/server-managers/runcloud/class-runcloud.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-providers/digitalocean/class-digitalocean.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-providers/hetzner/class-hetzner.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-providers/vultr/class-vultr.php';
 
        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce-subscriptions/class-server-orchestrator.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce-subscriptions/class-server-circuit-breaker.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce-subscriptions/statuses/class-server-error.php';


        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce/class-woocommerce-product.php';
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
       
        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\ServerCircuitBreaker')) {
            $circuit_breaker = new \Siya\Integrations\WooCommerceSubscriptions\ServerCircuitBreaker();
        }
        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator')) {
            $orchestrator = new \Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator();
        }
        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\Statuses\ServerError')) {
           $statuses = new \Siya\Integrations\WooCommerceSubscriptions\Statuses\ServerError();
        }
        if (class_exists('Siya\Integrations\WooCommerce\Product')) {
            $woocommerce_product = new \Siya\Integrations\WooCommerce\Product();
        }
        if (class_exists('Siya\Setup\CustomPostTypes')) {
            $custom_post_types = new \Siya\Setup\CustomPostTypes();
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
