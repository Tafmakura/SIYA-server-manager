<?php

namespace Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation {
   
    public function __construct() {
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_fields'], 10, 2);
    }

    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        // Check if parent product has server enabled
        $is_server_enabled = get_post_meta($variation->post_parent, '_arsol_server', true) === 'yes';
        if (!$is_server_enabled) return;

        // Get saved values
        $region = get_post_meta($variation->ID, '_variation_region', true);
        $image = get_post_meta($variation->ID, '_variation_image', true);
        
        // Display region field
        woocommerce_wp_text_input([
            'id'          => "variation_region_{$loop}",
            'name'        => "variation_region[{$loop}]",
            'label'       => __('Server Region', 'woocommerce'),
            'value'       => $region,
            'wrapper_class' => 'form-row form-row-first'
        ]);

        // Display image field
        woocommerce_wp_text_input([
            'id'          => "variation_image_{$loop}",
            'name'        => "variation_image[{$loop}]",
            'label'       => __('Server Image', 'woocommerce'),
            'value'       => $image,
            'wrapper_class' => 'form-row form-row-last'
        ]);
    }

    public function save_variation_fields($variation_id, $loop) {
        $fields = ['variation_region', 'variation_image'];
        
        foreach ($fields as $field) {
            $value = isset($_POST[$field][$loop]) ? sanitize_text_field($_POST[$field][$loop]) : '';
            update_post_meta($variation_id, "_{$field}", $value);
        }
    }
}
