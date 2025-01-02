<?php

namespace Siya\Integrations\WooCommerce;

defined('ABSPATH') || exit;

class Product {
   
    public function __construct() {
        add_action('init', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add custom product type options
        add_filter('product_type_options', [$this, 'add_product_option']);
    }

    public function get_product($product_id) {
        return wc_get_product($product_id);
    }

    public function add_product_option($product_types) {
        // Add your custom product type options here
        $product_types['custom_type'] = __('Custom Product Type', 'woocommerce');
        return $product_types;
    }
}
