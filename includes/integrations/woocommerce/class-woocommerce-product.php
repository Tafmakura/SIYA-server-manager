<?php

namespace Siya\Integrations\WooCommerce;

use Siya\AdminSettings\Slugs;
use WC_Admin_Notices;

defined('ABSPATH') || exit;

class Product {
   
    protected $validation_errors = [];

    public function __construct() {
        // Basic hooks
        add_action('init', [$this, 'init']);
        
        // Validation and save hook - run before saving but after product init
        add_filter('woocommerce_admin_process_product_object', [$this, 'validate_and_save_fields'], 5);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Product type hooks
        add_filter('product_type_options', [$this, 'add_arsol_server_product_option']);
        add_action('save_post_product', [$this, 'save_arsol_server_option_fields'], 10, 3);
        
        // Server settings tab hooks
        add_filter('woocommerce_product_data_tabs', [$this, 'add_arsol_server_settings_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_arsol_server_settings_tab_content']);
    }

    public function add_arsol_server_product_option($product_type_options) {
        $product_type_options['arsol_server'] = [
            'id'            => 'arsol_server', // ID without underscore for WooCommerce show/hide
            'wrapper_class' => 'show_if_subscription show_if_variable-subscription',
            'label'         => __('Server', 'woocommerce'),
            'description'   => __('Enable this if the product is a subscription to a server', 'woocommerce'),
            'default'       => 'no'
        ];
        return $product_type_options;
    }

    public function save_arsol_server_option_fields($post_ID, $product, $update) {
        $is_arsol_server = isset($_POST['arsol_server']) ? 'yes' : 'no';
        update_post_meta($post_ID, '_arsol_server', $is_arsol_server); // Save with underscore prefix
    }

    public function add_arsol_server_settings_tab($tabs) {
        $tabs['arsol_server_settings'] = [
            'label'    => __('Server Settings', 'woocommerce'),
            'target'   => 'arsol_server_settings_data',
            'class'    => ['show_if_arsol_server'], // Fix underscore naming
            'priority' => 50,
        ];

        return $tabs;
    }

    public function add_arsol_server_settings_tab_content() {
        global $post;
        $product = wc_get_product($post->ID);
        $slugs = new Slugs();
        $enabled_server_types = (array) get_option('arsol_allowed_server_types', []);
        if (!in_array('sites_server', $enabled_server_types)) {
            $enabled_server_types[] = 'sites_server';
        }

        // Pass product object to template for using get_meta
        include plugin_dir_path(__FILE__) . '../../../ui/templates/admin/woocommerce/product-settings-server.php';
    }

    public function validate_and_save_fields($product) {
        // Get the post ID from the product object
        $post_id = $product->get_id();
        
        // Check if product has server option enabled using post data (not meta)
        if (!isset($_POST['_arsol_server']) || $_POST['_arsol_server'] !== 'yes') {
            return $product;
        }
    
        $has_errors = false;
    
        // Access the fields from the product object instead of $_POST
        $server_type = sanitize_text_field($product->get_meta('arsol_server_type'));
        $is_sites_server = $server_type === 'sites_server';
    
        // 1. Required Fields Validation
        $required_fields = [
            'arsol_server_type' => __('Server Type', 'woocommerce')
        ];
    
        // Add provider fields only if not a sites server
        if (!$is_sites_server) {
            $required_fields += [
                'arsol_server_provider_slug' => __('Server Provider', 'woocommerce'),
                'arsol_server_plan_group_slug' => __('Server Plan Group', 'woocommerce'),
                'arsol_server_plan_slug' => __('Server Plan', 'woocommerce')
            ];
        }
    
        // Validate required fields
        foreach ($required_fields as $field => $label) {
            $field_value = $product->get_meta($field);
            if (empty($field_value)) {
                WC_Admin_Notices::add_custom_notice(
                    'required_field_error',
                    sprintf(__('%s is required.', 'woocommerce'), $label)
                );
                $has_errors = true;
            }
        }
    
        // 2. Pattern Validation
        $pattern_fields = [
            'arsol_server_region' => __('Server Region', 'woocommerce'),
            'arsol_server_image' => __('Server Image', 'woocommerce')
        ];
    
        foreach ($pattern_fields as $field => $label) {
            $value = sanitize_text_field($product->get_meta($field));
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                WC_Admin_Notices::add_custom_notice(
                    'pattern_error',
                    sprintf(__('%s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label)
                );
                $has_errors = true;
            }
        }
    
        // 3. Length Validation
        $region = sanitize_text_field($product->get_meta('arsol_server_region'));
        if (strlen($region) > 50) {
            WC_Admin_Notices::add_custom_notice(
                'length_error',
                __('Server Region cannot exceed 50 characters.', 'woocommerce')
            );
            $has_errors = true;
        }
    
        // 4. Applications Validation
        if ($is_sites_server || $server_type === 'application_server') {
            $max_apps = absint($product->get_meta('_arsol_max_applications', true));
            if ($max_apps < 1) {
                die('here');
                WC_Admin_Notices::add_custom_notice('custom_error', __('Maximum Applications must be at least 1.', 'woocommerce'));
                $has_errors = true;
            }
        }
    
        // If there are any validation errors, return null to prevent saving
        if ($has_errors) {
            WC_Admin_Notices::add_custom_notice(
                'validation_failed',
                __('Validation failed: Please check the server settings.', 'woocommerce')
            );
            return null; // Prevent saving
        }
    
        // If validation passes, sanitize and save all fields
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($product->get_meta('arsol_server_provider_slug')),
            '_arsol_server_plan_group_slug' => sanitize_text_field($product->get_meta('arsol_server_plan_group_slug')),
            '_arsol_server_plan_slug' => sanitize_text_field($product->get_meta('arsol_server_plan_slug')),
            'arsol_server_manager_required' => $is_sites_server ? 'yes' : (isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'),
            '_arsol_sites_server' => $is_sites_server ? 'yes' : 'no',
            '_arsol_ecommerce_optimized' => $product->get_meta('_arsol_ecommerce_optimized', true) ? 'yes' : 'no',
        ];
    
        // Save max applications if needed
        if ($server_type === 'sites_server' || $server_type === 'application_server') {
            $fields['_arsol_max_applications'] = absint($product->get_meta('_arsol_max_applications', true));
        } else {
            // Delete max applications meta if server type doesn't match
            delete_post_meta($post_id, '_arsol_max_applications');
        }
    
        // Sanitize and save the server region and image
        $region = sanitize_text_field($product->get_meta('arsol_server_region'));
        $server_image = sanitize_text_field($product->get_meta('arsol_server_image'));
    
        if ($is_sites_server) {
            $fields['arsol_server_region'] = '';
            $fields['arsol_server_image'] = '';
        } else {
            $fields['arsol_server_region'] = $region;
            $fields['arsol_server_image'] = $server_image;
        }
    
        $fields['arsol_server_type'] = sanitize_text_field($server_type);
    
        // Save all meta data
        foreach ($fields as $meta_key => $value) {
            $product->update_meta_data($meta_key, $value);
        }
    
        // Save any additional meta data as needed
        $product->update_meta_data('_arsol_additional_server_groups', $product->get_meta('_arsol_additional_server_groups', true));
        $product->update_meta_data('arsol_server_groups', $product->get_meta('arsol_server_groups', true));
        $product->update_meta_data('_arsol_assigned_server_groups', $product->get_meta('_arsol_assigned_server_groups', true));
        $product->update_meta_data('_arsol_assigned_server_tags', $product->get_meta('_arsol_assigned_server_tags', true));
    
        // Save the product
        $product->save();
    
        return $product;
    }
    

}
