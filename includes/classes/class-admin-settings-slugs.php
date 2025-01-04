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
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'initialize_default_settings']);
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
        // Register the settings group first
        add_settings_section(
            'siya_main_section',
            __('Server Provider Settings', 'siya'),
            [$this, 'section_callback'],
            self::MENU_SLUG
        );

        // Base settings registration with proper args
        register_setting(
            self::OPTION_GROUP,
            'siya_wp_server_provider',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        // Register plan settings for each provider
        foreach (array_keys(self::PROVIDERS) as $provider) {
            register_setting(
                self::OPTION_GROUP,
                "siya_{$provider}_plans",
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_plans'],
                    'default' => []
                ]
            );

            // Add settings field for each provider
            add_settings_field(
                "siya_{$provider}_plans",
                sprintf(__('%s Plans', 'siya'), self::PROVIDERS[$provider]),
                [$this, 'render_provider_plans_field'],
                self::MENU_SLUG,
                'siya_main_section',
                ['provider' => $provider]
            );
        }
    }

    public function section_callback(): void {
        echo '<p>' . esc_html__('Configure your server provider settings here.', 'siya') . '</p>';
    }

    public function render_provider_plans_field(array $args): void {
        $provider = $args['provider'];
        $plans = get_option("siya_{$provider}_plans", []);
        
        echo '<div class="provider-plans">';
        foreach ($plans as $index => $plan) {
            echo '<div class="plan-item">';
            printf(
                '<input type="text" name="siya_%s_plans[%d][slug]" value="%s" placeholder="Plan Slug" />',
                esc_attr($provider),
                esc_attr($index),
                esc_attr($plan['slug'])
            );
            printf(
                '<textarea name="siya_%s_plans[%d][description]" placeholder="Plan Description">%s</textarea>',
                esc_attr($provider),
                esc_attr($index),
                esc_textarea($plan['description'])
            );
            echo '<button type="button" class="button remove-plan">' . __('Remove plan', 'siya') . '</button>';
            echo '</div>';
        }
        echo '<button type="button" class="button add-plan">' . __('Add plan', 'siya') . '</button>';
        echo '</div>';
    }

    public function initialize_default_settings(): void {
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $option_name = "siya_{$provider}_plans";
            if (false === get_option($option_name)) {
                add_option($option_name, []);
            }
        }
    }

    public function sanitize_plans(array $plans): array {
        return array_filter(array_map(function($plan) {
            if (empty($plan['slug']) || empty($plan['description'])) {
                return null;
            }
            return [
                'slug' => sanitize_key($plan['slug']),
                'description' => sanitize_textarea_field($plan['description'])
            ];
        }, $plans));
    }

    public static function settings_page(): void {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
