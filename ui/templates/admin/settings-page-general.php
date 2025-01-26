<div class="wrap">
    <h1>SIYA Server Manager</h1>
    <p>Welcome to SIYA Server Manager. Use this plugin to manage your RunCloud servers.</p>
   
    <form method="post" action="options.php">
        <?php settings_fields('siya_settings_general'); ?>
        <?php do_settings_sections('siya_settings_general'); ?>
        <label for="arsol_allow_admin_server_delition">
            Allow server deletion by admin
        </label>
        <input
            type="checkbox"
            name="arsol_allow_admin_server_delition"
            id="arsol_allow_admin_server_delition"
            value="1"
            <?php checked(get_option('arsol_allow_admin_server_delition'), 1); ?>
            class="woocommerce-input-toggle--enabled"
        />
        <input type="hidden" name="arsol_allowed_server_types[]" value="sites_server" />
        <h2>Allowed server types</h2>
        <?php
        $saved_types = (array) get_option('arsol_allowed_server_types', []);
        $all_types = [
            'sites_server'          => 'Sites Server',
            'application_server'    => 'Application Server',
            'block_storage_server'  => 'Block Storage Server',
            'cloud_server'          => 'Cloud Server',
            'email_server'          => 'Email Server',
            'object_storage_server' => 'Object Storage Server',
            'vps_server'            => 'VPS Server',
        ];
        foreach ($all_types as $type => $label) {
            $checked = in_array($type, $saved_types) || $type === 'sites_server' ? 'checked' : '';
            $disabled = $type === 'sites_server' ? 'disabled' : '';
            echo '<label><input type="checkbox" name="arsol_allowed_server_types[]" value="' 
                 . esc_attr($type) . '" ' . $checked . ' ' . $disabled . '> ' . esc_html($label) . '</label><br>';
        }
        ?>
        <?php submit_button(); ?>
    </form>
</div>
