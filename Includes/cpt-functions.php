<?php

// Register custom post type for servers
function register_server_post_type() {
    $args = array(
        'labels' => array(
            'name' => 'Servers',
            'singular_name' => 'Server',
            'add_new' => 'Add New Server',
            'add_new_item' => 'Add New Server',
            'edit_item' => 'Edit Server',
            'new_item' => 'New Server',
            'view_item' => 'View Server',
            'search_items' => 'Search Servers',
            'not_found' => 'No Servers found',
            'not_found_in_trash' => 'No Servers found in Trash',
            'all_items' => 'All Servers',
            'menu_name' => 'Servers',
            'name_admin_bar' => 'Server',
        ),
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-hammer',
        'supports' => array( 'title', 'editor', 'custom-fields', 'author' ),
        'rewrite' => array( 'slug' => 'servers' ),
    );

    register_post_type( 'arsol_server', $args );
}
add_action( 'init', 'register_server_post_type' );

// Add custom meta fields for server info
function add_server_meta_boxes() {
    add_meta_box(
        'arsol_server_meta',
        'Server Information',
        'render_server_meta_box',
        'arsol_server',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'add_server_meta_boxes' );

// Render the meta box content
function render_server_meta_box($post) {
    $server_deployed = get_post_meta($post->ID, 'arsol_server_deployed', true);
    $server_connected = get_post_meta($post->ID, 'arsol_server_connected', true);

    ?>
    <label for="arsol_server_deployed">Server Deployed:</label>
    <input type="checkbox" name="arsol_server_deployed" value="1" <?php checked($server_deployed, 1); ?>><br>
    <label for="arsol_server_connected">Server Connected:</label>
    <input type="checkbox" name="arsol_server_connected" value="1" <?php checked($server_connected, 1); ?>>
    <?php
}

// Save server meta fields
function save_server_meta($post_id) {
    if (isset($_POST['arsol_server_deployed'])) {
        update_post_meta($post_id, 'arsol_server_deployed', '1');
    } else {
        update_post_meta($post_id, 'arsol_server_deployed', '0');
    }

    if (isset($_POST['arsol_server_connected'])) {
        update_post_meta($post_id, 'arsol_server_connected', '1');
    } else {
        update_post_meta($post_id, 'arsol_server_connected', '0');
    }
}
add_action( 'save_post', 'save_server_meta' );
