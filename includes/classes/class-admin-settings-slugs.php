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
        add_action('wp_ajax_get_provider_plan_groups', [$this, 'ajax_get_provider_plan_groups']); // renamed
        add_action('wp_ajax_get_group_plans', [$this, 'ajax_get_group_plans']);
        add_action('wp_ajax_get_providers_by_server_type', [$this, 'ajax_get_providers_by_server_type']);
    }

    // Admin Menu & Settings Methods
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
            $server_types = array_map('sanitize_text_field', $plan['server_types'] ?? []);

            if (!isset($grouped_plans[$group_slug])) {
                $grouped_plans[$group_slug] = [
                    'group_slug' => $group_slug,
                    'plans' => []
                ];
            }

            $grouped_plans[$group_slug]['plans'][] = [
                'slug' => $slug,
                'description' => $description,
                'server_types' => $server_types
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

    public function get_providers_by_server_type(string $server_type): array {
        $providers = [];
        foreach ($this->get_provider_slugs() as $provider) {
            $provider_plans = get_option("siya_{$provider}_plans", []);
            if (!is_array($provider_plans)) {
                continue;
            }
            
            foreach ($provider_plans as $plan) {
                if (isset($plan['server_types']) && 
                    is_array($plan['server_types']) && 
                    in_array($server_type, $plan['server_types'], true)) {
                    $providers[] = $provider;
                    break; // Found a matching plan, no need to check others
                }
            }
        }
        return array_values(array_unique($providers));
    }

    /**
     * Group Methods
     */
    public function get_provider_plan_group_slugs(string $provider_slug): array {
        if (!$this->provider_exists($provider_slug)) {
            return [];
        }
        $plans = get_option("siya_{$provider_slug}_plans", []);
        if (!is_array($plans)) {
            return [];
        }
        return array_values(array_unique(array_column($plans, 'group_slug')));
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

    public function get_provider_plan_groups_by_server_type(string $provider_slug, string $server_type): array {
        $provider_plans = get_option("siya_{$provider_slug}_plans", []);
        if (!is_array($provider_plans)) {
            return [];
        }
        error_log('target provider slug' . $provider_slug);
        error_log('target server type' . $server_type);
        error_log('Input Provider Plans' . var_export($provider_plans, true));
        $groups = [];
        foreach ($provider_plans as $plan) {
            if (isset($plan['server_types']) && 
                is_array($plan['server_types']) && 
                in_array($server_type, $plan['server_types'], true) && 
                isset($plan['group_slug']) && 
                !in_array($plan['group_slug'], $groups)) {
                $groups[] = $plan['group_slug'];
            }
        }

        error_log('Output groups' . var_export($groups, true));
        
        return array_values($groups);
    }

    /**
     * Plan Methods
     */

    // Get plan details 
    public function get_plan_details(string $provider_slug, string $plan_slug): ?array {
        $plans = $this->get_filtered_plans($provider_slug);
        $filtered = array_filter($plans, function($plan) use ($plan_slug) {
            return $plan['slug'] === $plan_slug;
        });
        return !empty($filtered) ? reset($filtered) : null;
    }

    // Check if plan exists
    public function plan_exists(string $provider_slug, string $plan_slug): bool {
        return $this->get_plan_details($provider_slug, $plan_slug) !== null;
    }

    // Get plans by group
    public function get_group_plans_by_server_type(string $provider_slug, string $group_slug, string $server_type): array {
        $all_plans = $this->get_filtered_plans($provider_slug);
        $filtered_plans = array_filter($all_plans, function($plan) use ($group_slug, $server_type) {
            return isset($plan['group_slug']) && 
                   $plan['group_slug'] === $group_slug &&
                   isset($plan['server_types']) && 
                   is_array($plan['server_types']) && 
                   in_array($server_type, $plan['server_types'], true);
        });
        return array_values($filtered_plans);
    }

    // Get all plans
    public function get_filtered_plans(?string $provider_slug = null, ?string $group_slug = null): array {
        if ($provider_slug && !$this->provider_exists($provider_slug)) {
            return [];
        }

        $result = [];
        if ($provider_slug) {
            $plans = get_option("siya_{$provider_slug}_plans", []);
            if (!is_array($plans)) {
                return [];
            }

            if ($group_slug) {
                $result = array_values(array_filter($plans, function($plan) use ($group_slug) {
                    return isset($plan['group_slug']) && $plan['group_slug'] === $group_slug;
                }));
            } else {
                $result = array_values($plans);
            }
        }

        return $result;
    }

    /**
     * AJAX Handlers
     */

    public function ajax_get_providers_by_server_type(): void {
        if (!isset($_GET['server_type'])) {
            wp_send_json([]);
            return;
        }
        $server_type = sanitize_text_field($_GET['server_type']);
        $providers = $this->get_providers_by_server_type($server_type);
        wp_send_json($providers);
    }
    
    public function ajax_get_provider_plan_groups(): void {
        $provider = sanitize_text_field($_GET['provider']);
        $server_type = isset($_GET['server_type']) ? sanitize_text_field($_GET['server_type']) : null;
        
        if ($server_type) {
            wp_send_json($this->get_provider_plan_groups_by_server_type($provider, $server_type));
        } else {
            wp_send_json($this->get_provider_plan_group_slugs($provider));
        }
    }

    public function ajax_get_group_plans(): void {
        $provider = sanitize_text_field($_GET['provider']);
        $group = sanitize_text_field($_GET['group']);
        $server_type = isset($_GET['server_type']) ? sanitize_text_field($_GET['server_type']) : null;
        
        if ($server_type) {
            error_log('NOPE');
            wp_send_json($this->get_group_plans_by_server_type($provider, $group, $server_type));
        } else {
            error_log('YEP');
            wp_send_json($this->get_filtered_plans($provider, $group));
        }
    }



}
