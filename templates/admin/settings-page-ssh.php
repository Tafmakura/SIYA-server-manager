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
                <td><input type="text" value="Server name (e.g. ARSOLXXXX)" style="min-width: 225px;" disabled /></td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>