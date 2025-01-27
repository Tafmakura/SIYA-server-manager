<?php

namespace Siya\Integrations\WooCommerce\Product;

use Siya\Integrations\WooCommerce\Product;

defined('ABSPATH') || exit;

class Variation extends Product {
   
    public function __construct() {

        

        // Add variation specific fields
        add_action('woocommerce_variation_options_pricing', [$this, 'add_custom_variation_fields'], 10, 3);
        
        // Save variation fields
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_fields'], 10, 2);

        // Add client-side validation
        add_action('admin_footer', [$this, 'add_variation_scripts']);
    }

    /**
     * Add custom fields to product variation
     */
    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        // Get the parent product
        $parent_product = wc_get_product($variation->post_parent);
        
        // Check if this is a variable subscription
        if (!$parent_product || $parent_product->get_type() !== 'variable-subscription') {
            return;
        }

        woocommerce_wp_text_input(array(
            'id'          => "_arsol_server_variation_region{$loop}",
            'name'        => "_arsol_server_variation_region[{$loop}]",
            'label'       => __('Server region slug (optional overide)', 'woocommerce'),
            'wrapper_class' => 'form-row form-row-first arsol-server-field',
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
            'label'       => __('Server image slug (optional overide)', 'woocommerce'),
            'wrapper_class' => 'form-row form-row-first arsol-server-field',
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

        // Get parent product ID
        $variation = wc_get_product($variation_id);
        if (!$variation) return;
        
        $parent_id = $variation->get_parent_id();
        $is_server_enabled = get_post_meta($parent_id, '_arsol_server', true) === 'yes';

        if (!$is_server_enabled) {
            // Delete meta keys if _arsol_server is not checked
            foreach ($fields as $field) {
                delete_post_meta($variation_id, $field);
            }
            return;
        }

        // Only save fields if _arsol_server is checked
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
            // Function to toggle visibility of server fields
            function toggleServerFields() {
                var isChecked = $('#_arsol_server').is(':checked');
                $('.arsol-server-field').toggle(isChecked);
            }

            // Run on page load
            toggleServerFields();

            // Run when checkbox changes
            $('#_arsol_server').change(function() {
                toggleServerFields();
            });

            // Input validation
            $(document).on('input', '[id^="_arsol_server_variation_region"], [id^="_arsol_server_variation_image"]', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');
            });
        });
        </script>
        <?php
    }
}
