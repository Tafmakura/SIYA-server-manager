<?php

namespace Siya\Integrations\WooCommerce\Product;

use  Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {
        // Add variation specific fields
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        
        // Save variation fields
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_fields'], 10, 2);
    }

    /**
     * Add custom fields to product variation
     */
    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_region{$loop}",
            'name'        => "_arsol_server_variation_region[{$loop}]",
            'label'       => __('Override server region (optional)', 'woocommerce'),
            'desc_tip'    => true,
            'description' => __('Enter the server region override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, '_arsol_server_variation_region', true),
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));

        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_image{$loop}",
            'name'        => "_arsol_server_variation_image[{$loop}]",
            'label'       => __('Override server image (optional)', 'woocommerce'),
            'desc_tip'    => true,
            'description' => __('Enter the server image override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, '_arsol_server_variation_image', true),
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));
    }

    /**
     * Save custom fields for product variation
     */
    public function save_custom_variation_fields($variation_id, $loop) {
        $region = isset($_POST['_arsol_server_variation_region'][$loop]) 
            ? sanitize_text_field($_POST['_arsol_server_variation_region'][$loop]) 
            : '';
        
        $image = isset($_POST['_arsol_server_variation_image'][$loop]) 
            ? sanitize_text_field($_POST['_arsol_server_variation_image'][$loop]) 
            : '';

        // Validate region format if not empty
        if (!empty($region) && !preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
            $region = '';
        }

        // Validate image format if not empty
        if (!empty($image) && !preg_match('/^[a-zA-Z0-9-]+$/', $image)) {
            $image = '';
        }

        update_post_meta($variation_id, '_arsol_server_variation_region', $region);
        update_post_meta($variation_id, '_arsol_server_variation_image', $image);
    }
}
