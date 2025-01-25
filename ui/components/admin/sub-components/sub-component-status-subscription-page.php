<?php 

function arsol_sub_component_status_subscription_page($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);
    $subscription = wcs_get_subscription($subscription_id);?>

    <h3>Server</h3>
    <p class="form-field form-field-wide">
        <p class="description">Server name: <a href="<?php echo esc_url(get_edit_post_link($server_post_id)); ?>">#ARSOL<?php echo $subscription_id; ?></a></p>
        <div class="arsol-server-status" style="margin-top: 10px;">
            <?php arsol_sub_component_status_pill_simple($server_post_id); ?>
        </div>
    </p>
    <style>
        
    </style>

<?php }

