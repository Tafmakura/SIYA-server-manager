<?php 
//Delete

function provision_and_register_server($subscription_id, $post_id) {
    error_log('provision_and_register_server called for subscription ID: ' . $subscription_id . ', post ID: ' . $post_id);
    
    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription || is_wp_error($subscription)) {
        $subscription->add_order_note(
            __('Failed to retrieve subscription object in provision_and_register_server.', 'your-text-domain')
        );
        return false;
    }

    // Ensure the post ID is present
    if (empty($post_id)) {
        $subscription->add_order_note(
            __('Post ID is empty in provision_and_register_server. Skipping provisioning.', 'your-text-domain')
        );
        return false;
    }

    // Step 1: Provision server with Hetzner and get IP address
    $server_details = provision_server_with_hetzner($subscription_id, $post_id);
    if (!$server_details) {
        $subscription->add_order_note(
            __('Failed to provision server with Hetzner.', 'your-text-domain')
        );
        return false;
    }

    // Set arsol_server_deployed to 1
    update_post_meta($post_id, 'arsol_server_deployed', 1);

    // Get IP address from custom post type
    $ip_address = get_post_meta($post_id, 'arsol_server_ip', true);

    // Get server name from custom post type
    $server_name = get_post_meta($post_id, 'arsol_server_deployed_name', true);

    // Step 2: Register server with RunCloud
    $result = register_server_with_runcloud($subscription_id, $ip_address, $server_name, $post_id);

    // If RunCloud registration is successful, set arsol_server_connected to 1
    if ($result) {
        update_post_meta($post_id, 'arsol_server_connected', 1);
    }

    return $result;
}

function provision_server_with_hetzner($subscription_id, $post_id) {
    $api_key = get_option('hetzner_api_key');
    $data = array(
        'name' => 'ars-' . date('y-m-d') . '-' . $subscription_id,  // Ensure this is a valid hostname
        'server_type' => 'cx22',     // Correct field name
        'image' => 'ubuntu-20.04',
        'location' => 'fsn1',
        'public_net' => array(
            'enable_ipv4' => true,
        ),
    );

    $response = wp_remote_post('https://api.hetzner.cloud/v1/servers', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($data),
    ));

    // Log the response from Hetzner API
    error_log('Hetzner API response: ' . wp_remote_retrieve_body($response));

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 201) {
        $response_body = wp_remote_retrieve_body($response);
        $server_data = json_decode($response_body, true);

        // Update server post with key details using the prefix arsol_server_
        update_post_meta($post_id, 'arsol_server_id', sanitize_text_field($server_data['server']['id']));
        update_post_meta($post_id, 'arsol_server_ip', sanitize_text_field($server_data['server']['public_net']['ipv4']['ip']));
        update_post_meta($post_id, 'arsol_server_ipv6', sanitize_text_field($server_data['server']['public_net']['ipv6']['ip'] ?? ''));
        update_post_meta($post_id, 'arsol_server_creation_date', sanitize_text_field($server_data['server']['created']));
        update_post_meta($post_id, 'arsol_server_status', sanitize_text_field($server_data['server']['status']));
        update_post_meta($post_id, 'arsol_server_root_password', sanitize_text_field($server_data['root_password']));
        update_post_meta($post_id, 'arsol_server_deployed_name', sanitize_text_field($server_data['server']['name']));

        // Add note to subscription about successful server creation
        $subscription = wcs_get_subscription($subscription_id);
        $subscription->add_order_note(
            sprintf(
                __('Successfully provisioned Hetzner server with IP: %s', 'your-text-domain'),
                $server_data['server']['public_net']['ipv4']['ip']
            )
        );

        // Log the server data received
        error_log('Hetzner server data: ' . print_r($server_data, true));

        return array(
            'ip_address' => $server_data['server']['public_net']['ipv4']['ip'],
            'server_id' => $server_data['server']['id'],
        );
    }

    // Log the error message and add note to the subscription
    $GLOBALS['hetzner_api_error'] = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
    error_log('Error provisioning server with Hetzner: ' . $GLOBALS['hetzner_api_error']);

    $subscription = wcs_get_subscription($subscription_id);
    $subscription->add_order_note(
        sprintf(
            __('Failed to provision Hetzner server. Error: %s', 'your-text-domain'),
            $GLOBALS['hetzner_api_error']
        )
    );

    return false;
}

