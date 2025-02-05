<?php

namespace Siya\CustomPostTypes\ServerPost;

use WC_Subscriptions;


class Setup {

    public function __construct() {
        add_action('init', array($this, 'create_server_post_type'));
        add_action('init', array($this, 'register_server_taxonomies'));
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
        add_action('edit_form_top', array($this, 'display_custom_title'));
        add_filter('gettext', array($this, 'change_published_to_provisioned'), 10, 3);
        add_action('admin_head', array($this, 'remove_preview_button'));


        // Add new filters
        add_action('restrict_manage_posts', array($this, 'add_server_filters'));
        add_filter('pre_get_posts', array($this, 'filter_servers_by_taxonomy'));

        // Add new actions and filters
        add_filter('post_row_actions', array($this, 'modify_server_actions'), 10, 2);
        add_action('init', array($this, 'modify_server_capabilities'));
        add_action('admin_head', array($this, 'my_column_width'));

        // Add action to load WooCommerce styles
        add_action('admin_enqueue_scripts', array($this, 'load_woocommerce_styles'));

        // Include necessary files
        $this->include_files();

        // Instantiate classes
        $this->instatiate_classes();

    }
    /**
     * Include necessary files.
     */
    private function include_files() {
        require_once plugin_dir_path(__FILE__) . 'class-server-cpt.php';
        require_once plugin_dir_path(__FILE__) . 'class-server-cpt-admin-tables.php';
        require_once plugin_dir_path(__FILE__) . 'class-server-cpt-admin-page-subscription.php';
    }

    /**
     * Instantiate classes.
     */
    private function instatiate_classes() {
        new \Siya\CustomPostTypes\ServerPost();
        new \Siya\CustomPostTypes\ServerPost\Admin\Tables();
        new \Siya\CustomPostTypes\ServerPost\Admin\Page\Subscription();
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
            'supports'           => array('author','custom-fields','comments'),
            'capabilities' => array(
                'create_posts' => 'do_not_allow',
           //     'delete_post' => 'do_not_allow',
            ),
            'map_meta_cap' => true,
        );

