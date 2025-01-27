<?php

namespace Siya\Integrations\WooCommerce\Product;

use Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {
        // Add variation specific fields only for subscription products
        add_action('woocommerce_variation_options', [$this, 'add_variation_server_option'], 10, 3);
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_fields'], 10, 2);
        add_action('admin_footer', [$this, 'add_variation_scripts']);
    }

    /**
     * Check if product is a subscription
     */
    private function is_subscription_product($product_id) {
        $product = wc_get_product($product_id);
        return $product && (
            $product->is_type('subscription') || 
            $product->is_type('variable-subscription') || 
            get_post_meta($product_id, '_arsol_server', true) === 'yes'
        );
    }

    /**
     * Add server checkbox to variation options
     */
    public function add_variation_server_option($loop, $variation_data, $variation) {
        if (!$this->is_subscription_product($variation->post_parent)) {
            return;
        }

        woocommerce_wp_checkbox(array(
            'id'            => "_arsol_server_variation_option{$loop}",
            'name'          => "_arsol_server_variation_option[{$loop}]",
            'label'         => __('Server', 'woocommerce'),
            'value'         => 'yes',
            'cbvalue'       => 'yes',
            'custom_attributes' => array(
                'disabled' => 'disabled',
                'checked'  => 'checked'
            )
        ));
    }

    /**
     * Add custom fields to product variation
     */
    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        // Only show fields for subscription products
        if (!$this->is_subscription_product($variation->post_parent)) {
            return;
        }

        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_region{$loop}",
            'name'        => "_arsol_server_variation_region[{$loop}]",
            'label'       => __('Override server region (optional)', 'woocommerce'),
            'desc_tip'    => true,
            'description' => __('Enter the server region override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, '_arsol_server_variation_region', true),
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));

        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_image{$loop}",
            'name'        => "_arsol_server_variation_image[{$loop}]",
            'label'       => __('Override server image (optional)', 'woocommerce'),
            'desc_tip'    => true,
            'description' => __('Enter the server image override. Only letters, numbers and hyphens allowed.', 'woocommerce'),
            'value'       => get_post_meta($variation->ID, '_arsol_server_variation_image', true),
            'custom_attributes' => array(
                'pattern' => '^[a-zA-Z0-9-]+$',
                'title'   => 'Only letters, numbers and hyphens allowed'
            )
        ));
    }

    /**
     * Save custom fields for product variation
     */
    public function save_custom_variation_fields($variation_id, $loop) {
        $fields = [
            '_arsol_server_variation_region',
            '_arsol_server_variation_image'
        ];

        foreach ($fields as $field) {
            $value = isset($_POST[$field][$loop]) ? sanitize_text_field($_POST[$field][$loop]) : '';
            
            // Only validate if value is not empty
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                $value = ''; // Clear invalid values
            }

            update_post_meta($variation_id, $field, $value);
        }
    }

    /**
     * Add JavaScript validation for variation fields
     */
    public function add_variation_scripts() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('input', '[id^="_arsol_server_variation_region"], [id^="_arsol_server_variation_image"]', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');
            });
        });
        </script>
        <?php
    }
}
