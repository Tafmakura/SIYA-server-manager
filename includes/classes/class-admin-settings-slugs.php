<?php

namespace Siya\AdminSettings;

class Slugs {
    private const MENU_SLUG = 'siya-slugs-settings';
    private const OPTION_GROUP = 'siya_settings';
    private const PROVIDERS = [
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner',
        'vultr' => 'Vultr'
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('allowed_options', [$this, 'add_allowed_options']);
    }

    public function add_menu_page(): void {
        add_options_page(
            __('SIYA Server Manager', 'siya'),
            __('SIYA Settings', 'siya'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'settings_page']
        );
    }

    public function register_settings(): void {
        // Register main settings section
        add_settings_section(
            'siya_providers_section',
            __('Server Provider Settings', 'siya'),
            [$this, 'section_callback'],
            self::MENU_SLUG
        );

        // Register WordPress provider setting
        register_setting(
            self::OPTION_GROUP,
            'siya_wp_server_provider',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        );

        // Register provider plan settings
        foreach (self::PROVIDERS as $slug => $name) {
            register_setting(
                self::OPTION_GROUP, 
                "siya_{$slug}_plans",
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_plans']
                ]
            );
        }
    }

    public function section_callback(): void {
        echo '<p>' . esc_html__('Configure your server provider settings here.', 'siya') . '</p>';
    }

    public function sanitize_plans(array $plans): array {
        return array_map(function($plan) {
            return [
                'group_slug' => sanitize_text_field($plan['group_slug']),
                'slug' => sanitize_text_field($plan['slug']),
                'description' => sanitize_textarea_field($plan['description'])
            ];
        }, $plans);
    }

    /**
     * Add allowed options.
     */
    public function add_allowed_options($allowed_options) {
        $allowed_options['siya_settings'] = [
            'siya_wp_server_provider',
            'siya_digitalocean_plans',
            'siya_hetzner_plans',
            'siya_vultr_plans'
        ];
        return $allowed_options;
    }

    public static function settings_page(): void {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
