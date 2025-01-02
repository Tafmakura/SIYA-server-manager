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

    public function save_arsol_server_option_fields($post_id) {
        $is_arsol_server = isset($_POST['_arsol_server']) ? 'yes' : 'no';
        update_post_meta($post_id, '_arsol_server', $is_arsol_server);
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
        echo '<div id="arsol_server_settings_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_server_provider_slug',
            'label'       => __('Server Provider Slug', 'woocommerce'),
            'description' => __('Enter the server provider slug.', 'woocommerce'),
            'desc_tip'    => 'true',
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_server_plan_slug',
            'label'       => __('Server Plan Slug', 'woocommerce'),
            'description' => __('Enter the server plan slug.', 'woocommerce'),
            'desc_tip'    => 'true',
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_applications',
            'label'       => __('Maximum Applications', 'woocommerce'),
            'description' => __('Enter the maximum number of applications allowed.', 'woocommerce'),
            'desc_tip'    => 'true',
            'type'        => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '999',
                'step' => '1',
                'style' => 'width: 3em; text-align: center;',  // Enough for 3 characters and centered
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, \'\')'  // Only accept numbers
            ),
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_staging_sites',
            'label'       => __('Maximum Staging Sites', 'woocommerce'),
            'description' => __('Enter the maximum number of staging sites allowed.', 'woocommerce'),
            'desc_tip'    => 'true',
            'type'        => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '999',
                'step' => '1',
                'style' => 'width: 3em; text-align: center;',  // Enough for 3 characters and centered
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, \'\')'  // Only accept numbers
            ),
        ));
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_wordpress_server',
            'label'       => __('WordPress', 'woocommerce'),
            'description' => __('Enable WordPress server settings.', 'woocommerce'),
        ));
        echo '<div class="arsol_ecommerce_field">';
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_ecommerce',
            'label'       => __('Ecommerce', 'woocommerce'),
            'description' => __('Enable ecommerce for this server.', 'woocommerce'),
        ));
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function save_arsol_server_settings_tab_content($post_id) {
        $arsol_server_provider_slug = isset($_POST['_arsol_server_provider_slug']) ? sanitize_text_field($_POST['_arsol_server_provider_slug']) : '';
        $arsol_server_plan_slug = isset($_POST['_arsol_server_plan_slug']) ? sanitize_text_field($_POST['_arsol_server_plan_slug']) : '';
        $arsol_max_applications = isset($_POST['_arsol_max_applications']) ? intval($_POST['_arsol_max_applications']) : '';
        $arsol_max_staging_sites = isset($_POST['_arsol_max_staging_sites']) ? intval($_POST['_arsol_max_staging_sites']) : '';
        $arsol_wordpress_server = isset($_POST['_arsol_wordpress_server']) ? 'yes' : 'no';
        $arsol_ecommerce = isset($_POST['_arsol_ecommerce']) ? 'yes' : 'no';

        update_post_meta($post_id, '_arsol_server_provider_slug', $arsol_server_provider_slug);
        update_post_meta($post_id, '_arsol_server_plan_slug', $arsol_server_plan_slug);
        update_post_meta($post_id, '_arsol_max_applications', $arsol_max_applications);
        update_post_meta($post_id, '_arsol_max_staging_sites', $arsol_max_staging_sites);
        update_post_meta($post_id, '_arsol_wordpress_server', $arsol_wordpress_server);
        update_post_meta($post_id, '_arsol_wordpress_ecommerce_server', $arsol_ecommerce);
    }

    public function add_admin_footer_script() {
        ?>
        <style>
        .arsol_ecommerce_field {
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

            function toggle_ecommerce_field() {
                if ($('#_arsol_wordpress_server').is(':checked')) {
                    $('.arsol_ecommerce_field').show();
                } else {
                    $('.arsol_ecommerce_field').hide();
                }
            }

            toggle_arsol_server_settings_tab();
            toggle_ecommerce_field();

            $('#_arsol_server').on('change', function() {
                toggle_arsol_server_settings_tab();
            });

            $('#_arsol_wordpress_server').on('change', function() {
                toggle_ecommerce_field();
            });
        });
        </script>
        <?php
    }
}