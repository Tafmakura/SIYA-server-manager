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
                <th scope="row">Plans</th>
                <td>
                    <div class="plan-repeater" data-provider="digitalocean">
                        <?php
                        $digitalocean_plans = get_option('siya_digitalocean_plans', array());
                        if (!empty($digitalocean_plans)) {
                            foreach ($digitalocean_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <input type="text" name="siya_digitalocean_plans[<?php echo $index; ?>][slug]" 
                                           value="<?php echo esc_attr($plan['slug']); ?>" placeholder="Plan Slug" />
                                    <textarea name="siya_digitalocean_plans[<?php echo $index; ?>][description]" 
                                              maxlength="250" placeholder="Plan Description"><?php echo esc_textarea($plan['description']); ?></textarea>
                                    <button type="button" class="button remove-plan">Remove</button>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <input type="text" name="plan_slug[]" placeholder="Plan Slug" />
                            <textarea name="plan_description[]" maxlength="250" placeholder="Plan Description"></textarea>
                            <button type="button" class="button remove-plan">Remove</button>
                        </div>
                        <button type="button" class="button add-plan">Add Plan</button>
                    </div>
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
                <th scope="row">Plans</th>
                <td>
                    <div class="plan-repeater" data-provider="hetzner">
                        <?php
                        $hetzner_plans = get_option('siya_hetzner_plans', array());
                        if (!empty($hetzner_plans)) {
                            foreach ($hetzner_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <input type="text" name="siya_hetzner_plans[<?php echo $index; ?>][slug]" 
                                           value="<?php echo esc_attr($plan['slug']); ?>" placeholder="Plan Slug" />
                                    <textarea name="siya_hetzner_plans[<?php echo $index; ?>][description]" 
                                              maxlength="250" placeholder="Plan Description"><?php echo esc_textarea($plan['description']); ?></textarea>
                                    <button type="button" class="button remove-plan">Remove</button>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <input type="text" name="plan_slug[]" placeholder="Plan Slug" />
                            <textarea name="plan_description[]" maxlength="250" placeholder="Plan Description"></textarea>
                            <button type="button" class="button remove-plan">Remove</button>
                        </div>
                        <button type="button" class="button add-plan">Add Plan</button>
                    </div>
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
                <th scope="row">Plans</th>
                <td>
                    <div class="plan-repeater" data-provider="vultr">
                        <?php
                        $vultr_plans = get_option('siya_vultr_plans', array());
                        if (!empty($vultr_plans)) {
                            foreach ($vultr_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <input type="text" name="siya_vultr_plans[<?php echo $index; ?>][slug]" 
                                           value="<?php echo esc_attr($plan['slug']); ?>" placeholder="Plan Slug" />
                                    <textarea name="siya_vultr_plans[<?php echo $index; ?>][description]" 
                                              maxlength="250" placeholder="Plan Description"><?php echo esc_textarea($plan['description']); ?></textarea>
                                    <button type="button" class="button remove-plan">Remove</button>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <input type="text" name="plan_slug[]" placeholder="Plan Slug" />
                            <textarea name="plan_description[]" maxlength="250" placeholder="Plan Description"></textarea>
                            <button type="button" class="button remove-plan">Remove</button>
                        </div>
                        <button type="button" class="button add-plan">Add Plan</button>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>

<style>
.plan-row {
    margin-bottom: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}

.plan-row input[type="text"] {
    width: 200px;
    margin-right: 10px;
}

.plan-row textarea {
    width: 300px;
    height: 100px;
    vertical-align: top;
    margin-right: 10px;
}

.add-plan {
    margin-top: 10px !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.add-plan').on('click', function() {
        var $repeater = $(this).closest('.plan-repeater');
        var $template = $repeater.find('.template').clone();
        var provider = $repeater.data('provider');
        var index = $repeater.find('.plan-row').length - 1;

        $template.removeClass('template').show()
            .find('input, textarea').each(function() {
                var name = $(this).attr('name');
                $(this).attr('name', 'siya_' + provider + '_plans[' + index + '][' + 
                    (name.includes('slug') ? 'slug' : 'description') + ']');
            });

        $(this).before($template);
    });

    $(document).on('click', '.remove-plan', function() {
        $(this).closest('.plan-row').remove();
    });
});
</script>
