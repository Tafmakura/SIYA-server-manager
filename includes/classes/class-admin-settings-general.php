<?php

namespace Siya\AdminSettings;

class General {
    
    public function __construct() {
        $this->register_general_settings();
    }


    public function register_general_settings() {
        register_setting('siya_settings_general', 'arsol_allow_admin_server_delition');

        add_settings_section(
            'siya_general_section',
            'General Settings',
            null,
            'siya_settings_general'
        );

        add_settings_field(
            'arsol_allow_admin_server_delition',
            'Allow server deletion by admin',
            array($this, 'render_toggle_field'),
            'siya_settings_general',
            'siya_general_section'
        );
    }

    public function render_toggle_field() {
        ?>
        <input
            type="checkbox"
            name="arsol_allow_admin_server_delition"
            id="arsol_allow_admin_server_delition"
            value="1"
            <?php checked(get_option('arsol_allow_admin_server_delition'), 1); ?>
            class="toggle"
        />
        <?php
    }


    public static function settings_page() {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-general.php';
    }
}
