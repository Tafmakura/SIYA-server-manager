<?php

namespace Siya\AdminSettings;

class Slugs {
    private const PROVIDERS = [
        'digitalocean' => 'DigitalOcean',
        'hetzner' => 'Hetzner',
        'vultr' => 'Vultr'
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'initialize_default_settings']);
    }

    public function add_settings_page(): void {
        add_options_page(
            __('SIYA Server Manager Settings', 'siya'),
            __('SIYA Settings', 'siya'),
            'manage_options',
            'siya_settings',
            [$this, 'settings_page']
        );
    }

    public function register_settings(): void {
        // Register the settings group first
        add_settings_section(
            'siya_slugs_main_section',
            __('Server Provider Settings', 'siya'),
            [$this, 'section_callback'],
            'siya_settings'
        );

        // Base settings registration with proper args
        register_setting(
            'siya_settings',  // Option group
            'siya_wp_server_provider', // Option name
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            ]
        );

        // Register plan settings for each provider
        foreach (array_keys(self::PROVIDERS) as $provider) {
            register_setting(
                'siya_settings',  // Option group
                "siya_{$provider}_plans",
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_plans'],
                    'default' => [],
                    'show_in_rest' => false
                ]
            );

            // Add settings field for each provider
            add_settings_field(
                "siya_{$provider}_plans",
                sprintf(__('%s Plans', 'siya'), self::PROVIDERS[$provider]),
                [$this, 'render_provider_plans_field'],
                'siya_settings',  // Page slug
                'siya_slugs_main_section',
                ['provider' => $provider]
            );
        }
    }

    public function section_callback(): void {
        echo '<p>' . esc_html__('Configure your server provider settings here.', 'siya') . '</p>';
    }

    public function render_provider_plans_field(array $args): void {
        $provider = $args['provider'];
        $plans = $this->get_plans($provider);
        
        echo '<div class="provider-plans">';
        foreach ($plans as $index => $plan) {
            echo '<div class="plan-item">';
            printf(
                '<input type="text" name="siya_%s_plans[%d][slug]" value="%s" />',
                esc_attr($provider),
                esc_attr($index),
                esc_attr($plan['slug'])
            );
            printf(
                '<textarea name="siya_%s_plans[%d][description]">%s</textarea>',
                esc_attr($provider),
                esc_attr($index),
                esc_textarea($plan['description'])
            );
            echo '</div>';
        }
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
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('siya_settings');
                do_settings_sections('siya_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
