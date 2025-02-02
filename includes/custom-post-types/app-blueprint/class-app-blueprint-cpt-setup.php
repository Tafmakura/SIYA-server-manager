<?php

namespace Siya\CustomPostTypes\AppBlueprintPost;

use \WC_Subscriptions;

class Setup {

    public function __construct() {
        add_action('init', array($this, 'create_app_blueprint_post_type'));
        add_action('init', array($this, 'register_app_blueprint_taxonomies'));
       // add_filter('post_row_actions', array($this, 'remove_post_table_actions'), 999999, 2);
      //  add_filter('map_meta_cap', array($this, 'restrict_capabilities'), 10, 4);
      //  add_filter('bulk_actions-edit-arsol_app_blueprint', array($this, 'remove_bulk_actions'));
      //  add_filter('display_post_states', array($this, 'remove_post_states'), 10, 2);
      //  add_filter('manage_arsol_app_blueprint_posts_columns', array($this, 'customize_columns'));
      //  add_action('admin_head', array($this, 'disable_title_editing'));
      //  add_action('admin_head', array($this, 'disable_status_editing'));
      //  add_filter('wp_insert_post_data', array($this, 'prevent_title_editing'), 10, 2);
      //  add_filter('wp_insert_post_data', array($this, 'prevent_permalink_and_status_editing'), 10, 2);
      //  add_filter('get_sample_permalink_html', array($this, 'remove_permalink_editor'), 10, 4);
      //  add_action('edit_form_top', array($this, 'display_custom_title'));
      //  add_filter('gettext', array($this, 'change_published_to_provisioned'), 10, 3);
        add_action('admin_head', array($this, 'remove_preview_button'));

        // Add new filters
        add_action('restrict_manage_posts', array($this, 'add_app_blueprint_filters'));
        add_filter('pre_get_posts', array($this, 'filter_app_blueprints_by_taxonomy'));

        // Add new actions and filters
        add_filter('post_row_actions', array($this, 'modify_app_blueprint_actions'), 10, 2);
        add_action('init', array($this, 'modify_app_blueprint_capabilities'));
        add_action('admin_head', array($this, 'my_column_width'));

        // Add action to load WooCommerce styles
        add_action('admin_enqueue_scripts', array($this, 'load_woocommerce_styles'));
    }

    /**
     * Registers a custom post type for app blueprints.
     */
    public function create_app_blueprint_post_type() {
        $labels = array(
            'name'               => _x('App Blueprints', 'post type general name', 'your-text-domain'),
            'singular_name'      => _x('App Blueprint', 'post type singular name', 'your-text-domain'),
            'menu_name'          => _x('App Blueprints', 'admin menu', 'your-text-domain'),
            'add_new'            => _x('Add New', 'arsol_app_blueprint', 'your-text-domain'),
            'add_new_item'       => __('Add New App Blueprint', 'your-text-domain'),
            'edit_item'          => __('Edit App Blueprint', 'your-text-domain'),
            'view_item'          => __('View App Blueprint', 'your-text-domain'),
            'all_items'          => __('All App Blueprints', 'your-text-domain'),
            'search_items'       => __('Search App Blueprints', 'your-text-domain'),
            'not_found'          => __('No app blueprints found.', 'your-text-domain'),
            'not_found_in_trash' => __('No app blueprints found in Trash.', 'your-text-domain')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'arsol_app_blueprint', 'with_front' => false),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('author','custom-fields','comments'),
            'capability_type'    => 'post',
            'map_meta_cap' => true,
        );

