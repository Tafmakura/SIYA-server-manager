<div class="wrap">
    <h1><?php _e('SIYA (Server Integration & Yield Augmentation)', 'arsol_siya'); ?></h1>
    <p><?php _e('Configure your API settings below. Ensure all fields are filled correctly.', 'arsol_siya'); ?></p>
    <form method="post" action="options.php">
        <?php
        settings_fields('api-settings-group');
        do_settings_sections('api-settings');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('API Key', 'arsol_siya'); ?></th>
                <td><input type="text" name="api_key" value="<?php echo esc_attr(get_option('api_key')); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('API Secret', 'arsol_siya'); ?></th>
                <td><input type="text" name="api_secret" value="<?php echo esc_attr(get_option('api_secret')); ?>" /></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>