        register_post_type('server', $args);
    }

    

    public function register_server_taxonomies() {
        // Hierarchical taxonomy: Group
        register_taxonomy(
            'arsol_server_group',
            'server',
            array(
                'labels' => array(
                    'name' => __('Server Groups', 'your-text-domain'),
                    'singular_name' => __('Server Group', 'your-text-domain'),
                ),
                'hierarchical' => true,
                'public' => false,
                'show_ui' => true,
                'rewrite' => array('slug' => 'server-group'),
            )
        );

        // Non-hierarchical taxonomy: Server Tags
        register_taxonomy(
            'arsol_server_tag', 
            'server', 
            array(
                'labels' => array(
                    'name' => __('Server Tags', 'your-text-domain'),
                    'singular_name' => __('Server Tag', 'your-text-domain'),
                ),
                'hierarchical' => false,
                'public' => false,
                'show_ui' => true,
                'rewrite' => array('slug' => 'server-tag'),
            )
        );
    }

    // Remove post table actions priority set to 999999 to make sure it runs last after other plugins
    public function remove_post_table_actions($actions, $post) {
        if ($post->post_type == 'server') {
            // Remove all actions except delete if allowed and no valid subscription is associated
            $subscription_id = get_post_meta($post->ID, 'arsol_server_subscription_id', true);
            $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
            if (current_user_can('administrator') && get_option('arsol_allow_admin_server_delition', false) && (!$subscription_id || !$subscription)) {
                return array('delete' => $actions['delete']);
            }
            return array();
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
        if (in_array($cap, ['delete_post', 'create_posts', 'delete_posts'])) {
            if (isset($args[0])) {
                $post = get_post($args[0]);
                if ($post && $post->post_type === 'server') {
                    // Allow delete capability for administrators if the option is enabled and no valid subscription is associated
                    $subscription_id = get_post_meta($post->ID, 'arsol_server_subscription_id', true);
                    $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
                    if ($cap === 'delete_post' && current_user_can('administrator') && get_option('arsol_allow_admin_server_delition', false) && (!$subscription_id || !$subscription)) {
                        return $caps;
                    }
                    $caps[] = 'do_not_allow';
                }
            }
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
        // Do not unset the comments column
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
        global $post, $pagenow;
        if ($domain === 'default' && $text === 'Published' && isset($post) && $post->post_type === 'server' && ($pagenow === 'edit.php' || $pagenow === 'post.php')) {
            $translated_text = __('Provisioned', 'your-text-domain');
        }
        return $translated_text;
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
                $data['post_title'] = $original_post->post_title; // Revert to the original title
            }
        }
        return $data;
    }

    public function prevent_permalink_and_status_editing($data, $postarr) {
        if ($data['post_type'] === 'server') {
            $original_post = get_post($postarr['ID']);
            if ($original_post) {
                $data['post_name'] = $original_post->post_name; // Revert to the original permalink
                $data['post_status'] = $original_post->post_status; // Revert to the original status
            }
        }
        return $data;
    }

    public function remove_permalink_editor($html, $post_id, $new_title, $new_slug) {
        $post = get_post($post_id);

        // Check if the post type is 'server'
        if ($post->post_type === 'server') {
            return ''; // Return an empty string to remove the permalink editor
        }

        return $html; // Return the original HTML for other post types
    }

    public function display_custom_title($post) {
        if ($post->post_type === 'server') {
            echo '<div id="order_data"><h2> Server: ' . esc_html($post->post_title) . '</h2></div>';
        }
    }

    public function remove_preview_button() {
        global $post_type;
        if ($post_type == 'server') {
            echo '<style>
                #post-preview, .misc-pub-section.misc-pub-post-status { display: none; }
            </style>';
        }
    }

    public function add_server_filters() {
        global $typenow;
    
        if ($typenow === 'server') {
            // Check if there is at least one post of the 'server' post type
            $server_count = wp_count_posts('server')->publish;
            if ($server_count > 0) {
                // Dropdown for Server Groups
                $this->render_taxonomy_dropdown(
                    'arsol_server_group',
                    __('Filter by Server Group', 'your-text-domain'),
                    __('All Server Groups', 'your-text-domain')
                );
    
                // Dropdown for Server Tags
                $this->render_taxonomy_dropdown(
                    'arsol_server_tag',
                    __('Filter by Server Tags', 'your-text-domain'),
                    __('All Server Tags', 'your-text-domain')
                );
            }
        }
    }
    

    private function render_taxonomy_dropdown($taxonomy, $default_label, $reset_label) {
        $taxonomy_obj = get_taxonomy($taxonomy);
        if (!$taxonomy_obj) {
            return;
        }
        $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
        wp_dropdown_categories(array(
            'taxonomy'         => $taxonomy,
            'hide_empty'       => false,
            'name'             => $taxonomy,
            'orderby'          => 'name',
            'selected'         => $selected,
            'hierarchical'     => true,
            'show_option_none' => $reset_label,
            'option_none_value'=> '',
            'value_field'      => 'slug',
        ));
    }
    
    

    public function filter_servers_by_taxonomy($query) {
        global $pagenow;
    
        if ($pagenow === 'edit.php' && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'server') {
            $tax_query = array();
    
            if (!empty($_GET['arsol_server_group'])) {
                $tax_query[] = array(
                    'taxonomy' => 'arsol_server_group',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['arsol_server_group']),
                );
            }
    
            if (!empty($_GET['arsol_server_tag'])) {
                $tax_query[] = array(
                    'taxonomy' => 'arsol_server_tag',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['arsol_server_tag']),
                );
            }
    
            if (!empty($tax_query)) {
                $query->set('tax_query', $tax_query);
            }
        }
    }

    public function modify_server_actions($actions, $post) {
        if ($post->post_type === 'server') {
            // Remove existing delete action
            unset($actions['delete']);
            
            // Check if user is admin, deletion is allowed, and no valid subscription is associated
            $subscription_id = get_post_meta($post->ID, 'arsol_server_subscription_id', true);
            $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
            if (current_user_can('administrator') && get_option('arsol_allow_admin_server_delition', false) && (!$subscription_id || !$subscription)) {
                $delete_url = get_delete_post_link($post->ID, '', true);
                $actions['delete'] = sprintf(
                    '<a href="%s" class="submitdelete" onclick="return confirm(\'Are you sure?\');">%s</a>',
                    $delete_url,
                    __('Delete', 'your-text-domain')
                );
            }
        }
        return $actions;
    }

    public function modify_server_capabilities() {
        if (current_user_can('administrator') && get_option('arsol_allow_admin_server_delition', false)) {
            $role = get_role('administrator');
            $role->add_cap('delete_servers');
            $role->add_cap('delete_published_servers');
        }
    }

    public function my_column_width() {
        echo '<style type="text/css">
                .column-arsol-server-status .column-details { width: 400px !important; overflow: hidden; }
              </style>';
    }

    /**
     * Load WooCommerce and WooCommerce Subscriptions styles on the servers table page.
     */
    public function load_woocommerce_styles($hook) {
        global $typenow;
        if ($typenow === 'server' && $hook === 'edit.php') {
            wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
            wp_enqueue_style('wcs_admin_styles', plugin_dir_url(WC_Subscriptions::$plugin_file) . 'assets/css/admin.css', array(), WC_Subscriptions::$version);
        }
    }
    
    // Other methods remain unchanged

}