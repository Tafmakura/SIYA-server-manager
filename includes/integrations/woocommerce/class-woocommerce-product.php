<?php

namespace Siya\Integrations\WooCommerce;

use Siya\AdminSettings\Slugs;

defined('ABSPATH') || exit;

class Product {
   
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'add_custom_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_custom_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add custom product type options
        add_filter('product_type_options', [$this, 'add_arsol_server_product_option']);
    
        // Save the ARSOL server checkbox option
        add_action('save_post_product', [$this, 'save_arsol_server_option_fields'], 10, 3);
          
        
        // Add Arsol Server Settings Tab
        add_filter('woocommerce_product_data_tabs', [$this, 'add_arsol_server_settings_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_arsol_server_settings_tab_content']);
        add_action('woocommerce_process_product_meta', [$this, 'save_arsol_server_settings_tab_content']);
        
        // Enqueue custom script for admin
        add_action('admin_footer', [$this, 'add_admin_footer_script']);
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

    public function save_arsol_server_option_fields($post_ID, $product, $update) {
        // Save the ARSOL server checkbox value
        $is_arsol_server = isset($_POST['_arsol_server']) ? 'yes' : 'no';
        update_post_meta($post_ID, '_arsol_server', $is_arsol_server);
    }

    public function add_arsol_server_settings_tab($tabs) {
        $tabs['arsol_server_settings'] = array(
            'label'    => __('Server Settings', 'woocommerce'),
            'target'   => 'arsol_server_settings_data',
            'class'    => ['show_if_simple', 'show_if_variable'],
            'priority' => 50,
        );

        return $tabs;
    }

    public function add_arsol_server_settings_tab_content() {
        global $post;
        $slugs = new Slugs();
        include plugin_dir_path(__FILE__) . '../../../templates/admin/woocommerce/product-settings-server.php';
    }

    public function save_arsol_server_settings_tab_content($post_id) {
        // Check if WooCommerce is saving the product meta
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }

        // Sanitize and validate region field specifically
        $region = isset($_POST['arsol_server_region']) ? $_POST['arsol_server_region'] : '';
        if (!empty($region)) {
            // Check if region contains only allowed characters
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                // Add error message for invalid characters
                wc_add_notice(__('Region field can only contain letters, numbers, and hyphens.', 'woocommerce'), 'error');
                return;
            }
            // Additional length validation
            if (strlen($region) > 50) {
                wc_add_notice(__('Region field cannot exceed 50 characters.', 'woocommerce'), 'error');
                return;
            }
        }

        // Define and sanitize fields
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($_POST['_arsol_server_provider_slug'] ?? ''),
            '_arsol_server_group_slug'    => sanitize_text_field($_POST['_arsol_server_group_slug'] ?? ''),
            '_arsol_server_plan_slug'     => sanitize_text_field($_POST['_arsol_server_plan_slug'] ?? ''),
            '_arsol_max_applications'     => absint($_POST['_arsol_max_applications'] ?? 0),
            '_arsol_max_staging_sites'    => absint($_POST['_arsol_max_staging_sites'] ?? 0),
            '_arsol_wordpress_server'     => isset($_POST['_arsol_wordpress_server']) ? 'yes' : 'no',
            '_arsol_ecommerce'            => isset($_POST['_arsol_ecommerce']) ? 'yes' : 'no',
            'arsol_server_region'         => sanitize_text_field($region),
        ];

        // Save all fields, even if empty
        foreach ($fields as $meta_key => $value) {
            update_post_meta($post_id, $meta_key, $value);
        }
    }

    public function add_admin_footer_script() {
        ?>
        <style>
        .arsol_ecommerce_field, .arsol_server_group_slug_field {
            display: none;
        }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggle_arsol_server_settings_tab() {
                if ($('#_arsol_server').is(':checked')) {
                    $('#woocommerce-product-data .arsol_server_settings_options').show();
                } else {
                    $('#woocommerce-product-data .arsol_server_settings_options').hide();
                    $('.wc-tabs .general_tab a').click();
                }
            }

            function toggle_ecommerce_and_server_group_fields() {
                if ($('#_arsol_wordpress_server').is(':checked')) {
                    $('.arsol_ecommerce_field').show();
                    $('.arsol_server_group_slug_field').hide();
                } else {
                    $('.arsol_ecommerce_field').hide();
                    $('.arsol_server_group_slug_field').show();
                }
            }

            toggle_arsol_server_settings_tab();
            toggle_ecommerce_and_server_group_fields();

            $('#_arsol_server').on('change', function() {
                toggle_arsol_server_settings_tab();
            });

            $('#_arsol_wordpress_server').on('change', function() {
                toggle_ecommerce_and_server_group_fields();
            });

            // Ensure WordPress Ecommerce maintains its state when hidden before saving
            $('#post').on('submit', function() {
                if (!$('#_arsol_wordpress_server').is(':checked')) {
                    $('#_arsol_ecommerce').prop('checked', false);
                }
            });
        });
        </script>
        <?php
    }

    public function add_custom_fields() {
        global $post;
        $slugs = new Slugs();
        // Add your custom fields here
    }

    public function save_custom_fields($post_id) {
        // Save your custom fields here
    }

    /**
     * Save product meta data
     */
    public function save_product_meta($post_id) {
        // Check nonce for security
        if (!isset($_POST['siya_product_nonce']) || !wp_verify_nonce($_POST['siya_product_nonce'], 'save_siya_product')) {
            return;
        }

        // Save provider, group, and plan data
        $provider = isset($_POST['_arsol_server_provider_slug']) ? sanitize_text_field($_POST['_arsol_server_provider_slug']) : '';
        $group_slug = isset($_POST['_arsol_server_group_slug']) ? sanitize_text_field($_POST['_arsol_server_group_slug']) : '';
        $plan_slug = isset($_POST['_arsol_server_plan_slug']) ? sanitize_text_field($_POST['_arsol_server_plan_slug']) : '';

        // Perform validation
        if (empty($provider) || empty($group_slug) || empty($plan_slug)) {
            wc_add_notice(__('Please fill in all required fields: Server provider, Server group, and Server plan.', 'siya'), 'error');
            return;
        }

        // Save the basic fields
        update_post_meta($post_id, '_arsol_server_provider_slug', $provider);
        update_post_meta($post_id, '_arsol_server_group_slug', $group_slug);
        update_post_meta($post_id, '_arsol_server_plan_slug', $plan_slug);

        // Save WordPress server status
        $is_wordpress_server = isset($_POST['_arsol_wordpress_server']) && $_POST['_arsol_wordpress_server'] === 'yes';
        update_post_meta($post_id, '_arsol_wordpress_server', $is_wordpress_server ? 'yes' : 'no');

        // Get the existing values for region and image
        $region = get_post_meta($post_id, '_arsol_server_region', true);
        $server_image = get_post_meta($post_id, '_arsol_server_image', true);

        // Only update region and image if WordPress server is not enabled
        if (!$is_wordpress_server) {
            $region = isset($_POST['_arsol_server_region']) ? sanitize_text_field($_POST['_arsol_server_region']) : $region;
            $server_image = isset($_POST['_arsol_server_image']) ? sanitize_text_field($_POST['_arsol_server_image']) : $server_image;
        }

        update_post_meta($post_id, '_arsol_server_region', $region);
        update_post_meta($post_id, '_arsol_server_image', $server_image);
    }
}
