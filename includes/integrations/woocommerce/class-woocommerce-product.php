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
        
        // Change priority to 1 to run before other processes
        add_filter('woocommerce_admin_process_product_object', [$this, 'validate_and_save_fields'], 1);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Product type hooks
        add_filter('product_type_options', [$this, 'add_arsol_server_product_option']);
        
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

    public function validate_and_save_fields($product) {
        // Early validation
        if (!$this->validate_server_fields($product)) {
            WC_Admin_Notices::add_custom_notice(
                'validation_failed', 
                __('Server validation failed. Changes were not saved.', 'woocommerce')
            );
            // Return false to prevent saving
          //  wp_die(__('Validation failed. Please check the server settings.', 'woocommerce'));
            return false;
        }

        // Continue with save if validation passes
        $this->save_server_fields($product);
        return $product;
    }

    private function validate_server_fields($product) {
        // Get post ID from product object
        $post_id = $product->get_id();

        // Check multiple ways since WooCommerce can be inconsistent
        $is_server = false;
        
        // Check POST data
        if (isset($_POST['_arsol_server'])) {
            $is_server = $_POST['_arsol_server'] === 'yes';
        } else if (isset($_POST['arsol_server'])) {
            $is_server = $_POST['arsol_server'] === 'yes';
        }
        
        // Fallback to product meta if POST check fails
        if (!$is_server) {
            $is_server = $product->get_meta('_arsol_server') === 'yes';
        }

        // If not a server product, return early
        if (!$is_server) {
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
                WC_Admin_Notices::add_custom_notice('custom_error', sprintf(__('%s is required.', 'woocommerce'), $label));
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
                WC_Admin_Notices::add_custom_notice('custom_error', sprintf(__('%s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label));
                $has_errors = true;
            }
        }

        // 3. Length Validation
        $region = sanitize_text_field($_POST['arsol_server_region'] ?? '');
        if (strlen($region) > 50) {
            WC_Admin_Notices::add_custom_notice('custom_error', __('Server Region cannot exceed 50 characters.', 'woocommerce'));
            $has_errors = true;
        }

        // 4. Applications Validation.
        if ($is_sites_server || $server_type === 'application_server') {
            $max_apps = absint($_POST['_arsol_max_applications'] ?? 0);
            if ($max_apps < 1) {
                //die('here');
                WC_Admin_Notices::add_custom_notice('custom_error', __('Maximum Applications must be at least 1.', 'woocommerce'));
                $has_errors = true;
            }
        }

        if ($has_errors) {
            $this->validation_errors[] = __('Validation failed: Please check the server settings.', 'woocommerce');
            return false;
        }

        return true;
    }

    private function save_server_fields($product) {
        // Early return if there are validation errors
        if (!empty($this->validation_errors)) {
            return false;
        }

        // Save all fields if validation passes
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($_POST['arsol_server_provider_slug'] ?? ''),
            '_arsol_server_plan_group_slug' => sanitize_text_field($_POST['arsol_server_plan_group_slug'] ?? ''),
            '_arsol_server_plan_slug' => sanitize_text_field($_POST['arsol_server_plan_slug'] ?? ''),
            '_arsol_server_manager_required' => $is_sites_server ? 'yes' : (isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'),
            '_arsol_sites_server' => $is_sites_server ? 'yes' : 'no',
            '_arsol_ecommerce_optimized' => isset($_POST['_arsol_ecommerce_optimized']) ? 'yes' : 'no',
        ];

        // Only include max applications if server type is sites_server or application_server
        if ($server_type === 'sites_server' || $server_type === 'application_server') {
            $fields['_arsol_max_applications'] = absint($_POST['_arsol_max_applications'] ?? 0);
        } else {
            // Delete max applications meta if server type is not sites_server or application_server
            $product->delete_meta_data('_arsol_max_applications');
        }

        // Get existing values for region and image
        $existing_region = $product->get_meta('_arsol_server_region', true);
        $existing_image = $product->get_meta('_arsol_server_image', true);
        
        // Handle region and image fields
        $region = isset($_POST['arsol_server_region']) ? sanitize_text_field($_POST['arsol_server_region']) : $existing_region;
        $server_image = isset($_POST['arsol_server_image']) ? sanitize_text_field($_POST['arsol_server_image']) : $existing_image;

        // Only validate if fields are not empty and were modified
        if (!empty($region) && $region !== $existing_region) {
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                WC_Admin_Notices::add_custom_notice('custom_error', __('Region field can only contain letters, numbers, and hyphens.', 'woocommerce'));
                return false;
            }
            if (strlen($region) > 50) {
                WC_Admin_Notices::add_custom_notice('custom_error', __('Region field cannot exceed 50 characters.', 'woocommerce'));
                return false;
            }
        }

        // Set region and image values - only clear if Sites server is being enabled
        $was_sites_server = $product->get_meta('_arsol_sites_server', true) === 'yes';

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

        // Ensure Runcloud is saved as 'yes' if server type is 'sites_server' 
        if ($is_sites_server) {
            $product->update_meta_data('_arsol_server_manager_required', 'yes');
        }

        // Save all fields 
        foreach ($fields as $meta_key => $value) {
            $product->update_meta_data($meta_key, $value);
        }

        $additional_groups = isset($_POST['_arsol_additional_server_groups'])
            ? array_map('sanitize_text_field', $_POST['_arsol_additional_server_groups'])
            : [];
        $product->update_meta_data('_arsol_additional_server_groups', $additional_groups);

        $server_groups = isset($_POST['arsol_server_groups'])
            ? array_map('sanitize_text_field', $_POST['arsol_server_groups'])
            : [];
        $product->update_meta_data('arsol_server_groups', $server_groups);

        $assigned_server_groups = isset($_POST['_arsol_assigned_server_groups'])
            ? array_map('intval', $_POST['_arsol_assigned_server_groups'])
            : [];
        $product->update_meta_data('_arsol_assigned_server_groups', $assigned_server_groups);

        // Save assigned server tags
        $assigned_server_tags = isset($_POST['_arsol_assigned_server_tags'])
            ? array_map('intval', $_POST['_arsol_assigned_server_tags'])
            : [];
        $product->update_meta_data('_arsol_assigned_server_tags', $assigned_server_tags);

        $product->save();

        return $product;
    }

}
