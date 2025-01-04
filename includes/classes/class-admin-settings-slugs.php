<?php

namespace Siya\AdminSettings;

class Slugs {
    private const MENU_SLUG = 'siya-settings';
    private const OPTION_GROUP = 'siya_settings';
    private const PROVIDERS = [
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner',
        'vultr' => 'Vultr'
    ];

    public function __construct() {
   
    }

    public static function settings_page(): void {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
