<div class="wrap">
    <h1><?php _e('SIYA API Keys', 'arsol_siya'); ?></h1>
    <p><?php _e('Configure your API settings below. Ensure all fields are filled correctly.', 'arsol_siya'); ?></p>
    
    <form method="post" action="options.php">
        <?php settings_fields('api-settings-group'); ?>

        <h2><?php _e('Server Managers', 'arsol_siya'); ?></h2>
        <table class="form-table">
            <?php 
            $manager_fields = apply_filters('siya_server_managers_fields', []);
            foreach ($manager_fields as $key => $field) : 
            ?>
            <tr>
                <th><?php echo esc_html($field['label']); ?></th>
                <td>
                    <textarea 
                        name="<?php echo esc_attr($key . '_api_key'); ?>" 
                        style="width: 100%; max-width: 400px; height: 116px; resize: none; overflow: auto;"
                    ><?php echo esc_textarea($field['value']); ?></textarea>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2><?php _e('Server Providers', 'arsol_siya'); ?></h2>
        <table class="form-table">
            <?php 
            $provider_fields = apply_filters('siya_server_providers_fields', []);
            foreach ($provider_fields as $key => $field) : 
            ?>
            <tr>
                <th><?php echo esc_html($field['label']); ?></th>
                <td>
                    <textarea 
                        name="<?php echo esc_attr($key . '_api_key'); ?>" 
                        style="width: 100%; max-width: 400px; height: 116px; resize: none; overflow: auto;"
                    ><?php echo esc_textarea($field['value']); ?></textarea>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
