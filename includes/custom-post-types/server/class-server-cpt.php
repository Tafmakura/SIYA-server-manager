<?php

namespace SIYA\CustomPostTypes;

class Server {

    private $server_internal_id;
    private $server_deployed_id;
    private $server_provisioned_id;
    private $server_name;
    private $server_type;
    private $server_os;
    private $server_os_version;
    private $server_region;
    private $server_deployed_status;
    private $server_deployed_date;
    private $server_manager;
    private $server_provisoned_status;
    private $server_provisioned_date;
    private $server_provider;
    private $server_status;
    private $server_status_date;
    private $server_ipv4;
    private $server_ipv6;
    private $server_size;
    private $server_image;
    private $server_backup_enabled;
    private $server_backup_schedule;
    private $server_backup_retention;
    private $server_monitoring_enabled;
    private $server_monitoring_interval;


    public function __construct() {
        $this->register_cpt();
    }

    /**
     * Registers a custom post type for servers.
     */
    public function register_cpt() {
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
            'supports'           => array('title','editor','author','thumbnail','excerpt','comments')
        );

        register_post_type('server', $args);
    }
}