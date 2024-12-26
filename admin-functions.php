<?php 

// Create settings menu
function runcloud_settings_menu() {
    add_options_page(
        'RunCloud and Hetzner Settings',  // Page title
        'API Settings',                   // Menu title
        'manage_options',                 // Capability
        'api-settings',                   // Menu slug
        'runcloud_settings_page'          // Callback function
    );
}
add_action('admin_menu', 'runcloud_settings_menu');

// Settings page callback
function runcloud_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('RunCloud and Hetzner Settings', 'your-text-domain'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('api-settings-group');
            do_settings_sections('api-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function register_api_settings() {
    register_setting('api-settings-group', 'runcloud_api_key');
    register_setting('api-settings-group', 'hetzner_api_key');

    add_settings_section(
        'api-settings-section',
        __('API Settings', 'your-text-domain'),
        null,
        'api-settings'
    );

    add_settings_field(
        'runcloud-api-key',
        __('RunCloud API Key', 'your-text-domain'),
        'runcloud_api_key_field',
        'api-settings',
        'api-settings-section'
    );

    add_settings_field(
        'hetzner-api-key',
        __('Hetzner API Key', 'your-text-domain'),
        'hetzner_api_key_field',
        'api-settings',
        'api-settings-section'
    );
}
add_action('admin_init', 'register_api_settings');

// API key field callback for RunCloud
function runcloud_api_key_field() {
    $api_key = get_option('runcloud_api_key');
    ?>
    <input type="text" name="runcloud_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
    <?php
}

// API key field callback for Hetzner
function hetzner_api_key_field() {
    $api_key = get_option('hetzner_api_key');
    ?>
    <input type="text" name="hetzner_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
    <?php
}

// Register Custom Post Type "Server"
function create_server_post_type() {
    $labels = array(
        'name'               => _x('Servers', 'post type general name', 'your-text-domain'),
        'singular_name'      => _x('Server', 'post type singular name', 'your-text-domain'),
        'menu_name'          => _x('Servers', 'admin menu', 'your-text-domain'),
        'add_new'            => _x('Add New', 'server', 'your-text-domain'),
        'add_new_item'       => __('Add New Server', 'your-text-domain'),
        'edit_item'          => __('Edit Server', 'your-text-domain'),
        'view_item'          => __('View Server', 'your-text-domain'),
        'all_items'          => __('All Servers', 'your-text-domain'),
        'search_items'       => __('Search Servers', 'your-text-domain'),
        'not_found'          => __('No servers found.', 'your-text-domain'),
        'not_found_in_trash' => __('No servers found in Trash.', 'your-text-domain')
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'server'),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
    );

    register_post_type('server', $args);
}
add_action('init', 'create_server_post_type');


