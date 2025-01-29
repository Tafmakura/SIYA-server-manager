<?php

namespace Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation {
    
    public function __construct() {
        // Display fields
        add_action('woocommerce_variation_options', [$this, 'add_custom_fields'], 10, 3);
        
        // Save fields
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_fields'], 10, 2);
        
        // Load variation data
        add_filter('woocommerce_available_variation', [$this, 'load_variation_fields'], 10, 3);
    }

    public function add_custom_fields($loop, $variation_data, $variation) {
        $variation_object = wc_get_product($variation->ID);
        if (!$variation_object) return;

        error_log('variation_object: ' . print_r($variation_object, true));

        // Check if arsol_server is enabled on parent
        $parent = wc_get_product($variation_object->get_parent_id());
        $is_server_enabled = $parent ? $parent->get_meta('_arsol_server') === 'yes' : false;
        $hidden_class = $is_server_enabled ? '' : 'hidden';

        woocommerce_wp_text_input([
            'id'          => "arsol_server_variation_region{$loop}",
            'name'        => "arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional override)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class}",
            'desc_tip'    => true,
            'description' => __('Enter the server region override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => $variation_object->get_meta('_arsol_server_variation_region'),
            'custom_attributes' => [
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            ]
        ]);

        woocommerce_wp_text_input([
            'id'          => "arsol_server_variation_image{$loop}",
            'name'        => "arsol_server_variation_image[{$loop}]",
            'label'       => __('Server image slug (optional override)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-last show_if_arsol_server {$hidden_class}",
            'desc_tip'    => true,
            'description' => __('Enter the server image override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => $variation_object->get_meta('_arsol_server_variation_image'),
            'custom_attributes' => [
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            ]
        ]);
    }

    public function save_custom_fields($variation_id, $loop) {
        $variation = wc_get_product($variation_id);
        if (!$variation) return;

        $has_errors = false;

        // Validate and save region
        if (isset($_POST['arsol_server_variation_region'][$loop])) {
            $region = sanitize_text_field($_POST['arsol_server_variation_region'][$loop]);
            
            if (!empty($region)) {
                if (strlen($region) > 15) {
                    wc_add_notice(__('Server region cannot exceed 15 characters.', 'woocommerce'), 'error');
                    $has_errors = true;
                }
                if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                    wc_add_notice(__('Invalid server region. Only letters, numbers, and hyphens allowed.', 'woocommerce'), 'error');
                    $has_errors = true;
                }
            }

            if (!$has_errors) {
                $variation->update_meta_data('_arsol_server_variation_region', $region);
            }
        }

        // Validate and save image
        if (isset($_POST['arsol_server_variation_image'][$loop])) {
            $image = sanitize_text_field($_POST['arsol_server_variation_image'][$loop]);
            
            if (!empty($image)) {

                die('You hit the right hook!');


                if (strlen($image) > 15) {
                    wc_add_notice(__('Server image cannot exceed 15 characters.', 'woocommerce'), 'error');
                    $has_errors = true;
                }
                if (!preg_match('/^[a-zA-Z0-9-]+$/', $image)) {
                wc_add_notice(__('Invalid server image. Only letters, numbers, and hyphens allowed.', 'woocommerce'), 'error');
                $has_errors = true;
                }
            }

            if (!$has_errors) {
                $variation->update_meta_data('_arsol_server_variation_image', $image);
            }
        }

        if (!$has_errors) {
            $variation->save();
        }
    }

    public function load_variation_fields($variation_data, $product, $variation) {
        // Add custom fields to variation data with arsol prefix
        $variation_data['arsol_server_variation_region'] = $variation->get_meta('_arsol_server_variation_region');
        $variation_data['arsol_server_variation_image'] = $variation->get_meta('_arsol_server_variation_image');
        
        return $variation_data;
    }
}
