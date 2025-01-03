<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('siya_slugs_settings'); ?>
        
        <!-- Server Manager Section -->
        <h2>Server Manager Slugs</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Dashboard Slug</th>
                <td>
                    <input type="text" name="siya_server_dashboard_slug" 
                           value="<?php echo esc_attr(get_option('siya_server_dashboard_slug', 'server-dashboard')); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Servers List Slug</th>
                <td>
                    <input type="text" name="siya_servers_list_slug" 
                           value="<?php echo esc_attr(get_option('siya_servers_list_slug', 'servers')); ?>" />
                </td>
            </tr>
        </table>

        <!-- WordPress Plan Section -->
        <h2>WordPress Plan Slugs</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Plans List Slug</th>
                <td>
                    <input type="text" name="siya_wp_plans_slug" 
                           value="<?php echo esc_attr(get_option('siya_wp_plans_slug', 'wordpress-plans')); ?>" />
                </td>
            </tr>
        </table>

        <!-- Server Provider Plans Section -->
        <h2>Server Provider Plan Slugs</h2>
        
        <!-- DigitalOcean Subsection -->
        <h3>DigitalOcean</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Provider Name Slug</th>
                <td>
                    <input type="text" value="digitalocean" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Provider Plans Slug</th>
                <td>
                    <input type="text" name="siya_digitalocean_plans_slug" 
                           value="<?php echo esc_attr(get_option('siya_digitalocean_plans_slug', 'digitalocean-plans')); ?>" />
                </td>
            </tr>
        </table>

        <!-- Hetzner Subsection -->
        <h3>Hetzner</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Provider Name Slug</th>
                <td>
                    <input type="text" value="hetzner" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Provider Plans Slug</th>
                <td>
                    <input type="text" name="siya_hetzner_plans_slug" 
                           value="<?php echo esc_attr(get_option('siya_hetzner_plans_slug', 'hetzner-plans')); ?>" />
                </td>
            </tr>
        </table>

        <!-- Vultr Subsection -->
        <h3>Vultr</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Provider Name Slug</th>
                <td>
                    <input type="text" value="vultr" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Provider Plans Slug</th>
                <td>
                    <input type="text" name="siya_vultr_plans_slug" 
                           value="<?php echo esc_attr(get_option('siya_vultr_plans_slug', 'vultr-plans')); ?>" />
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
