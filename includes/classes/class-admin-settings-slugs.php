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

        // Add WordPress provider field
        add_settings_field(
            'siya_wp_server_provider',
            __('WordPress Server Provider', 'siya'),
            [$this, 'render_provider_select'],
            self::MENU_SLUG,
            'siya_providers_section'
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

            add_settings_field(
                "siya_{$slug}_plans",
                sprintf(__('%s Plans', 'siya'), $name),
                [$this, 'render_provider_plans'],
                self::MENU_SLUG,
                'siya_providers_section',
                ['provider' => $slug]
            );
        }
    }

    public function section_callback(): void {
        echo '<p>' . esc_html__('Configure your server provider settings here.', 'siya') . '</p>';
    }

    public function render_provider_select(): void {
        $selected = get_option('siya_wp_server_provider');
        ?>
        <select name="siya_wp_server_provider">
            <?php foreach (self::PROVIDERS as $slug => $name): ?>
                <option value="<?php echo esc_attr($slug); ?>" 
                    <?php selected($selected, $slug); ?>>
                    <?php echo esc_html($name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('Select the cloud provider for WordPress hosting', 'siya'); ?>
        </p>
        <?php
    }

    public function render_provider_plans(array $args): void {
        $provider = $args['provider'];
        $plans = get_option("siya_{$provider}_plans", []);
        ?>
        <div class="plan-repeater" data-provider="<?php echo esc_attr($provider); ?>">
            <?php foreach ($plans as $index => $plan): ?>
                <div class="plan-row">
                    <div class="plan-field">
                        <label><?php _e('Plan slug', 'siya'); ?></label>
                        <input type="text" 
                               name="siya_<?php echo esc_attr($provider); ?>_plans[<?php echo $index; ?>][slug]"
                               value="<?php echo esc_attr($plan['slug']); ?>" />
                    </div>
                    <div class="plan-field">
                        <label><?php _e('Description', 'siya'); ?></label>
                        <textarea name="siya_<?php echo esc_attr($provider); ?>_plans[<?php echo $index; ?>][description]"><?php 
                            echo esc_textarea($plan['description']); 
                        ?></textarea>
                    </div>
                    <button type="button" class="button remove-plan">
                        <?php _e('Remove', 'siya'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
            
            <div class="plan-row template" style="display:none">
                <div class="plan-field">
                    <label><?php _e('Plan slug', 'siya'); ?></label>
                    <input type="text" name="plan_slug[]" placeholder="<?php _e('Enter plan slug', 'siya'); ?>" />
                </div>
                <div class="plan-field">
                    <label><?php _e('Description', 'siya'); ?></label>
                    <textarea name="plan_description[]" placeholder="<?php _e('Enter plan description', 'siya'); ?>"></textarea>
                </div>
                <button type="button" class="button remove-plan"><?php _e('Remove', 'siya'); ?></button>
            </div>
            
            <button type="button" class="button add-plan">
                <?php _e('Add Plan', 'siya'); ?>
            </button>
        </div>
        <?php
    }

    public function sanitize_plans(array $plans): array {
        return array_map(function($plan) {
            return [
                'slug' => sanitize_text_field($plan['slug']),
                'description' => sanitize_textarea_field($plan['description'])
            ];
        }, $plans);
    }

    public function add_allowed_options($allowed_options) {
        $allowed_options[self::OPTION_GROUP] = [
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