function register_server_with_runcloud($subscription_id, $ip_address, $server_name, $post_id) {
    error_log('register_server_with_runcloud called for subscription ID: ' . $subscription_id . ', post ID: ' . $post_id);

    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription || is_wp_error($subscription)) {
        error_log('Failed to retrieve subscription object.');
        return false;
    }

    // Sanitize the server name and remove hyphens
    $server_name_sanitized = sanitize_text_field($server_name);
    $server_name_sanitized = str_replace('-', '', $server_name_sanitized);

    // Get API key from settings
    $api_key = get_option('runcloud_api_key');

    // Prepare the data for the RunCloud API request
    $data = array(
        'name' => $server_name_sanitized,
        'ipAddress' => $ip_address,
    );

    // Make the API request to RunCloud
    $response = wp_remote_post('https://manage.runcloud.io/api/v3/servers', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($data),
    ));

    // Log the response from RunCloud API
    error_log('RunCloud API response: ' . wp_remote_retrieve_body($response));
    $subscription->add_order_note(
        __('[runcloud-1] RunCloud API response: ' . wp_remote_retrieve_body($response), 'your-text-domain')
    );

    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
        $response_body = wp_remote_retrieve_body($response);
        $server_data = json_decode($response_body, true);

        // Log the server data received
        error_log('RunCloud server data: ' . print_r($server_data, true));
        $subscription->add_order_note(
            __('[runcloud-2] RunCloud server data: ' . print_r($server_data, true), 'your-text-domain')
        );

        // Update server post with additional data using the prefix arsol_server_
        update_post_meta($post_id, 'arsol_server_runcloud_id', sanitize_text_field($server_data['id']));
        update_post_meta($post_id, 'arsol_server_runcloud_ip', sanitize_text_field($server_data['ipAddress']));
        update_post_meta($post_id, 'arsol_server_status', 'active');
        update_post_meta($post_id, 'arsol_server_connected', 1);  // Set connected to 1
        update_post_meta($post_id, 'arsol_server_deployment_success', '1');

        // Add note to subscription about successful server registration
        $subscription->add_order_note(
            __('Successfully provisioned server and connected to RunCloud.', 'your-text-domain')
        );

        return true; // Success
    }

    // Log the error message and add note to the subscription
    $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
    $error_details = wp_remote_retrieve_body($response);
    $GLOBALS['runcloud_api_error'] = $error_message . ': ' . $error_details;
    error_log('Error creating server with RunCloud: ' . $GLOBALS['runcloud_api_error']);
    $subscription->add_order_note(
        __('[runcloud-3] Error creating server with RunCloud: ' . $GLOBALS['runcloud_api_error'], 'your-text-domain')
    );

    $subscription->add_order_note(
        sprintf(
            __('Failed to register server with RunCloud. Error: %s', 'your-text-domain'),
            $GLOBALS['runcloud_api_error']
        )
    );

    return false; // Failure
}


add_action('woocommerce_subscription_status_pending_to_active', 'create_runcloud_server_on_activate', 20, 1);

