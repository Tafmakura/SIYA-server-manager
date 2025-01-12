<!--  This is a template file for the SSH Keys settings page. -->
<div class="wrap">
    <h1><?php _e('SSH Keys', 'arsol_siya'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('siya_settings_ssh');
        do_settings_sections('siya_settings_ssh');
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><?php _e('SSH Username', 'arsol_siya'); ?></th>
                <td><input type="text" value="Server name (e.g. ARSOLXXXX)" style="width: auto;" disabled /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('SSH Private Key', 'arsol_siya'); ?></th>
                <td><textarea name="ssh_private_key" rows="10" cols="50"><?php echo esc_textarea(get_option('ssh_private_key')); ?></textarea></td>
            </tr>
            <tr valign="top">
                <th scope="row"><?php _e('SSH Public Key', 'arsol_siya'); ?></th>
                <td><textarea name="ssh_public_key" rows="10" cols="50"><?php echo esc_textarea(get_option('ssh_public_key')); ?></textarea></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>