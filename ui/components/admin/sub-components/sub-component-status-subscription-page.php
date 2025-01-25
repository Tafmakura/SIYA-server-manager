<?php 

function arsol_sub_component_status_subscription_page($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
    $subscription = wcs_get_subscription($subscription_id);?>
  
    <p class="form-field form-field-wide arsol-server-status" style="margin-top: 1em;" ><p>
    <h3>Server</h3>
    <p class="form-field form-field-wide arsol-server-status-pill" >
    <p><strong>Important: </strong><?php echo esc_html__("A Server state represents the provisioning status of a server and not its current power state.", 'arsol-server-manager'); ?></p>
    <p class="form-field form-field-wide">Server status: <a href="<?php echo esc_url(get_edit_post_link($server_post_id)); ?>">ARSOL<?php echo $subscription_id; ?> →</a></p>
    <p class="form-field form-field-wide arsol-server-status-pill" style="margin-top: 10px;">
        <?php arsol_sub_component_status_pill_simple($server_post_id); ?>
    </p>
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
        .row-action {
            display: none !important;
        }
    </style>

<?php }

