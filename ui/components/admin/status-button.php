<?php
$circuit_breaker = isset($args['circuit_breaker']) ? $args['circuit_breaker'] : null;
$server_post_id = isset($args['server_post_id']) ? $args['server_post_id'] : null;
$subscription_id = isset($args['subscription_id']) ? $args['subscription_id'] : null;

if ($circuit_breaker === null || $server_post_id === null || $subscription_id === null) {
    echo '<mark class="subscription-status order-status server-status status-no-server no-server tips"><span>No Server</span></mark>';
    return;
}

$server_actions = array();
$server_actions[] = '<a href="' . esc_url(get_edit_post_link($server_post_id)) . '">View</a>'; // Always show View

if ($circuit_breaker == -1) {
    $status = 'error';
    $label = 'Error';
    $repair_url = wp_nonce_url(admin_url('admin-post.php?action=repair&subscription_id=' . $subscription_id), 'repair_nonce');
    $server_actions[] = '<a href="' . esc_url($repair_url) . '" class="repair-server">Repair</a>';
} elseif ($circuit_breaker == 1) {
    $status = 'on-hold';
    $label = 'Repair';
} elseif ($circuit_breaker == 0) {
    $status = 'active';
    $label = 'Live';
    $reboot_url = wp_nonce_url(admin_url('admin-post.php?action=reboot&subscription_id=' . $subscription_id), 'reboot_nonce');
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
    .server-status {
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 12px;
        font-weight: 500;
    }
    .server-status.okay { background: #c6e1c6; color: #5b841b; }
    .server-status.setup { background: #f8dda7; color: #94660c; }
    .server-status.error { background: #eba3a3; color: #761919; }
    .server-status.repair { background: #c8d7e1; color:rgb(24, 77, 112); }
    .server-status.no-server { background: #e5e5e5; color: #777; }
</style>