        register_post_type('arsol_app_blueprint', $args);
    }

    public function register_app_blueprint_taxonomies() {
        // Hierarchical taxonomy: Group
        register_taxonomy(
            'arsol_app_blueprint_group',
            'arsol_app_blueprint',
            array(
                'labels' => array(
                    'name' => __('App Blueprint Groups', 'your-text-domain'),
                    'singular_name' => __('App Blueprint Group', 'your-text-domain'),
                ),
                'hierarchical' => true,
                'public' => false,
                'show_ui' => true,
                'rewrite' => array('slug' => 'arsol_app_blueprint-group'),
            )
        );

        // Non-hierarchical taxonomy: App Blueprint Tags
        register_taxonomy(
            'arsol_app_blueprint_tag', 
            'arsol_app_blueprint', 
            array(
                'labels' => array(
                    'name' => __('App Blueprint Tags', 'your-text-domain'),
                    'singular_name' => __('App Blueprint Tag', 'your-text-domain'),
                ),
                'hierarchical' => false,
                'public' => false,
                'show_ui' => true,
                'rewrite' => array('slug' => 'arsol_app_blueprint-tag'),
            )
        );
    }

    // Remove post table actions priority set to 999999 to make sure it runs last after other plugins
    public function remove_post_table_actions($actions, $post) {
        if ($post->post_type == 'arsol_app_blueprint') {
            // Remove all actions except delete if allowed and no valid subscription is associated
            $subscription_id = get_post_meta($post->ID, 'arsol_app_blueprint_subscription_id', true);
            $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
            if (current_user_can('administrator') && get_option('arsol_allow_admin_app_blueprint_deletion', false) && (!$subscription_id || !$subscription)) {
                return array('delete' => $actions['delete']);
            }
            return array();
        }
        return $actions;
    }

    public function remove_bulk_actions($actions) {
        return array();
    }

    public function restrict_capabilities($caps, $cap, $user_id, $args) {
        if (in_array($cap, ['delete_post', 'create_posts', 'delete_posts'])) {
            if (isset($args[0])) {
                $post = get_post($args[0]);
                if ($post && $post->post_type === 'arsol_app_blueprint') {
                    // Allow delete capability for administrators if the option is enabled and no valid subscription is associated
                    $subscription_id = get_post_meta($post->ID, 'arsol_app_blueprint_subscription_id', true);
                    $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
                    if ($cap === 'delete_post' && current_user_can('administrator') && get_option('arsol_allow_admin_app_blueprint_deletion', false) && (!$subscription_id || !$subscription)) {
                        return $caps;
                    }
                    $caps[] = 'do_not_allow';
                }
            }
        }
        return $caps;
    }

    public function remove_post_states($post_states, $post) {
        if ($post->post_type == 'arsol_app_blueprint') {
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
        if ($domain === 'default' && $text === 'Published' && isset($post) && $post->post_type === 'arsol_app_blueprint' && ($pagenow === 'edit.php' || $pagenow === 'post.php')) {
            $translated_text = __('Provisioned', 'your-text-domain');
        }
        return $translated_text;
    }

    public function disable_title_editing() {
        global $post_type;
        if ($post_type == 'arsol_app_blueprint') {
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
        if ($post_type == 'arsol_app_blueprint') {
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
        if ($data['post_type'] === 'arsol_app_blueprint') {
            $original_post = get_post($postarr['ID']);
            if ($original_post && $original_post->post_title !== $data['post_title']) {
                $data['post_title'] = $original_post->post_title; // Revert to the original title
            }
        }
        return $data;
    }

    public function prevent_permalink_and_status_editing($data, $postarr) {
        if ($data['post_type'] === 'arsol_app_blueprint') {
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

        // Check if the post type is 'arsol_app_blueprint'
        if ($post->post_type === 'arsol_app_blueprint') {
            return ''; // Return an empty string to remove the permalink editor
        }

        return $html; // Return the original HTML for other post types
    }

    public function display_custom_title($post) {
        if ($post->post_type === 'arsol_app_blueprint') {
            echo '<div id="order_data"><h2> App Blueprint: ' . esc_html($post->post_title) . '</h2></div>';
        }
    }

    public function remove_preview_button() {
        global $post_type;
        if ($post_type == 'arsol_app_blueprint') {
            echo '<style>
                #post-preview, .misc-pub-section.misc-pub-post-status { display: none; }
            </style>';
        }
    }

    public function add_app_blueprint_filters() {
        global $typenow;
    
        if ($typenow === 'arsol_app_blueprint') {
            // Check if there is at least one post of the 'arsol_app_blueprint' post type
            $app_blueprint_count = wp_count_posts('arsol_app_blueprint')->publish;
            if ($app_blueprint_count > 0) {
                // Dropdown for App Blueprint Groups
                $this->render_taxonomy_dropdown(
                    'arsol_app_blueprint_group',
                    __('Filter by App Blueprint Group', 'your-text-domain'),
                    __('All App Blueprint Groups', 'your-text-domain')
                );
    
                // Dropdown for App Blueprint Tags
                $this->render_taxonomy_dropdown(
                    'arsol_app_blueprint_tag',
                    __('Filter by App Blueprint Tags', 'your-text-domain'),
                    __('All App Blueprint Tags', 'your-text-domain')
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

    public function filter_app_blueprints_by_taxonomy($query) {
        global $pagenow;
    
        if ($pagenow === 'edit.php' && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'arsol_app_blueprint') {
            $tax_query = array();
    
            if (!empty($_GET['arsol_app_blueprint_group'])) {
                $tax_query[] = array(
                    'taxonomy' => 'arsol_app_blueprint_group',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['arsol_app_blueprint_group']),
                );
            }
    
            if (!empty($_GET['arsol_app_blueprint_tag'])) {
                $tax_query[] = array(
                    'taxonomy' => 'arsol_app_blueprint_tag',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field($_GET['arsol_app_blueprint_tag']),
                );
            }
    
            if (!empty($tax_query)) {
                $query->set('tax_query', $tax_query);
            }
        }
    }

    public function modify_app_blueprint_actions($actions, $post) {
        if ($post->post_type === 'arsol_app_blueprint') {
            // Remove existing delete action
            unset($actions['delete']);
            
            // Check if user is admin, deletion is allowed, and no valid subscription is associated
            $subscription_id = get_post_meta($post->ID, 'arsol_app_blueprint_subscription_id', true);
            $subscription = $subscription_id ? wcs_get_subscription($subscription_id) : false;
            if (current_user_can('administrator') && get_option('arsol_allow_admin_app_blueprint_deletion', false) && (!$subscription_id || !$subscription)) {
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

    public function modify_app_blueprint_capabilities() {
        if (current_user_can('administrator') && get_option('arsol_allow_admin_app_blueprint_deletion', false)) {
            $role = get_role('administrator');
            $role->add_cap('delete_arsol_app_blueprints');
            $role->add_cap('delete_published_arsol_app_blueprints');
        }
    }

    public function my_column_width() {
        echo '<style type="text/css">
                .column-arsol-app-blueprint-status .column-details { width: 400px !important; overflow: hidden; }
              </style>';
    }

    /**
     * Load WooCommerce and WooCommerce Subscriptions styles on the app blueprints table page.
     */
    public function load_woocommerce_styles($hook) {
        global $typenow;
        if ($typenow === 'arsol_app_blueprint' && $hook === 'edit.php') {
            wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
            wp_enqueue_style('wcs_admin_styles', plugin_dir_url(WC_Subscriptions::$plugin_file) . 'assets/css/admin.css', array(), WC_Subscriptions::$version);
        }
    }

    // Other methods remain unchanged

}
