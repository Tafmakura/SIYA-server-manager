<?php 

namespace Siya\Setup;

class CustomPostTypes {
    public function __construct() {
        $this->create_server_post_type();

        echo 'AHA!';
        add_filter('post_row_actions', array($this, 'remove_post_table_actions'), 10, 2);
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
        );

        register_post_type('server', $args);
    }


    /**
     * Remove unwanted actions from server post type list table
     */
    public function remove_post_table_actions($actions, $post) {
        if ($post->post_type === 'server') {
            $allowed_actions = array();
            
            if (isset($actions['edit'])) {
                $allowed_actions['edit'] = $actions['edit'];
            }
            
            if (isset($actions['view'])) {
                $allowed_actions['view'] = $actions['view'];
            }
            
            return $allowed_actions;
        }
        return $actions;
    }
    
}
