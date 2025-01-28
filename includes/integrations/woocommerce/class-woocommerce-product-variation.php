<?php

namespace Siya\Integrations\WooCommerce\Product;

use Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {
        // Remove old validation hooks
        add_action('woocommerce_before_variation_object_save', [$this, 'validate_variation_fields'], 5, 2);
        
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
        
        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_region{$loop}",
            'name'        => "_arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional overide)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class}",
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
            'label'       => __('Server image slug (optional overide)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class}",
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
        $fields = [
            'arsol_server_variation_region',
            'arsol_server_variation_image'
        ];

        // Get parent product ID
        $variation = wc_get_product($variation_id);
        if (!$variation) return;
        
        $parent_id = $variation->get_parent_id();
        $is_server_enabled = get_post_meta($parent_id, '_arsol_server', true) === 'yes';

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
    public function validate_variation_fields($variation, $i) {
        $parent = $variation->get_parent_id();
        if (!$parent || get_post_meta($parent, '_arsol_server', true) !== 'yes') {
            return true;
        }

        $has_errors = false;
        $pattern_fields = [
            'arsol_server_variation_region' => __('Server Region', 'woocommerce'),
            'arsol_server_variation_image' => __('Server Image', 'woocommerce')
        ];

        foreach ($pattern_fields as $field => $label) {
            $field_name = $field . '[' . $i . ']';
            $value = sanitize_text_field($_POST[$field_name] ?? '');
            
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                $variation->add_error(
                    sprintf(__('Variation %s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label)
                );
                $has_errors = true;
            }
        }

        return !$has_errors;
    }
}
