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
            'label'         => __('Server', 'woocommerce'),
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
        $enabled_server_types = (array) get_option('arsol_allowed_server_types', []);
        if (!in_array('sites_server', $enabled_server_types)) {
            $enabled_server_types[] = 'sites_server';
        }
        include plugin_dir_path(__FILE__) . '../../../ui/templates/admin/woocommerce/product-settings-server.php';
    }

    public function save_arsol_server_settings_tab_content($post_id) {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }

        // Force Runcloud integration if Sites server is enabled
        $is_sites_server = isset($_POST['_arsol_sites_server']);
        
        // Define and sanitize basic fields
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($_POST['_arsol_server_provider_slug'] ?? ''),
            '_arsol_server_plan_group_slug'    => sanitize_text_field($_POST['_arsol_server_plan_group_slug'] ?? ''),
            '_arsol_server_plan_slug'     => sanitize_text_field($_POST['_arsol_server_plan_slug'] ?? ''),
            '_arsol_max_applications'     => absint($_POST['_arsol_max_applications'] ?? 0),
            '_arsol_max_staging_sites'    => absint($_POST['_arsol_max_staging_sites'] ?? 0),
            '_arsol_server_manager_required' => $is_sites_server ? 'yes' : (isset($_POST['_arsol_server_manager_required']) ? 'yes' : 'no'),
            '_arsol_sites_server'     => $is_sites_server ? 'yes' : 'no',
            '_arsol_wordpress_ecommerce'  => isset($_POST['_arsol_wordpress_ecommerce']) ? 'yes' : 'no',
        ];

        // Get existing values for region and image
        $existing_region = get_post_meta($post_id, '_arsol_server_region', true);
        $existing_image = get_post_meta($post_id, '_arsol_server_image', true);
        
        // Handle region and image fields
        $region = isset($_POST['_arsol_server_region']) ? sanitize_text_field($_POST['_arsol_server_region']) : $existing_region;
        $server_image = isset($_POST['_arsol_server_image']) ? sanitize_text_field($_POST['_arsol_server_image']) : $existing_image;

        // Only validate if fields are not empty and were modified
        if (!empty($region) && $region !== $existing_region) {
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $region)) {
                wc_add_notice(__('Region field can only contain letters, numbers, and hyphens.', 'woocommerce'), 'error');
                return;
            }
            if (strlen($region) > 50) {
                wc_add_notice(__('Region field cannot exceed 50 characters.', 'woocommerce'), 'error');
                return;
            }
        }

        // Set region and image values - only clear if Sites server is being enabled
        $was_sites_server = get_post_meta($post_id, '_arsol_sites_server', true) === 'yes';

        if ($is_sites_server && !$was_sites_server) {
            // Only clear values when transitioning to Sites server
            $fields['_arsol_server_region'] = '';
            $fields['_arsol_server_image'] = '';
        } else {
            // Keep existing or updated values
            $fields['_arsol_server_region'] = $region;
            $fields['_arsol_server_image'] = $server_image;
        }

        $fields['_arsol_server_type'] = sanitize_text_field($_POST['_arsol_server_type'] ?? '');

        // Save all fields
        foreach ($fields as $meta_key => $value) {
            update_post_meta($post_id, $meta_key, $value);
        }

        $additional_groups = isset($_POST['_arsol_additional_server_groups'])
            ? array_map('sanitize_text_field', $_POST['_arsol_additional_server_groups'])
            : [];
        update_post_meta($post_id, '_arsol_additional_server_groups', $additional_groups);

        $server_groups = isset($_POST['_arsol_server_groups'])
            ? array_map('sanitize_text_field', $_POST['_arsol_server_groups'])
            : [];
        update_post_meta($post_id, '_arsol_server_groups', $server_groups);

        $assigned_server_groups = isset($_POST['_arsol_assigned_server_groups'])
            ? array_map('intval', $_POST['_arsol_assigned_server_groups'])
            : [];
        update_post_meta($post_id, '_arsol_assigned_server_groups', $assigned_server_groups);

        // Save assigned server tags
        $assigned_server_tags = isset($_POST['_arsol_assigned_server_tags'])
            ? array_map('intval', $_POST['_arsol_assigned_server_tags'])
            : [];
        update_post_meta($post_id, '_arsol_assigned_server_tags', $assigned_server_tags);
    }

    public function add_admin_footer_script() {
        ?>
        <style>
        .arsol_wordpress_ecommerce_field, .arsol_server_group_slug_field {
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
                if ($('#_arsol_sites_server').is(':checked')) {
                    $('.arsol_wordpress_ecommerce_field').show();
                    $('.arsol_server_group_slug_field').hide();
                } else {
                    $('.arsol_wordpress_ecommerce_field').hide();
                    $('.arsol_server_group_slug_field').show();
                }
            }

            function handleSitesServerChange() {
                var $sitesCheckbox = $('#_arsol_sites_server');
                var $runcloudCheckbox = $('#_arsol_server_manager_required');
                
                if ($sitesCheckbox.is(':checked')) {
                    $runcloudCheckbox.prop('checked', true).prop('disabled', true);
                    $('.arsol_wordpress_ecommerce_field').show();
                    $('.arsol_server_group_slug_field').hide();
                } else {
                    $runcloudCheckbox.prop('disabled', false);
                    $('.arsol_wordpress_ecommerce_field').hide();
                    $('.arsol_server_group_slug_field').show();
                }
            }

            toggle_arsol_server_settings_tab();
            toggle_ecommerce_and_server_group_fields();

            $('#_arsol_server').on('change', function() {
                toggle_arsol_server_settings_tab();
            });

            $('#_arsol_sites_server').on('change', function() {
                handleSitesServerChange();
                if ($(this).is(':checked')) {
                    setSitesProvider();
                    toggleSitesFields();
                } else {
                    $('#_arsol_server_provider_slug').prop('disabled', false);
                    $('#_arsol_server_plan_group_slug').prop('disabled', false);
                    toggleSitesFields();
                }
            });

            // Initial state
            handleSitesServerChange();

            // Ensure WordPress Ecommerce maintains its state when hidden before saving
            $('#post').on('submit', function() {
                if (!$('#_arsol_sites_server').is(':checked')) {
                    $('#_arsol_wordpress_ecommerce').prop('checked', false);
                }
                // Enable provider and group fields before submitting
                $('#_arsol_server_provider_slug').prop('disabled', false);
                $('#_arsol_server_plan_group_slug').prop('disabled', false);
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

        // Validate and sanitize input
        $provider = isset($_POST['_arsol_server_provider_slug']) ? sanitize_text_field($_POST['_arsol_server_provider_slug']) : '';
        $group_slug = isset($_POST['_arsol_server_plan_group_slug']) ? sanitize_text_field($_POST['_arsol_server_plan_group_slug']) : '';
        $plan_slug = isset($_POST['_arsol_server_plan_slug']) ? sanitize_text_field($_POST['_arsol_server_plan_slug']) : '';

        // Perform validation
        if (empty($provider) || empty($group_slug) || empty($plan_slug)) {
            wc_add_notice(__('Please fill in all required fields: Server provider, Server group, and Server plan.', 'siya'), 'error');
            return;
        }

        // Save validated data
        update_post_meta($post_id, '_arsol_server_provider_slug', $provider);
        update_post_meta($post_id, '_arsol_server_plan_group_slug', $group_slug);
        update_post_meta($post_id, '_arsol_server_plan_slug', $plan_slug);
    }
}
