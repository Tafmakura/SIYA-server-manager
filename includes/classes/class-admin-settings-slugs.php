<?php

namespace Siya\AdminSettings;

class Slugs {
    private const MENU_SLUG = 'siya-slugs-settings';
    private const OPTION_GROUP = 'siya_settings_slugs';
    private const PROVIDERS = [
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner',
        'vultr' => 'Vultr'
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('allowed_options', [$this, 'add_allowed_options']);
        add_action('wp_ajax_get_provider_groups', [$this, 'ajax_get_provider_groups']);
        add_action('wp_ajax_get_group_plans', [$this, 'ajax_get_group_plans']);
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

        // Register Sites provider setting
        register_setting(
            self::OPTION_GROUP,
            'siya_wp_server_provider',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        );

        // Register Sites group setting
        register_setting(
            self::OPTION_GROUP,
            'siya_wp_server_group',
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
        $grouped_plans = [];

        foreach ($plans as $plan) {
            $group_slug = preg_replace('/[^a-zA-Z0-9-]/', '', sanitize_text_field($plan['group_slug']));
            $slug = preg_replace('/[^a-zA-Z0-9-]/', '', sanitize_text_field($plan['slug']));
            $description = sanitize_textarea_field($plan['description']);

            if (!isset($grouped_plans[$group_slug])) {
                $grouped_plans[$group_slug] = [
                    'group_slug' => $group_slug,
                    'plans' => []
                ];
            }

            $grouped_plans[$group_slug]['plans'][] = [
                'slug' => $slug,
                'description' => $description
            ];
        }

        return array_values($grouped_plans);
    }

    /**
     * Add allowed options.
     */
    public function add_allowed_options($allowed_options) {
        $allowed_options[self::OPTION_GROUP] = [
            'siya_wp_server_provider',
            'siya_wp_server_group', // Ensure this line is included
            'siya_digitalocean_plans',
            'siya_hetzner_plans',
            'siya_vultr_plans'
        ];
        return $allowed_options;
    }

    public static function settings_page(): void {
        include plugin_dir_path(__DIR__) . '../ui/templates/admin/settings-page-slugs.php';
    }

    /**
     * Provider Methods
     */
    public function get_provider_slugs(): array {
        return array_keys(self::PROVIDERS);
    }

    public function get_provider_name(string $provider_slug): string {
        return self::PROVIDERS[$provider_slug] ?? '';
    }

    public function provider_exists(string $provider_slug): bool {
        return isset(self::PROVIDERS[$provider_slug]);
    }

    /**
     * Group Methods
     */
    public function get_provider_group_slugs(string $provider_slug): array {
        if (!$this->provider_exists($provider_slug)) {
            return [];
        }
        $plans = get_option("siya_{$provider_slug}_plans", []);
        if (!is_array($plans)) {
            return [];
        }
        return array_unique(array_column($plans, 'group_slug'));
    }

    public function get_providers_by_group(string $group_slug): array {
        $providers = [];
        foreach ($this->get_provider_slugs() as $provider) {
            $plans = get_option("siya_{$provider}_plans", []);
            if (in_array($group_slug, array_column($plans, 'group_slug'))) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    /**
     * Plan Methods
     */
    public function get_filtered_plans(?string $provider_slug = null, ?string $group_slug = null): array {
        if ($provider_slug && !$this->provider_exists($provider_slug)) {
            return [];
        }

        $plans = [];
        if ($provider_slug) {
            $plans = get_option("siya_{$provider_slug}_plans", []);
            if (!is_array($plans)) {
                return [];
            }
            if ($group_slug) {
                $plans = array_filter($plans, function($plan) use ($group_slug) {
                    return $plan['group_slug'] === $group_slug;
                });
            }
        }
        return $plans;
    }

    public function get_plan_details(string $provider_slug, string $plan_slug): ?array {
        $plans = $this->get_filtered_plans($provider_slug);
        $filtered = array_filter($plans, function($plan) use ($plan_slug) {
            return $plan['slug'] === $plan_slug;
        });
        return !empty($filtered) ? reset($filtered) : null;
    }

    public function plan_exists(string $provider_slug, string $plan_slug): bool {
        return $this->get_plan_details($provider_slug, $plan_slug) !== null;
    }

    public function ajax_get_provider_groups(): void {
        $provider = sanitize_text_field($_GET['provider']);
        wp_send_json($this->get_provider_group_slugs($provider));
    }

    public function ajax_get_group_plans(): void {
        $provider = sanitize_text_field($_GET['provider']);
        $group = sanitize_text_field($_GET['group']);
        $plans = $this->get_filtered_plans($provider, $group);
        wp_send_json($plans);
    }
}
