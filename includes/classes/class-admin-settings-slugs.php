<?php

namespace Siya\AdminSettings;

class Slugs {
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Register settings for slugs
     */
    public function register_settings() {
        register_setting('siya_slugs_settings', 'siya_server_dashboard_slug');
        register_setting('siya_slugs_settings', 'siya_servers_list_slug');
        register_setting('siya_slugs_settings', 'siya_wp_plans_slug');
        register_setting('siya_slugs_settings', 'siya_provider_plans_slug');
    }

    /**
     * Display the slugs settings page
     */
    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
