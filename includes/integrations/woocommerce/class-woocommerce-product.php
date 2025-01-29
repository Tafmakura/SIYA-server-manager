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
        add_action('admin_notices', [$this, 'add_admin_notices']);

        
        // Validation and save hook - run before saving but after product init
        add_filter('woocommerce_admin_process_product_object', [$this, 'validate_and_save_fields'], 5);
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
            'id'            => 'arsol_server',
            'wrapper_class' => 'show_if_subscription show_if_variable-subscription',
            'label'         => __('Server', 'woocommerce'),
            'description'   => __('Enable this if the product is a subscription to a server', 'woocommerce'),
            'default'       => 'no'
        ];
        return $product_type_options;
    }

    public function save_arsol_server_option_fields($post_ID, $product, $update) {
        $is_arsol_server = isset($_POST['arsol_server']) ? 'yes' : 'no';
        update_post_meta($post_ID, '_arsol_server', $is_arsol_server);
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
        $post_id = $product->get_id();
        $is_server = false;

        // Check POST data
        if (isset($_POST['_arsol_server'])) {
            $is_server = $_POST['_arsol_server'] === 'yes';
        } else if (isset($_POST['arsol_server'])) {
            $is_server = $_POST['arsol_server'] === 'yes';
        }

        // Fallback to product meta if POST check fails
        if (!$is_server) {
            $is_server = $product->get_meta('_arsol_server') === 'yes';
        }

        // If not a server product, return early
        if (!$is_server) {
            return $product;
        }

        $has_errors = false;
        $server_type = sanitize_text_field($_POST['arsol_server_type'] ?? '');
        $is_sites_server = $server_type === 'sites_server';

        // 1. Required Fields Validation
        $required_fields = [
            'arsol_server_type' => __('Server Type', 'woocommerce')
        ];

        if (!$is_sites_server) {
            $required_fields += [
                'arsol_server_provider_slug' => __('Server Provider', 'woocommerce'),
                'arsol_server_plan_group_slug' => __('Server Plan Group', 'woocommerce'),
                'arsol_server_plan_slug' => __('Server Plan', 'woocommerce')
            ];
        }

        foreach ($required_fields as $field => $label) {
            if (empty($_POST[$field])) {
                $this->validation_errors[] = sprintf(__('%s is required.', 'woocommerce'), $label);
                $has_errors = true;
            }
        }

        // 2. Pattern Validation
        $pattern_fields = [
            'arsol_server_region' => __('Server Region', 'woocommerce'),
            'arsol_server_image' => __('Server Image', 'woocommerce')
        ];

        foreach ($pattern_fields as $field => $label) {
            $value = sanitize_text_field($_POST[$field] ?? '');
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9-]+$/', $value)) {
                $this->validation_errors[] = sprintf(__('%s can only contain letters, numbers, and hyphens.', 'woocommerce'), $label);
                $has_errors = true;
            }
        }

        // 3. Length Validation
        $region = sanitize_text_field($_POST['arsol_server_region'] ?? '');
        if (strlen($region) > 50) {
            $this->validation_errors[] = __('Server Region cannot exceed 50 characters.', 'woocommerce');
            $has_errors = true;
        }

        // 4. Applications Validation
        if ($is_sites_server || $server_type === 'application_server') {
            $max_apps = absint($_POST['_arsol_max_applications'] ?? 0);
            if ($max_apps < 1) {
                $this->validation_errors[] = __('Maximum Applications must be at least 1.', 'woocommerce');
                $has_errors = true;
            }
        }

        if ($has_errors) {
            return $product;
        }

        // Save all fields if validation passes
        $fields = [
            '_arsol_server_provider_slug' => sanitize_text_field($_POST['arsol_server_provider_slug'] ?? ''),
            '_arsol_server_plan_group_slug' => sanitize_text_field($_POST['arsol_server_plan_group_slug'] ?? ''),
            '_arsol_server_plan_slug' => sanitize_text_field($_POST['arsol_server_plan_slug'] ?? ''),
            '_arsol_server_manager_required' => $is_sites_server ? 'yes' : (isset($_POST['arsol_server_manager_required']) ? 'yes' : 'no'),
            '_arsol_sites_server' => $is_sites_server ? 'yes' : 'no',
            '_arsol_ecommerce_optimized' => isset($_POST['_arsol_ecommerce_optimized']) ? 'yes' : 'no',
        ];

        if ($server_type === 'sites_server' || $server_type === 'application_server') {
            $fields['_arsol_max_applications'] = absint($_POST['_arsol_max_applications'] ?? 0);
        } else {
            $product->delete_meta_data('_arsol_max_applications');
        }

        // Handle region and image
        $region = sanitize_text_field($_POST['arsol_server_region'] ?? $product->get_meta('_arsol_server_region', true));
        $server_image = sanitize_text_field($_POST['arsol_server_image'] ?? $product->get_meta('_arsol_server_image', true));

        if (strlen($region) > 50) {
            $this->validation_errors[] = __('Server Region cannot exceed 50 characters.', 'woocommerce');
            return $product;
        }

        // Save fields
        foreach ($fields as $meta_key => $value) {
            $product->update_meta_data($meta_key, $value);
        }

        $product->save();

        return $product;
    }

    public function add_admin_notices() {
        if (!empty($this->validation_errors)) {
            foreach ($this->validation_errors as $error_message) {
                echo '<div class="notice notice-error is-dismissible">
                        <p>' . esc_html($error_message) . '</p>
                      </div>';
            }
        }
    }
}