function create_runcloud_server_on_activate($subscription) {
    $subscription_id = $subscription->get_id();
    $post_id = get_post_meta($subscription_id, 'arsol_server_post_id', true);
    $subscription->add_order_note(
        __('[create-activate-1] Retrieved server post ID: ' . $post_id . ' for subscription ID: ' . $subscription_id, 'your-text-domain')
    );

    if (!$post_id) {
        $subscription->add_order_note(
            __('[create-activate-2] Creating new server post for subscription ID: ' . $subscription_id, 'your-text-domain')
        );

        $post_id = wp_insert_post(array(
            'post_title' => 'ars-' . date('y-m-d') . '-' . $subscription_id,
            'post_content' => 'Server details for subscription #' . $subscription_id,
            'post_status' => 'publish',
            'post_type' => 'server',
        ));

        if (is_wp_error($post_id)) {
            $error_message = $post_id->get_error_message();
            $subscription->add_order_note(
                __('[create-activate-3] Failed to create server post. Error: ' . $error_message, 'your-text-domain')
            );
            return;
        } else {
            $subscription->add_order_note(
                __('[create-activate-4] Successfully created server post with ID: ' . $post_id, 'your-text-domain')
            );

            update_post_meta($post_id, 'arsol_server_deployed', 0);
            update_post_meta($post_id, 'arsol_server_connected', 0);

            update_post_meta($subscription_id, 'arsol_server_post_id', $post_id);
            update_post_meta($post_id, 'arsol_subscription_id', $subscription_id);

            $subscription->add_order_note(
                __('[create-activate-5] Successfully created server entity.', 'your-text-domain')
            );
        }
    } else {
        $subscription->add_order_note(
            __('[create-activate-6] Server post already exists for subscription ID: ' . $subscription_id, 'your-text-domain')
        );
    }

    $server_deployed = get_post_meta($post_id, 'arsol_server_deployed', true);
    $server_connected = get_post_meta($post_id, 'arsol_server_connected', true);
    $subscription->add_order_note(
        __('[create-activate-7] Server deployed: ' . $server_deployed . ', server connected: ' . $server_connected, 'your-text-domain')
    );

    if ($server_deployed != 1 || $server_connected != 1) {
        $result = provision_and_register_server($subscription_id, $post_id);

        if (!$result) {
            $subscription->add_order_note(
                __('[create-activate-8] Failed to provision and register server.', 'your-text-domain')
            );

            $subscription->update_status('on-hold');
        } else {
            $subscription->add_order_note(
                __('[create-activate-9] Successfully provisioned and registered server.', 'your-text-domain')
            );
        }
    } else {
        $subscription->add_order_note(
            __('[create-activate-10] Server already deployed and connected.', 'your-text-domain')
        );
    }

    $parent_order = $subscription->get_parent();
    if ($parent_order) {
        $parent_order->update_status('completed');
    }
}




add_action('woocommerce_subscription_status_active', 'force_on_hold_and_check_server_status', 10, 1);

