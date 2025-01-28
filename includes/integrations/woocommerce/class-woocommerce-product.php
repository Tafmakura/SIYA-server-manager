<?php

namespace Siya\Integrations\WooCommerce;

use Siya\AdminSettings\Slugs;

defined('ABSPATH') || exit;

class Product {
   
    protected $validation_errors = [];

    public function __construct() {
        // Basic hooks
        add_action('init', [$this, 'init']);
        
        // Validation hook - run before saving but after product init
        add_filter('woocommerce_admin_process_product_object', [$this, 'validate_fields'], 5);
        
        // Save hooks (should run after validation)
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_fields'], 10);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta'], 15);
        add_action('woocommerce_process_product_meta', [$this, 'save_arsol_server_settings_tab_content'], 20);
        
        add_action('admin_notices', [$this, 'display_validation_errors']);
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
        $slugs = new Slugs();
        $enabled_server_types = (array) get_option('arsol_allowed_server_types', []);
        if (!in_array('sites_server', $enabled_server_types)) {
            $enabled_server_types[] = 'sites_server';
        }
        include plugin_dir_path(__FILE__) . '../../../ui/templates/admin/woocommerce/product-settings-server.php';
    }

    public function save_arsol_server_settings_tab_content($post_id) {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }

        // Force Runcloud integration if Sites server is enabled
        $is_sites_server = isset($_POST['_arsol_sites_server']);
        
        // Get server type
        $server_type = sanitize_text_field($_POST['arsol_server_type'] ?? '');
        
        // Define and sanitize basic fields
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($_POST['arsol_server_provider_slug'] ?? ''),
            '_arsol_server_plan_group_slug' => sanitize_text_field($_POST['arsol_server_plan_group_slug'] ?? ''),
            '_arsol_server_plan_slug' => sanitize_text_field($_POST['arsol_server_plan_slug'] ?? ''),
            'arsol_server_manager_required' => $is_sites_server ? 'yes' : (isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'),
            '_arsol_sites_server' => $is_sites_server ? 'yes' : 'no',
            '_arsol_ecommerce_optimized' => isset($_POST['_arsol_ecommerce_optimized']) ? 'yes' : 'no',
        ];

        // Only include max applications if server type is sites_server or application_server
        if ($server_type === 'sites_server' || $server_type === 'application_server') {
            $fields['_arsol_max_applications'] = absint($_POST['_arsol_max_applications'] ?? 0);
        } else {
            // Delete max applications meta if server type is not sites_server or application_server
            delete_post_meta($post_id, '_arsol_max_applications');
        }

        // Get existing values for region and image
        $existing_region = get_post_meta($post_id, '_arsol_server_region', true);
        $existing_image = get_post_meta($post_id, '_arsol_server_image', true);
        
        // Handle region and image fields
        $region = isset($_POST['arsol_server_region']) ? sanitize_text_field($_POST['arsol_server_region']) : $existing_region;
        $server_image = isset($_POST['arsol_server_image']) ? sanitize_text_field($_POST['arsol_server_image']) : $existing_image;

        // Only validate if fields are not empty and were modified
        if (!empty($region) && $region !== $existing_region) {
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                wc_add_notice(__('Region field can only contain letters, numbers, and hyphens.', 'woocommerce'), 'error');
                return;
            }
            if (strlen($region) > 50) {
                wc_add_notice(__('Region field cannot exceed 50 characters.', 'woocommerce'), 'error');
                return;
            }
        }

        // Set region and image values - only clear if Sites server is being enabled
        $was_sites_server = get_post_meta($post_id, '_arsol_sites_server', true) === 'yes';

        if ($is_sites_server && !$was_sites_server) {
            // Only clear values when transitioning to Sites server
            $fields['arsol_server_region'] = '';
            $fields['arsol_server_image'] = '';
        } else {
            // Keep existing or updated values
            $fields['arsol_server_region'] = $region;
            $fields['arsol_server_image'] = $server_image;
        }

        $fields['arsol_server_type'] = sanitize_text_field($_POST['arsol_server_type'] ?? '');

        // Save all fields
        foreach ($fields as $meta_key => $value) {
            update_post_meta($post_id, $meta_key, $value);
        }

        $additional_groups = isset($_POST['_arsol_additional_server_groups'])
            ? array_map('sanitize_text_field', $_POST['_arsol_additional_server_groups'])
            : [];
        update_post_meta($post_id, '_arsol_additional_server_groups', $additional_groups);

        $server_groups = isset($_POST['arsol_server_groups'])
            ? array_map('sanitize_text_field', $_POST['arsol_server_groups'])
            : [];
        update_post_meta($post_id, 'arsol_server_groups', $server_groups);

        $assigned_server_groups = isset($_POST['_arsol_assigned_server_groups'])
            ? array_map('intval', $_POST['_arsol_assigned_server_groups'])
            : [];
        update_post_meta($post_id, '_arsol_assigned_server_groups', $assigned_server_groups);

        // Save assigned server tags
        $assigned_server_tags = isset($_POST['_arsol_assigned_server_tags'])
            ? array_map('intval', $_POST['_arsol_assigned_server_tags'])
            : [];
        update_post_meta($post_id, '_arsol_assigned_server_tags', $assigned_server_tags);
    }

  
    /**
     * Save product meta data
     */
    public function save_product_meta($post_id) {
        // Check nonce for security
        if (!isset($_POST['siya_product_nonce']) || !wp_verify_nonce($_POST['siya_product_nonce'], 'save_siya_product')) {
            return;
        }

        // Validate and sanitize input
        $provider = isset($_POST['arsol_server_provider_slug']) ? sanitize_text_field($_POST['arsol_server_provider_slug']) : '';
        $group_slug = isset($_POST['arsol_server_plan_group_slug']) ? sanitize_text_field($_POST['arsol_server_plan_group_slug']) : '';
        $plan_slug = isset($_POST['arsol_server_plan_slug']) ? sanitize_text_field($_POST['arsol_server_plan_slug']) : '';

        // Perform validation
        if (empty($provider) || empty($group_slug) || empty($plan_slug)) {
            wc_add_notice(__('Please fill in all required fields: Server provider, Server group, and Server plan.', 'siya'), 'error');
            return;
        }

        // Save validated data
        update_post_meta($post_id, '_arsol_server_provider_slug', $provider);
        update_post_meta($post_id, '_arsol_server_plan_group_slug', $group_slug);
        update_post_meta($post_id, '_arsol_server_plan_slug', $plan_slug);
    }

    /**
     * Single method to handle all field validation
     */
    public function validate_fields($product) {
        // Get post ID from product object
        $post_id = $product->get_id();
        
        // Check if product has server option enabled using post data (not meta)
        if (!isset($_POST['_arsol_server']) || $_POST['_arsol_server'] !== 'yes') {
            return $product;
        }

        $has_errors = false;
        $server_type = sanitize_text_field($_POST['arsol_server_type'] ?? '');
        $is_sites_server = $server_type === 'sites_server';

        // 1. Required Fields Validation
        $required_fields = [
            'arsol_server_type' => __('Server Type', 'woocommerce')
        ];

        // Add provider fields only if not sites server
        if (!$is_sites_server) {
            $required_fields += [
                'arsol_server_provider_slug' => __('Server Provider', 'woocommerce'),
                'arsol_server_plan_group_slug' => __('Server Plan Group', 'woocommerce'),
                'arsol_server_plan_slug' => __('Server Plan', 'woocommerce')
            ];
        }

        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                wc_add_notice(sprintf(__('%s is required.', 'woocommerce'), $label), 'error');
                $has_errors = true;
            }
        }

        // 2. Pattern Validation
        $pattern_fields = [
            'arsol_server_region' => __('Server Region', 'woocommerce'),
            'arsol_server_image' => __('Server Image', 'woocommerce')
        ];

        foreach ($pattern_fields as $field => $label) {
            $value = sanitize_text_field($_POST[$field] ?? '');
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                wc_add_notice(sprintf(__('%s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label), 'error');
                $has_errors = true;
            }
        }

        // 3. Length Validation
        $region = sanitize_text_field($_POST['arsol_server_region'] ?? '');
        if (strlen($region) > 50) {
            wc_add_notice(__('Server Region cannot exceed 50 characters.', 'woocommerce'), 'error');
            $has_errors = true;
        }

        // 4. Applications Validation
        if ($is_sites_server || $server_type === 'application_server') {
            $max_apps = absint($_POST['_arsol_max_applications'] ?? 0);
            if ($max_apps < 1) {
                wc_add_notice(__('Maximum applications must be at least 1.', 'woocommerce'), 'error');
                $has_errors = true;
            }
        }

        if ($has_errors) {
            // Add error to product object instead of redirecting
            $product->add_error('Validation failed: Please check the server settings.');
            return false;
        }

        return $product;
    }

    public function display_validation_errors() {
        // This can be used if you wish to display custom validation errors via an admin notice
    }
}
