<?php 

function arsol_sub_component_status_subscription_page($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
    $subscription = wcs_get_subscription($subscription_id);?>
  
    <p class="form-field form-field-wide arsol-server-status" style="margin-top: 1em;" ><p>
    <h3>Server status</h3>
    <p class="form-field form-field-wide arsol-server-status-pill" >
    <p>Possible server states states are: 
    <strong>Active</strong> (server installed correctly), 
    <strong>Building</strong> (server is setting up for the first time), 
    <strong>Repairing</strong> (server is attempting an auto-repair procesdure),
    <strong>Error</strong> (Server did not install correctly, requires attention).</p>
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
        .arsol-row-action {
            display: none !important;
        }

    </style>

<?php }