function force_on_hold_and_check_server_status($subscription_id) {
    if (!is_admin()) {
        return;
    }

    $subscription = wcs_get_subscription($subscription_id);

    if (!$subscription || is_wp_error($subscription)) {
        error_log('Failed to retrieve subscription object.');
        return;
    }

    $timestamp = current_time('mysql');
    $subscription_id = $subscription->get_id();
    $subscription->add_order_note(
        __('[on-hold-check-1] Subscription status changed to active. Time: ' . $timestamp, 'your-text-domain')
    );

    $post_id = get_post_meta($subscription_id, 'arsol_server_post_id', true);
    error_log('force_on_hold_and_check_server_status: Retrieved server post ID: ' . $post_id . ' for subscription ID: ' . $subscription_id);
    $subscription->add_order_note(
        __('[on-hold-check-2] Retrieved server post ID: ' . $post_id . ' for subscription ID: ' . $subscription_id . '. Time: ' . $timestamp, 'your-text-domain')
    );

    if ($post_id) {
        $server_deployed = get_post_meta($post_id, 'arsol_server_deployed', true);
        $server_connected = get_post_meta($post_id, 'arsol_server_connected', true);
        error_log('force_on_hold_and_check_server_status: Server deployed: ' . $server_deployed . ', server connected: ' . $server_connected);
        $subscription->add_order_note(
            __('[on-hold-check-3] Server deployed: ' . $server_deployed . ', server connected: ' . $server_connected . '. Time: ' . $timestamp, 'your-text-domain')
        );

        // Check if either field is 0 or null
        if ($server_deployed != 1) {
            // Change status to "on-hold"
            $subscription->update_status('on-hold');
            error_log('Status changed to on-hold due to incomplete server deployment.');
            $subscription->add_order_note(
                __('[on-hold-check-4] Status changed to on-hold due to incomplete server deployment. Time: ' . $timestamp, 'your-text-domain')
            );

            // Add a note with the server post ID
            $subscription->add_order_note(
                __('[on-hold-check-5] Status set to on-hold. Server post ID: ' . $post_id . '. Time: ' . $timestamp, 'your-text-domain')
            );

            // Redeploy and register the server
            $result = provision_and_register_server($subscription_id, $post_id);

            if (!$result) {
                error_log('force_on_hold_and_check_server_status: Failed to provision and register server.');
                // Add a subscription note with the specific error message
                $subscription->add_order_note(
                    sprintf(
                        __('[on-hold-check-6] %s. Time: %s', 'your-text-domain'),
                        $GLOBALS['hetzner_api_error'] ?? $GLOBALS['runcloud_api_error'],
                        $timestamp
                    )
                );
            } else {
                error_log('force_on_hold_and_check_server_status: Successfully provisioned and registered server.');
                // Add a note about the successful server creation
                $subscription->add_order_note(
                    __('[on-hold-check-7] Successfully provisioned server and connected to RunCloud. Time: ' . $timestamp, 'your-text-domain')
                );

                // Change status back to active if successful
                $subscription->update_status('active');
            }
        } else if ($server_connected != 1) {
            // Reconnect to RunCloud
            $result = register_server_with_runcloud($subscription_id, get_post_meta($post_id, 'arsol_server_ip', true), get_post_meta($post_id, 'arsol_server_deployed_name', true), $post_id);

            if (!$result) {
                $runcloud_error = isset($GLOBALS['runcloud_api_error']) ? $GLOBALS['runcloud_api_error'] : 'Unknown error';
                error_log('force_on_hold_and_check_server_status: Failed to reconnect to RunCloud. Error: ' . $runcloud_error);
                // Add a subscription note with the specific error message
                $subscription->add_order_note(
                    sprintf(
                        __('[on-hold-check-8] RunCloud error: %s. Time: %s', 'your-text-domain'),
                        $runcloud_error,
                        $timestamp
                    )
                );

                // Change status to "on-hold"
                $subscription->update_status('on-hold');
            } else {
                error_log('force_on_hold_and_check_server_status: Successfully reconnected to RunCloud.');
                // Add a note about the successful server creation
                $subscription->add_order_note(
                    __('[on-hold-check-9] Successfully reconnected to RunCloud. Time: ' . $timestamp, 'your-text-domain')
                );

                // Change status back to active if successful
                $subscription->update_status('active');
            }
        } else {
            error_log('Server already deployed and connected.');
            $subscription->add_order_note(
                __('[on-hold-check-10] Server already deployed and connected. Time: ' . $timestamp, 'your-text-domain')
            );
        }
    } else {
        error_log('Server post ID not found for subscription ID: ' . $subscription_id);
        // Change status to "on-hold" and add a note
        $subscription->update_status('on-hold');
        $subscription->add_order_note(
            __('[on-hold-check-11] Server post ID not found, status set to on-hold. Time: ' . $timestamp, 'your-text-domain')
        );
    }

    // Refresh the page after processing
    echo "<script type='text/javascript'>
            setTimeout(function(){
                location.reload();
            }, 1000);
          </script>";
}

// ...existing code...
function update_server_status($post_id, $status) {
    $server = new ServerPost($post_id);
    $meta_data = [
        '_arsol_state_10_provisioning' => $status,
        'arsol_server_status_date' => current_time('mysql')
    ];
    $server->update_meta_data($post_id, $meta_data);
}
// ...existing code...