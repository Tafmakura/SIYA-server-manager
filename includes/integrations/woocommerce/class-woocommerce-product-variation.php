<?php

namespace Siya\Integrations\WooCommerce\Product;

use WC_Admin_Notices;

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

        // Check if arsol_server is enabled on parent and get server type
        $parent = wc_get_product($variation_object->get_parent_id());
        $is_server_enabled = $parent ? $parent->get_meta('_arsol_server') === 'yes' : false;
        $server_type = $parent ? $parent->get_meta('_arsol_server_type') : '';
        
        // Hide if server is not enabled, or if it's enabled and is a sites server !!!!
        $hidden_class_on_load = (!$is_server_enabled || ($is_server_enabled && $server_type === 'sites_server')) ? 'hidden' : '';

        woocommerce_wp_text_input([
            'id'          => "arsol_server_variation_region{$loop}",
            'name'        => "arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional override)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_server {$hidden_class_on_load}",
            'desc_tip'    => true,
            'description' => __('Enter the server region for this variation. Only letters, numbers and hyphens allowed (max 50 characters).', 'woocommerce'),
            'value'       => $variation_object->get_meta('_arsol_server_variation_region'),
            'custom_attributes' => [
            'pattern' => '^[a-zA-Z0-9-]+$',
            'maxlength' => '30',
            'title'   => 'Only letters, numbers and hyphens allowed',
            'onkeyup' => "this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')"
            ]
        ]);

        woocommerce_wp_text_input([
            'id'          => "arsol_server_variation_image{$loop}",
            'name'        => "arsol_server_variation_image[{$loop}]",
            'label'       => __('Server image slug (optional override)', 'woocommerce'), 
            'wrapper_class' => "form-row form-row-last show_if_arsol_server hide_if_arsol_sites_server {$hidden_class_on_load}",
            'desc_tip'    => true,
            'description' => __('Enter the server image for this variation. Only letters, numbers and hyphens allowed (max 50 characters).', 'woocommerce'),
            'value'       => $variation_object->get_meta('_arsol_server_variation_image'),
            'custom_attributes' => [
            'pattern' => '^[a-zA-Z0-9-]+$',
            'maxlength' => '30',
            'title'   => 'Only letters, numbers and hyphens allowed',
            'onkeyup' => "this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '')"
            ]
        ]);


        woocommerce_wp_text_input([
            'id'          => "arsol_server_variation_max_applications{$loop}",
            'name'        => "arsol_server_variation_max_applications[{$loop}]",
            'label'       => __('Server max applications (optional override)', 'woocommerce'),
            'wrapper_class' => "form-row form-row-first show_if_arsol_application_server show_if_arsol_sites_server {$hidden_class_on_load}",
            'desc_tip'    => true, 
            'description' => __('Enter the maximum applications for this variation. Numbers only (0-999) or leave empty to use main subscription default.', 'woocommerce'),
            'value'       => $variation_object->get_meta('_arsol_server_variation_max_applications', true),
            'type'        => 'number',
            'custom_attributes' => [
            'min' => '0',
            'max' => '999',
            'step' => '1',
            'style' => 'width: 3em; text-align: center;',
            'onkeyup' => "if(this.value !== '') { this.value = this.value.replace(/[^0-9]/g, '').slice(0, 3); }"
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
                    WC_Admin_Notices::add_custom_notice(
                        'region_length_error',
                        __('Variation server region cannot exceed 15 characters.', 'woocommerce')
                    );
                    $has_errors = true;
                }
                if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                    WC_Admin_Notices::add_custom_notice(
                        'region_pattern_error', 
                        __('Invalid variation server region. Only letters, numbers, and hyphens allowed.', 'woocommerce')
                    );
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

 


                if (strlen($image) > 15) {
                    WC_Admin_Notices::add_custom_notice(
                        'image_length_error',
                        __('Variation server image cannot exceed 15 characters.', 'woocommerce')
                    );
                    $has_errors = true;
                }
                if (!preg_match('/^[a-zA-Z0-9-]+$/', $image)) {
                    WC_Admin_Notices::add_custom_notice(
                        'image_pattern_error',
                        __('Invalid variation server image. Only letters, numbers, and hyphens allowed.', 'woocommerce')
                    );
                    $has_errors = true;
                }
            }

            if (!$has_errors) {
                $variation->update_meta_data('_arsol_server_variation_image', $image);
            }
        }

        // Handle max applications with support for empty values
        if (isset($_POST['arsol_server_variation_max_applications'][$loop])) {
            $max_applications = $_POST['arsol_server_variation_max_applications'][$loop];
            
            // Allow empty string or "0"
            if ($max_applications === '' || $max_applications === '0') {
                $variation->update_meta_data('_arsol_server_variation_max_applications', $max_applications);
            } else {
                $max_applications = absint($max_applications);
                if ($max_applications <= 99) {
                    $variation->update_meta_data('_arsol_server_variation_max_applications', $max_applications);
                } else {
                    WC_Admin_Notices::add_custom_notice(
                        'max_applications_error',
                        __('Maximum applications cannot exceed 999.', 'woocommerce')
                    );
                    $has_errors = true;
                }
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
        $variation_data['arsol_server_variation_max_applications'] = $variation->get_meta('_arsol_server_variation_max_applications');
        
        return $variation_data;
    }
}
