<?php

// Add a custom dashboard widget
function add_custom_dashboard_widget() {
    wp_add_dashboard_widget(
        'arsol_dashboard_widget', 
        'Server Integration Status', 
        'display_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'add_custom_dashboard_widget');

// Display widget content
function display_dashboard_widget() {
    echo '<h3>Server Integration Status</h3>';
    echo '<p>Check the current status of your server integrations.</p>';
}

// Enqueue custom admin styles
function enqueue_admin_styles() {
    wp_enqueue_style('arsol-admin-styles', plugin_dir_url(__FILE__) . '../assets/admin.css');
}
add_action('admin_enqueue_scripts', 'enqueue_admin_styles');
