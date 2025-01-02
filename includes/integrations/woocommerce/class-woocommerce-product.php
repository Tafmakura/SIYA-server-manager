<?php

namespace Siya\Integrations\WooCommerce;

defined('ABSPATH') || exit;

class Product {
   
    public function __construct() {
        add_action('init', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add custom product type options
        add_filter('product_type_options', [$this, 'add_arsol_server_product_option']);
        add_action('woocommerce_process_product_meta_simple', [$this, 'save_arsol_server_option_fields']);
        add_action('woocommerce_process_product_meta_variable', [$this, 'save_arsol_server_option_fields']);
        
        // Add custom tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_custom_product_admin_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_custom_product_admin_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_product_admin_tab_content']);
        
        // Enqueue custom script for admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_arsol_server_product_option($product_type_options) {
        // Add your custom product type options here
        $product_type_options['arsol_server'] = array(
            'id'            => '_arsol_server',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label'         => __('ARSOL Server', 'woocommerce'),
            'description'   => __('', 'woocommerce'),
            'default'       => 'no'
        );
    
        return $product_type_options;
    }

    public function save_arsol_server_option_fields($post_id) {
        $is_arsol_server = isset($_POST['_arsol_server']) ? 'yes' : 'no';
        update_post_meta($post_id, '_arsol_server', $is_arsol_server);
    }

    public function add_custom_product_admin_tab($tabs) {
        $tabs['custom_tab'] = array(
            'label'    => __('Custom Tab', 'woocommerce'),
            'target'   => 'custom_product_data',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 50,
        );

        return $tabs;
    }

    public function add_custom_product_admin_tab_content() {
        echo '<div id="custom_product_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id'          => '_custom_field',
            'label'       => __('Custom Field', 'woocommerce'),
            'description' => __('Enter custom field data here.', 'woocommerce'),
            'desc_tip'    => 'true',
        ));
        echo '</div>';
        echo '</div>';
    }

    public function save_custom_product_admin_tab_content($post_id) {
        $custom_field = isset($_POST['_custom_field']) ? sanitize_text_field($_POST['_custom_field']) : '';
        update_post_meta($post_id, '_custom_field', $custom_field);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_script('custom-admin-js', plugins_url('/custom-admin.js', __FILE__), array('jquery'), '1.0.0', true);
    }
}

