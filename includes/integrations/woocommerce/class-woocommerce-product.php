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
        // Add debugging
        error_log('POST Data: ' . print_r($_POST, true));
        error_log('Is Server Check: ' . isset($_POST['arsol_server']));
        error_log('Server Value: ' . $_POST['arsol_server'] ?? 'not set');

        // Only save if server is enabled
        if (!isset($_POST['arsol_server']) || $_POST['arsol_server'] !== 'yes') {
            error_log('Early return - server not enabled');
            return $product;
        }

        // Debug fields before save
        $fields = [
            '_arsol_server' => 'yes',
            '_arsol_server_type' => sanitize_text_field($_POST['arsol_server_type'] ?? ''),
            '_arsol_server_region' => sanitize_text_field($_POST['arsol_server_region'] ?? ''),
            '_arsol_server_image' => sanitize_text_field($_POST['arsol_server_image'] ?? ''),
            '_arsol_server_manager_required' => isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'
        ];

        error_log('Fields to save: ' . print_r($fields, true));

        // Save fields one by one with verification
        foreach ($fields as $key => $value) {
            $product->update_meta_data($key, $value);
            error_log("Saving {$key}: {$value}");
        }

        // Verify save
        $product->save();
        error_log('Post save verification - Product ID: ' . $product->get_id());
        error_log('Saved region: ' . $product->get_meta('_arsol_server_region'));
        error_log('Saved image: ' . $product->get_meta('_arsol_server_image'));

        return $product;
    }

}
