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
        add_filter('woocommerce_admin_process_product_object', [$this, 'validate_and_save_fields'], 75);
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
            'class'    => ['show_if_arsol_server'],
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
        // Only save if server is enabled
        if (!isset($_POST['arsol_server']) || $_POST['arsol_server'] !== 'yes') {
            return $product;
        }

        // Basic fields that are always saved
        $fields = [
            '_arsol_server' => 'yes',
            '_arsol_server_type' => sanitize_text_field($_POST['arsol_server_type'] ?? ''),
            '_arsol_server_region' => sanitize_text_field($_POST['arsol_server_region'] ?? ''),
            '_arsol_server_image' => sanitize_text_field($_POST['arsol_server_image'] ?? ''),
            '_arsol_server_manager_required' => isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'
        ];

        // Add server type specific fields
        $is_sites_server = ($fields['_arsol_server_type'] === 'sites_server');
        
        if ($is_sites_server) {
            $fields['_arsol_server_provider_slug'] = get_option('siya_wp_server_provider');
            $fields['_arsol_server_plan_group_slug'] = get_option('siya_wp_server_group');
            $fields['_arsol_ecommerce_optimized'] = isset($_POST['arsol_ecommerce_optimized']) ? 'yes' : 'no';
        } else {
            $fields['_arsol_server_provider_slug'] = sanitize_text_field($_POST['arsol_server_provider_slug'] ?? '');
            $fields['_arsol_server_plan_group_slug'] = sanitize_text_field($_POST['arsol_server_plan_group_slug'] ?? '');
        }

        // Always save plan slug
        $fields['_arsol_server_plan_slug'] = sanitize_text_field($_POST['arsol_server_plan_slug'] ?? '');

        // Handle max applications
        if ($is_sites_server || $fields['_arsol_server_type'] === 'application_server') {
            $fields['_arsol_max_applications'] = absint($_POST['arsol_max_applications'] ?? 0);
        }

        // Save all collected fields
        foreach ($fields as $key => $value) {
            $product->update_meta_data($key, $value);
        }

        // Save taxonomies
        $product->update_meta_data('_arsol_assigned_server_groups', 
            !empty($_POST['arsol_assigned_server_groups']) ? array_map('intval', $_POST['arsol_assigned_server_groups']) : []
        );
        $product->update_meta_data('_arsol_assigned_server_tags',
            !empty($_POST['arsol_assigned_server_tags']) ? array_map('intval', $_POST['arsol_assigned_server_tags']) : []
        );

        // Set required WooCommerce settings
        $product->set_sold_individually(true);
        if ($is_sites_server) {
            $product->update_meta_data('_subscription_limit', 'active');
        }

        // Save all changes
        $product->save();

        return $product;
    }

}
