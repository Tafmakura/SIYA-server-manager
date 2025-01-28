<?php

namespace Siya\Integrations\WooCommerce\Product;

use Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {

        

        // Add variation specific fields
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        
        // Save variation fields
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_fields'], 10, 2);

        // Remove client-side validation
        // add_action('admin_footer', [$this, 'add_variation_scripts']);
        
        // Add WooCommerce validation
        add_filter('woocommerce_variation_is_valid', [$this, 'validate_variation_fields'], 10, 2);
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
        
        woocommerce_wp_text_input(array(
            'id'          => "arsol_server_variation_region{$loop}",
            'name'        => "arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional overide)', 'woocommerce'),
            'wrapper_class' => 'form-row form-row-first show_if_arsol_server hidden', // Added hidden class
            'desc_tip'    => true,
            'description' => __('Enter the server region override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, 'arsol_server_variation_region', true),
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));

        woocommerce_wp_text_input(array(
            'id'          => "arsol_server_variation_image{$loop}",
            'name'        => "arsol_server_variation_image[{$loop}]",
            'label'       => __('Server image slug (optional overide)', 'woocommerce'),
            'wrapper_class' => 'form-row form-row-first show_if_arsol_server hidden', // Added hidden class
            'desc_tip'    => true,
            'description' => __('Enter the server image override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, 'arsol_server_variation_image', true),
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
        $fields = [
            'arsol_server_variation_region',
            'arsol_server_variation_image'
        ];

        // Get parent product ID
        $variation = wc_get_product($variation_id);
        if (!$variation) return;
        
        $parent_id = $variation->get_parent_id();
        $is_server_enabled = get_post_meta($parent_id, 'arsol_server', true) === 'yes';

        if (!$is_server_enabled) {
            // Delete meta keys if arsol_server is not checked
            foreach ($fields as $field) {
                delete_post_meta($variation_id, $field);
            }
            return;
        }

        // Only save fields if arsol_server is checked
        foreach ($fields as $field) {
            $value = isset($_POST[$field][$loop]) ? sanitize_text_field($_POST[$field][$loop]) : '';
            
            // Only validate if value is not empty
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                $value = ''; // Clear invalid values
            }

            update_post_meta($variation_id, $field, $value);
        }
    }

    /**
     * Add WooCommerce validation for variation fields
     */
    public function validate_variation_fields($valid, $variation_id) {
        // Pattern validation for region and image fields
        $pattern_fields = [
            'arsol_server_variation_region' => __('Server Region', 'woocommerce'),
            'arsol_server_variation_image' => __('Server Image', 'woocommerce'),
        ];

        foreach ($pattern_fields as $field => $label) {
            $value = get_post_meta($variation_id, $field, true);
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                wc_add_notice(sprintf(__('Variation %s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label), 'error');
                $valid = false;
            }
        }

        return $valid;
    }
}
