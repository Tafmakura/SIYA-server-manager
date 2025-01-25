<?php 

function arsol_sub_component_status_subscription_page($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);

    
    $subscription = wcs_get_subscription($subscription_id);

    ?>
    <h3>Server</h3>
    <p class="form-field form-field-wide">
        <label for="arsol-server-status">Server</label>
        <a href="<?php echo esc_url(get_edit_post_link($server_post_id)); ?>"><?php echo $server_post_id; ?></a>
        <div class="arsol-server-status">
            <?php arsol_sub_component_status_pill_simple($server_post_id); ?>
        </div>
    <style>
        .server-status.active { background: #c6e1c6; color: #5b841b; }
        .server-status.building { background: #f8dda7; color: #94660c; }
        .server-status.error { background: #eba3a3; color: #761919; }
        .server-status.repairing { background: #c8d7e1; color: #194d70; }
        .server-status.no-server { background: #e5e5e5; color: #777; }
    </style>
<?php }

