<?php

namespace Siya\AdminSettings;

class General {
    
    public function __construct() {
        $this->register_general_settings();
    }


    public function register_general_settings() {
        register_setting('siya_settings_general', 'arsol_allow_admin_server_delition');
        register_setting('siya_settings_general', 'arsol_allowed_server_types', [
            'default' => ['sites_server']
        ]);

        add_settings_section(
            'siya_general_section',
            'General Settings',
            null,
            'siya_settings_general'
        );

        add_settings_section(
            'siya_general_server_types_section',
            'Allowed server types',
            null,
            'siya_settings_general'
        );

        add_settings_field(
            'arsol_allow_admin_server_delition',
            'Allow server deletion by admin',
            'siya_settings_general',
            'siya_general_section'
        );

        add_settings_field(
            'arsol_allowed_server_types',
            'Allowed server types',
            [$this, 'render_allowed_server_types_field'],
            'siya_settings_general',
            'siya_general_server_types_section'
        );
    }

    public function render_allowed_server_types_field() {
        $saved_types = (array) get_option('arsol_allowed_server_types', []);
        $all_types = [
            'sites_server'          => 'Sites Server',
            'application_server'    => 'Application Server',
            'block_storage_server'  => 'Block Storage Server',
            'cloud_server'          => 'Cloud Server',
            'email_server'          => 'Email Server',
            'object_storage_server' => 'Object Storage Server',
            'vps_server'            => 'VPS Server',
        ];

    }

    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../ui/templates/admin/settings-page-general.php';
    }
}
