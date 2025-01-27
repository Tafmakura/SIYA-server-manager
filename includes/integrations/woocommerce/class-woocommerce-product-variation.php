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

        // Add AJAX handler
        add_action('wp_ajax_toggle_variation_fields', [$this, 'ajax_toggle_variation_fields']);
    }

    /**
     * AJAX handler for toggling variation fields
     */
    public function ajax_toggle_variation_fields() {
        check_ajax_referer('woocommerce-variation-fields', 'security');
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $loop = isset($_POST['loop']) ? absint($_POST['loop']) : 0;
        
        if (!$product_id || !$variation_id) {
            wp_send_json_error();
        }

        $product = wc_get_product($product_id);
        $is_server = get_post_meta($product_id, '_arsol_server', true) === 'yes';
        
        if ($product && $product->get_type() === 'variable-subscription' && $is_server) {
            ob_start();
            $this->render_variation_fields($loop, [], get_post($variation_id));
            $html = ob_get_clean();
            wp_send_json_success(['html' => $html]);
        } else {
            wp_send_json_success(['html' => '']);
        }
    }

    /**
     * Render variation fields
     */
    private function render_variation_fields($loop, $variation_data, $variation) {
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

    public function add_custom_variation_fields($loop, $variation_data, $variation) {
        echo '<div class="arsol-variation-fields" data-loop="' . esc_attr($loop) . '" data-variation-id="' . esc_attr($variation->ID) . '"></div>';
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
            // Function to update variation fields
            function updateVariationFields() {
                var product_id = $('#post_ID').val();
                var is_server = $('#_arsol_server').is(':checked');
                
                $('.arsol-variation-fields').each(function() {
                    var $container = $(this);
                    var variation_id = $container.data('variation-id');
                    var loop = $container.data('loop');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'toggle_variation_fields',
                            security: '<?php echo wp_create_nonce("woocommerce-variation-fields"); ?>',
                            product_id: product_id,
                            variation_id: variation_id,
                            loop: loop
                        },
                        success: function(response) {
                            if (response.success) {
                                $container.html(response.data.html);
                            }
                        }
                    });
                });
            }

            // Initial load
            updateVariationFields();

            // Watch for checkbox changes
            $('#_arsol_server').on('change', updateVariationFields);

            // Watch for new variations being added
            $('#woocommerce-product-data').on('woocommerce_variations_added', updateVariationFields);

            // Existing validation code
            $(document).on('input', '[id^="_arsol_server_variation_region"], [id^="_arsol_server_variation_image"]', function() {
                this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');
            });
        });
        </script>
        <?php
    }
}
