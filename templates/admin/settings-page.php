<div class="wrap">
    <h1><?php _e('SIYA (Server Integration & Yield Augmentaion) ', 'your-text-domain'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('api-settings-group');
        do_settings_sections('api-settings');
        submit_button();
        ?>
    </form>
</div>

<?php
// API key field callback for RunCloud
function runcloud_api_key_field() {
    $api_key = get_option('runcloud_api_key');
    ?>
    <input type="text" name="runcloud_api_key" value="<?php echo esc_attr($api_key); ?>" />
    <?php
}

// API key field callback for Hetzner
function hetzner_api_key_field() {
    $api_key = get_option('hetzner_api_key');
    ?>
    <input type="text" name="hetzner_api_key" value="<?php echo esc_attr($api_key); ?>" />
    <?php
}
?>
