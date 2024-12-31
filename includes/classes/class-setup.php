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

        // Core Classes
        require_once plugin_dir_path(__DIR__) . '/classes/class-setup.php';
        require_once plugin_dir_path(__DIR__) . '/classes/class-setup-custom-post-types.php';
        
        // Interfaces
        
        //require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-provider.php';
        require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-provider-setup.php';
        //require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-manager.php';
        require_once plugin_dir_path(__DIR__) . '/interfaces/interface-server-manager-setup.php';

        // Custom Post Types
        require_once plugin_dir_path(__DIR__) . '/custom-post-types/server/class-server-cpt.php';
        
        // Integrations
        require_once plugin_dir_path(__DIR__) . '/integrations/server-providers/hetzner/class-hetzner.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-providers/hetzner/class-hetzner-setup.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-managers/runcloud/class-runcloud.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/server-managers/runcloud/class-runcloud-setup.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce-subscriptions/class-server-orchestrator.php';
        require_once plugin_dir_path(__DIR__) . '/integrations/woocommerce-subscriptions/class-server-circuit-breaker.php';
    }

    /**
     * Initialize WordPress hooks.
     */
    private function initialize_hooks() {
        add_action('init', array($this, 'initialize_custom_post_types'));
        add_action('admin_init', array($this, 'initialize_admin_settings'));
        add_action('admin_menu', array($this, 'initialize_admin_menus'));
        
        // Hook server orchestrator to subscription status changes
        add_action('woocommerce_subscription_status_active', array($this, 'initialize_server_orchestration'), 10, 1);
        add_action('woocommerce_subscription_status_processing', array($this, 'initialize_server_orchestration'), 10, 1);
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
        if (class_exists('Siya\AdminSettings')) {
            new \Siya\AdminSettings();
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

    public function initialize_server_orchestration($subscription) {
        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator')) {
            $orchestrator = new \Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator($subscription);
        }
    }
}
