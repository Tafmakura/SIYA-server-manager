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
        add_filter('product_type_options', [$this, 'add_e_visa_product_option']);
        add_action( 'woocommerce_process_product_meta_simple', [$this, 'save_evisa_option_fields']  );
        add_action( 'woocommerce_process_product_meta_variable', [$this, 'save_evisa_option_fields']  );
    }

    public function get_product($product_id) {
        return wc_get_product($product_id);
    }

    public function add_e_visa_product_option($product_type_options) {
        // Add your custom product type options here
        $product_type_options['evisa'] = array(
            'id'            => '_evisa',
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label'         => __( 'eVisa', 'woocommerce' ),
            'description'   => __( '', 'woocommerce' ),
            'default'       => 'no'
        );
    
        return $product_type_options;

    }

    public function save_evisa_option_fields( $post_id ) {
    $is_e_visa = isset( $_POST['_evisa'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_evisa', $is_e_visa );
}
}






