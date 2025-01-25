<?php 

function arsol_sub_component_status_pill_simple($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);

    
    $subscription = wcs_get_subscription($subscription_id);

    if (!$server_post_id || !$subscription_id) {    
        echo '<div><span>N/A</span></div>';
        return false;
    }

    $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

    $server_actions = array();
    $server_actions[] = '<a href="' . esc_url(get_edit_post_link($server_post_id)) . '">View</a>'; // Always show View
    
    if ($circuit_breaker == -1) {
        $status = 'error';
        $label = 'Error';
        $repair_url = wp_nonce_url(admin_url('admin-post.php?action=repair&subscription_id=' . $subscription->get_id()), 'repair_nonce');
        $server_actions[] = '<a href="' . esc_url($repair_url) . '" class="repair-server">Repair</a>';
    } elseif ($circuit_breaker == 1) {
        $status = 'repairing';
        $label = 'Repairing';
    } elseif ($circuit_breaker == 0) {
        $status = 'active';
        $label = 'Active';
        $reboot_url = wp_nonce_url(admin_url('admin-post.php?action=reboot&subscription_id=' . $subscription->get_id()), 'reboot_nonce');
        $server_actions[] = '<a href="' . esc_url($reboot_url) . '" class="reboot-server">Reboot</a>';
    } else {
        $status = 'building';
        $label = 'Building';
    }
    ?>
    <mark class="order-status server-status <?php echo esc_attr($status); ?> tips">
        <span><?php echo esc_html($label); ?></span>
    </mark>
    <?php if (!is_singular('shop_subscription')): ?>
        <div class="row-actions">
            <?php echo implode(' | ', $server_actions); ?>
        </div>
    <?php endif; ?>
    <style>
        .server-status.active { background: #c6e1c6; color: #5b841b; }
        .server-status.building { background: #f8dda7; color: #94660c; }
        .server-status.error { background: #eba3a3; color: #761919; }
        .server-status.repairing { background: #c8d7e1; color: #194d70; }
        .server-status.no-server { background: #e5e5e5; color: #777; }
    </style>
<?php }

