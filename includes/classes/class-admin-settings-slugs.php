<?php

namespace Siya\AdminSettings;

class Slugs {
    private const PROVIDERS = [
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner',
        'vultr' => 'Vultr'
    ];

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'initialize_default_settings']);
    }

    public function register_settings(): void {
        // Base settings
        register_setting('siya_slugs_settings', 'siya_wp_server_provider');

        // Register plan settings for each provider
        foreach (array_keys(self::PROVIDERS) as $provider) {
            register_setting(
                'siya_slugs_settings',
                "siya_{$provider}_plans",
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_plans'],
                    'default' => []
                ]
            );
        }
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

    public function get_plans(string $provider): array {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return [];
        }
        return (array) get_option("siya_{$provider}_plans", []);
    }

    public function add_plan(string $provider, string $slug, string $description): bool {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return false;
        }

        $plans = $this->get_plans($provider);
        $plans[] = [
            'slug' => sanitize_key($slug),
            'description' => sanitize_textarea_field($description)
        ];

        return update_option("siya_{$provider}_plans", $plans);
    }

    public function remove_plan(string $provider, int $index): bool {
        if (!array_key_exists($provider, self::PROVIDERS)) {
            return false;
        }

        $plans = $this->get_plans($provider);
        if (!isset($plans[$index])) {
            return false;
        }

        unset($plans[$index]);
        return update_option("siya_{$provider}_plans", array_values($plans));
    }

    public function get_providers(): array {
        return self::PROVIDERS;
    }

    public static function settings_page(): void {
        include plugin_dir_path(__DIR__) . '../templates/admin/settings-page-slugs.php';
    }
}
