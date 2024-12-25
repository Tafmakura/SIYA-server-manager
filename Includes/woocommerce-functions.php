<?php

// Hook into the WooCommerce Subscription status change to provision server
function create_runcloud_server_on_activate($subscription) {
    $subscription_id = $subscription->get_id();
    $post_id = get_post_meta($subscription_id, '_arsol_server_post_id', true);

    if (!$post_id) {
        return;
    }

    // Check if the server has already been provisioned
    $server_deployed = get_post_meta($post_id, 'arsol_server_deployed', true);
    if ($server_deployed) {
        return; // Skip if server is already deployed
    }

    // Provision and register server
    $result = provision_and_register_server($subscription_id, $post_id);
    
    if ($result) {
        // If successful, update subscription status
        update_post_meta($post_id, 'arsol_server_deployed', '1');
    } else {
        // If failed, update status to "on-hold"
        $subscription->update_status('on-hold');
    }
}

// Force "on-hold" if server deployment is incomplete and check server status
function force_on_hold_and_check_server_status($subscription) {
    $subscription_id = $subscription->get_id();
    $post_id = get_post_meta($subscription_id, '_arsol_server_post_id', true);

    if (!$post_id) {
        return;
    }

    // Check if the server is deployed and connected
    $server_deployed = get_post_meta($post_id, 'arsol_server_deployed', true);
    $server_connected = get_post_meta($post_id, 'arsol_server_connected', true);

    // If the server isn't deployed or isn't connected, force subscription to "on-hold"
    if (!$server_deployed || !$server_connected) {
        $subscription->update_status('on-hold');
    }

    // Optionally, refresh the subscription status periodically
    add_action('wp_footer', 'refresh_subscription_status_periodically', 99);
}

// Auto-refresh the subscription status
function refresh_subscription_status_periodically() {
    ?>
    <script type="text/javascript">
        setTimeout(function() {
            location.reload();
        }, 30000); // Refresh every 30 seconds
    </script>
    <?php
}
