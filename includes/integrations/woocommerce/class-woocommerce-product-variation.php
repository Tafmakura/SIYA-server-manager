<?php

namespace Siya\Integrations\WooCommerce\Product;

use Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {
        // Remove old validation hooks
       // add_action('woocommerce_before_variation_object_save', [$this, 'validate_variation_fields'], 5, 2);
        
        // Admin UI hooks
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        
        // Save hooks (should run after validation)
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_fields'], 15, 2);
    }
    /**
     * Add custom fields to product variation
     */
    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        // Get the parent product
        $parent_product = wc_get_product($variation->post_parent);
        
        // Check if this is a variable subscription
        if (!$parent_product || $parent_product->get_type() !== 'variable-subscription') {
            return;
        }

        // Check if arsol_server is enabled
        $is_server_enabled = get_post_meta($variation->post_parent, '_arsol_server', true) === 'yes';
        $hidden_class = $is_server_enabled ? '' : 'hidden';
        
        // Fix: Get the saved values using the correct meta keys
        $region_value = get_post_meta($variation->ID, '_arsol_server_variation_region', true);
        $image_value = get_post_meta($variation->ID, '_arsol_server_variation_image', true);
        
        woocommerce_wp_text_input(array(
            'id'          => "arsol_server_variation_region{$loop}",
            'name'        => "arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional overide)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class}",
            'desc_tip'    => true,
            'description' => __('Enter the server region override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => $region_value, // Fix: Use the retrieved value
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));

        woocommerce_wp_text_input(array(
            'id'          => "arsol_server_variation_image{$loop}",
            'name'        => "arsol_server_variation_image[{$loop}]",
            'label'       => __('Server image slug (optional overide)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class}",
            'desc_tip'    => true,
            'description' => __('Enter the server image override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => $image_value, // Fix: Use the retrieved value
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));
    }

    /**
     * Save custom fields for product variation
     */
    public function save_custom_variation_fields($variation_id, $i) {
        // Get the parent product
        $parent_product = wc_get_product($variation_id);
        
        // Check if this is a variable subscription
        if (!$parent_product || $parent_product->get_type() !== 'variable-subscription') {
            return;
        }

        // Check if arsol_server is enabled
       //$is_server_enabled = get_post_meta($variation_id, '_arsol_server', true) === 'yes';
        
        // Save the custom fields
        if ($is_server_enabled) {
            $region = isset($_POST['arsol_server_variation_region'][$i]) ? sanitize_text_field($_POST['arsol_server_variation_region'][$i]) : '';
            $image = isset($_POST['arsol_server_variation_image'][$i]) ? sanitize_text_field($_POST['arsol_server_variation_image'][$i]) : '';
            
            update_post_meta($variation_id, '_arsol_server_variation_region', $region);
            update_post_meta($variation_id, '_arsol_server_variation_image', $image);
        }
    }

 
}
