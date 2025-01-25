<?php 

function arsol_sub_component_status_pill_simple($server_post_id){

    $subscription_id = get_post_meta($server_post_id, 'arsol_server_subscription_id', true);

    
    $subscription = wcs_get_subscription($subscription_id);

    if (!$server_post_id) {
        echo '<div style="text-align:center"><span>N/A</span></div>';
        return false;
    }

    if (!$subscription) {
        echo '<mark class="subscription-status order-status server-status status-no-server no-server tips"><span>No Server</span></mark>';
        return false;
    }

    $server_post_id = $subscription->get_meta('arsol_linked_server_post_id', true);
    $circuit_breaker = get_post_meta($server_post_id, '_arsol_state_00_circuit_breaker', true);

    $server_actions = array();
    $server_actions[] = '<a href="' . esc_url(get_edit_post_link($server_post_id)) . '">View</a>'; // Always show View
    
    if ($circuit_breaker == -1) {
        $status = 'error';
        $label = 'Error';
        $repair_url = wp_nonce_url(admin_url('admin-post.php?action=repair&subscription_id=' . $subscription->get_id()), 'repair_nonce');
        $server_actions[] = '<a href="' . esc_url($repair_url) . '" class="repair-server">Repair</a>';
    } elseif ($circuit_breaker == 1) {
        $status = 'on-hold';
        $label = 'Repair';
    } elseif ($circuit_breaker == 0) {
        $status = 'active';
        $label = 'Live';
        $reboot_url = wp_nonce_url(admin_url('admin-post.php?action=reboot&subscription_id=' . $subscription->get_id()), 'reboot_nonce');
        $server_actions[] = '<a href="' . esc_url($reboot_url) . '" class="reboot-server">Reboot</a>';
    } else {
        $status = 'pending';
        $label = 'Setup';
    }
    ?>
    <mark class="subscription-status order-status server-status status-<?php echo esc_attr($status); ?> tips">
        <span><?php echo esc_html($label); ?></span>
    </mark>
    <div class="row-actions">
        <?php echo implode(' | ', $server_actions); ?>
    </div>
    <style>
        .server-status.okay { background: #d4edda; color: #155724; }
        .server-status.setup { background: #fff3cd; color: #856404; }
        .server-status.error { background: #f8d7da; color: #721c24; }
        .server-status.repair { background: #d1ecf1; color: #0c5460; }
        .server-status.no-server { background: #e2e3e5; color: #6c757d; }
    </style>
<?php }

