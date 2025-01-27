<?php

namespace Siya\Integrations;

defined('ABSPATH') || exit;

use Siya\AdminSettings\Slugs;

class Setup {
    public function __construct() {

        $this->include_files();
        $this->initialize_classes();
    }

    public function include_files() {
        
        // Server Managers
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/server-managers/runcloud/class-runcloud.php';
        
        // Server Providers
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/server-providers/digitalocean/class-digitalocean.php';
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/server-providers/hetzner/class-hetzner.php';
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/server-providers/vultr/class-vultr.php';
        
        // WooCommerce
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/woocommerce/class-woocommerce-product.php';
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/woocommerce/class-woocommerce-product-variation.php';

        // WooCommerce Subscriptions
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/woocommerce-subscriptions/class-server-orchestrator.php';
        require_once __SIYA_PLUGIN_ROOT__ . 'includes/integrations/woocommerce-subscriptions/class-server-circuit-breaker.php';
     
    }

    public function initialize_classes() {

      new \Siya\Integrations\WoocommerceSubscriptions\ServerOrchestrator();
    //new \Siya\Integrations\WoocommerceSubscriptions\ServerCircuitBreaker();
      new \Siya\Integrations\Woocommerce\Product();
      new \Siya\Integrations\Woocommerce\Product\Variation();

    }


}