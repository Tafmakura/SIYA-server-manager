<?php

namespace SIYA\CustomPostTypes;

class ServerPostSetup {

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
        add_action('manage_server_posts_custom_column', array($this, 'populate_custom_columns'), 10, 2);
        add_action('edit_form_top', array($this, 'display_custom_title'));
        add_filter('gettext', array($this, 'change_published_to_provisioned'), 10, 3);
        add_action('admin_head', array($this, 'remove_preview_button'));


        // Add new filters
        add_action('restrict_manage_posts', array($this, 'add_server_filters'));
        add_filter('pre_get_posts', array($this, 'filter_servers_by_taxonomy'));
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

 

    public function populate_custom_columns($column, $post_id) {
        if ($column === 'details') {
            // Get the associated subscription ID
            $subscription_id = get_post_meta($post_id, 'arsol_server_subscription_id', true);
    
            if ($subscription_id) {

                // Get the subscription object
                $subscription = wcs_get_subscription($subscription_id);

                // Get the customer ID
                $customer_id = $subscription->get_customer_id();
    
                if ($subscription) {
                    // Get billing name (first and last)
                    $billing_first_name = $subscription->get_billing_first_name();
                    $billing_last_name = $subscription->get_billing_last_name();
                    
                    // Check if the billing name exists, if not, use the profile name or username
                    if ($billing_first_name && $billing_last_name) {
                        $billing_name = $billing_first_name . ' ' . $billing_last_name;
                    } else {
                        // Fallback to user's display name or username if billing name is missing
                        $user = get_userdata($customer_id);
                        
                        // Use display name if available, otherwise fallback to username
                        $billing_name = $user ? $user->display_name : ( $user ? $user->user_login : __('No customer found', 'your-text-domain') );
                    }
    
                    // Generate links for subscription and customer
                    $subscription_link = get_edit_post_link($subscription_id);
                    $customer_wc_link = admin_url('user-edit.php?user_id=' . $customer_id);
    
                    // Render the column content
                    echo sprintf(
                        __('Assigned server post id: #%d for subscription: <strong><a href="%s">#%s</a></strong> associated with customer: <a href="%s">%s</a>', 'your-text-domain'),
                        $post_id,
                        esc_url($subscription_link),
                        esc_html($subscription_id),
                        esc_url($customer_wc_link),
                        esc_html($billing_name)
                    );
                } else {
                    echo __('Invalid subscription', 'your-text-domain');
                }
            } else {
                echo __('No subscription found', 'your-text-domain');
            }
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
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ));
    
        echo '<select name="' . esc_attr($taxonomy) . '" id="' . esc_attr($taxonomy) . '" class="postform">';
        // Add custom reset option to reset the filter
        echo '<option value="">' . esc_html($reset_label) . '</option>';
    
        if (!empty($terms)) {
            foreach ($terms as $term) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($term->slug),
                    selected($selected, $term->slug, false),
                    esc_html($term->name)
                );
            }
        }
        echo '</select>';
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
    
    // Other methods remain unchanged

}