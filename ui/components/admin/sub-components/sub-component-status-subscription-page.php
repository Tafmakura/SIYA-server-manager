<?php 

function arsol_sub_component_status_subscription_page($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
    $subscription = wcs_get_subscription($subscription_id);?>

  
    <p class="form-field form-field-wide arsol-server-status" ><p>
    <h3>Server status</h3>
    <p class="form-field form-field-wide arsol-server-status-pill" style="margin-top: 10px;">
    <p><?php esc_html_e('An active status indicates the server was installed correctly and remains available, even when powered off. Servers in error state indicate installation issues and may be completely unavailable or operating with limited functionality.', 'arsol-server-manager'); ?></p>
        <?php arsol_sub_component_status_pill_simple($server_post_id); ?>
    <p>Server statuses indicate the current state of your server. Possible states are: 
    <strong>Active</strong> (server is running), 
    <strong>Inactive</strong> (server is stopped), 
    <strong>Suspended</strong> (subscription paused), or 
    <strong>Error</strong> (requires attention).</p>
    <p class="form-field form-field-wide arsol-server-status-pill" style="margin-top: 10px;">
        <?php arsol_sub_component_status_pill_simple($server_post_id); ?>
    </p>
    <p class="form-field form-field-wide">Server name: <a href="<?php echo esc_url(get_edit_post_link($server_post_id)); ?>">ARSOL<?php echo $subscription_id; ?></a></p>

    <style>
        .arsol-server-status {
            margin-top: 1em;
        }
        .arsol-server-status-pill {
            display: inline-block;
        }
        .arsol-server-status-pill {
            margin-top: 9px;
        }

    </style>

<?php }

