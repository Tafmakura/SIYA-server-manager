<div class="wrap">
    <h1><?php _e('SIYA (Server Integration & Yield Augmentation)', 'arsol_siya'); ?></h1>
    <p><?php _e('Configure your API settings below. Ensure all fields are filled correctly.', 'arsol_siya'); ?></p>
    <form method="post" action="options.php">
        <?php
        settings_fields('api-settings-group');
        do_settings_sections('api-settings');
        submit_button();
        ?>
    </form>
</div>
