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
        add_filter('woocommerce_product_tabs', [$this, 'add_custom_product_tab']);
        add_action('woocommerce_product_tab_panels', [$this, 'display_custom_product_tab_panel']);
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

    public function add_custom_product_tab($tabs) {
        $tabs['custom_tab'] = array(
            'title'    => __('Custom Tab', 'woocommerce'),
            'priority' => 50,
            'callback' => [$this, 'display_custom_product_tab_panel']
        );
    
        return $tabs;
    }

    public function display_custom_product_tab_panel() {
        echo '<h2>' . __('Custom Tab', 'woocommerce') . '</h2>';
        echo '<p>' . __('Here\'s some custom content for your new tab.', 'woocommerce') . '</p>';
    }
}


