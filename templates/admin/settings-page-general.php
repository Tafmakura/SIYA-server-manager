<div class="wrap">
    <h1>SIYA Server Manager</h1>
    <p>Welcome to SIYA Server Manager. Use this plugin to manage your RunCloud servers.</p>
    
    <div class="card">
        <h2>Quick Overview</h2>
        <p>Configure your RunCloud API settings in the API Settings submenu to get started.</p>
    </div>

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
            class="toggle"
        />
        <?php submit_button(); ?>
    </form>
</div>
