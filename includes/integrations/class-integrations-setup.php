<?php

namespace Siya\Integrations;

defined('ABSPATH') || exit;

use Siya\AdminSettings\Slugs;

class WooCommerce {
    public function __construct() {
        add_action('init', [$this, 'init']);
    }

    public function include_files() {
        
        // Server Managers
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/server-managers/runcloud/class-runcloud.php';
        
        // Server Providers
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/server-providers/digitalocean/class-digitalocean.php';
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/server-providers/hetzner/class-hetzner.php';
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/server-providers/vultr/class-vultr.php';
        
        // WooCommerce
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/woocommerce/class-woocommerce-product.php';
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/woocommerce/class-woocommerce-product-variation.php';

        // WooCommerce Subscriptions
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/woocommerce-subscriptions/class-server-circuit-breaker.php';
        require_once SIYA_PLUGIN_PATH . 'includes/integrations/woocommerce-subscriptions/class-woocommerce-server-ochestrator.php';

    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\ServerCircuitBreaker')) {
            $circuit_breaker = new \Siya\Integrations\WooCommerceSubscriptions\ServerCircuitBreaker();
        }
        if (class_exists('Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator')) {
            $orchestrator = new \Siya\Integrations\WooCommerceSubscriptions\ServerOrchestrator();
        }

        if (class_exists('Siya\Integrations\WooCommerce\Product')) {
            $woocommerce_product = new \Siya\Integrations\WooCommerce\Product();
        }
    }


}