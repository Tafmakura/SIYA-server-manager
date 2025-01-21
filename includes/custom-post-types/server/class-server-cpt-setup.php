<?php

namespace SIYA\CustomPostTypes;

class ServerPostSetup {

    public function __construct() {
        add_action('init', array($this, 'create_server_post_type'));
        add_filter('post_row_actions', array($this, 'remove_post_table_actions'), 999999, 2);
        add_action('admin_menu', array($this, 'remove_add_new_button'));
        add_filter('map_meta_cap', array($this, 'restrict_capabilities'), 10, 4);
        add_filter('bulk_actions-edit-server', array($this, 'remove_bulk_actions'));
        add_filter('display_post_states', array($this, 'remove_post_states'), 10, 2);
        add_filter('manage_server_posts_columns', array($this, 'customize_columns'));
        add_action('manage_server_posts_custom_column', array($this, 'customize_column_content'), 10, 2);
    }

    /**
     * Registers a custom post type for servers.
     */
    public function create_server_post_type() {
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
            'rewrite'            => array('slug' => 'server', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title','editor','author','thumbnail','excerpt','comments','custom-fields'),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
                'delete_post' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
        );

        register_post_type('server', $args);
    }

    // Remove post table actions priority set to 999999 to make sure it runs last after other plugins
    public function remove_post_table_actions($actions, $post) {
        if ($post->post_type == 'server') {
            unset($actions['trash']);
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    public function remove_bulk_actions($actions) {
        return array();
    }

    public function remove_add_new_button() {
        global $submenu;
        if (isset($submenu['edit.php?post_type=server'])) {
            unset($submenu['edit.php?post_type=server'][10]); // Removes 'Add New'
        }
    }

    public function restrict_capabilities($caps, $cap, $user_id, $args) {
        if ($cap === 'delete_post' || $cap === 'create_posts' || $cap === 'delete_posts') {
            $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    public function remove_post_states($post_states, $post) {
        if ($post->post_type == 'server') {
            $post_states = array();
        }
        return $post_states;
    }

    public function customize_columns($columns) {
        unset($columns['cb']);
        unset($columns['author']);
        return $columns;
    }

    public function customize_column_content($column, $post_id) {
        if ($column === 'date') {
            $post = get_post($post_id);
            $time = get_post_time('G', true, $post);
            $time_diff = time() - $time;
            if ($time_diff > 0 && $time_diff < DAY_IN_SECONDS) {
                $h_time = sprintf(__('%s ago'), human_time_diff($time));
            } else {
                $h_time = mysql2date(__('Y/m/d'), $post->post_date);
            }
            echo '<strong>' . __('Created') . '</strong> ' . $h_time;
        }
    }
}