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
        add_action('admin_head', array($this, 'disable_title_editing'));
        add_action('admin_head', array($this, 'disable_status_editing'));
        add_filter('wp_insert_post_data', array($this, 'prevent_title_editing'), 10, 2);
        add_filter('wp_insert_post_data', array($this, 'prevent_permalink_and_status_editing'), 10, 2);
        add_filter('get_sample_permalink_html', array($this, 'remove_permalink_editor'), 10, 4);
        add_action('manage_server_posts_custom_column', array($this, 'populate_custom_columns'), 10, 2);
        add_action('edit_form_top', array($this, 'display_custom_title'));
        add_filter('gettext', array($this, 'change_published_to_provisioned'), 10, 3);
        add_filter('post_row_actions', array($this, 'add_delete_action_for_admins'), 1000010, 2);
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
            'supports'           => array('author', 'custom-fields', 'comments'),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
                'delete_post'  => 'delete_post',
                'delete_posts' => 'delete_posts',
            ),
            'map_meta_cap'       => true,
            'publicly_queryable' => false,
            'exclude_from_search'=> true,
        );

        register_post_type('server', $args);
    }

    /**
     * Restricts capabilities to only allow administrators to delete server posts.
     */
    public function restrict_capabilities($caps, $cap, $user_id, $args) {
        $post_type = isset($args[0]) ? get_post_type($args[0]) : '';

        if ($post_type === 'server') {
            if (in_array($cap, ['delete_post', 'delete_posts', 'create_posts'], true) && !user_can($user_id, 'administrator')) {
                $caps[] = 'do_not_allow';
            }
        }

        return $caps;
    }

    public function remove_post_table_actions($actions, $post) {
        if ($post->post_type === 'server' && !current_user_can('administrator')) {
            return array(); // Remove all actions for non-admins
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

    public function remove_post_states($post_states, $post) {
        if ($post->post_type === 'server') {
            $post_states = array();
        }
        return $post_states;
    }

    public function customize_columns($columns) {
        unset($columns['cb']);
        unset($columns['author']);
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['details'] = __('Details', 'your-text-domain');
            }
        }
        return $new_columns;
    }

    public function change_published_to_provisioned($translated_text, $text, $domain) {
        global $post;
        if ($domain === 'default' && $text === 'Published' && $post->post_type === 'server') {
            $translated_text = __('Provisioned', 'your-text-domain');
        }
        return $translated_text;
    }

    public function populate_custom_columns($column, $post_id) {
        if ($column === 'details') {
            // Populate custom column content (e.g., related subscription details)
            echo __('Details content here.', 'your-text-domain');
        }
    }

    public function disable_title_editing() {
        global $post_type;
        if ($post_type == 'server') {
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const titleField = document.getElementById("title");
                    if (titleField) {
                        titleField.setAttribute("readonly", "readonly");
                    }
                });
            </script>';
        }
    }

    public function disable_status_editing() {
        global $post_type;
        if ($post_type == 'server') {
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const statusField = document.getElementById("post_status");
                    if (statusField) {
                        statusField.setAttribute("disabled", "disabled");
                    }
                });
            </script>';
        }
    }

    public function prevent_title_editing($data, $postarr) {
        if ($data['post_type'] === 'server') {
            $original_post = get_post($postarr['ID']);
            if ($original_post && $original_post->post_title !== $data['post_title']) {
                $data['post_title'] = $original_post->post_title;
            }
        }
        return $data;
    }

    public function prevent_permalink_and_status_editing($data, $postarr) {
        if ($data['post_type'] === 'server') {
            $original_post = get_post($postarr['ID']);
            if ($original_post) {
                $data['post_name'] = $original_post->post_name;
                $data['post_status'] = $original_post->post_status;
            }
        }
        return $data;
    }

    public function remove_permalink_editor($html, $post_id, $new_title, $new_slug) {
        $post = get_post($post_id);

        if ($post->post_type === 'server') {
            return '';
        }

        return $html;
    }

    public function display_custom_title($post) {
        if ($post->post_type === 'server') {
            echo '<div id="order_data"><h2> Server: ' . esc_html($post->post_title) . '</h2></div>';
        }
    }

    public function add_delete_action_for_admins($actions, $post) {
        if ($post->post_type == 'server' && current_user_can('administrator')) {
            $actions['delete'] = '<a href="' . get_delete_post_link($post->ID) . '">' . __('Delete') . '</a>';
        }
        return $actions;
    }
}
