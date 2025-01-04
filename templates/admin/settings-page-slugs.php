<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('siya_settings_slugs'); ?>
        <?php do_settings_sections('siya-slugs-settings'); ?>

        <!-- WordPress Plan Section -->
        <h2>WordPress managed hosting</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Server provider</th>
                <td>
                    <select name="siya_wp_server_provider">
                        <option value="digitalocean" <?php selected(get_option('siya_wp_server_provider'), 'digitalocean'); ?>>DigitalOcean</option>
                        <option value="hetzner" <?php selected(get_option('siya_wp_server_provider'), 'hetzner'); ?>>Hetzner</option>
                        <option value="vultr" <?php selected(get_option('siya_wp_server_provider'), 'vultr'); ?>>Vultr</option>
                    </select>
                    <p class="arsol-description">Select the cloud provider for WordPress hosting</p>
                </td>
            </tr>
        </table>

        <!-- DigitalOcean Section -->
        <h2>DigitalOcean</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Server provider slug</th>
                <td>
                    <input type="text" value="digitalocean" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Available plans</th>
                <td>
                    <div class="plan-repeater" data-provider="digitalocean">
                        <?php
                        $digitalocean_plans = get_option('siya_digitalocean_plans', array());
                        if (!empty($digitalocean_plans)) {
                            foreach ($digitalocean_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <div class="plan-field">
                                        <label>Plan slug</label>
                                        <input type="text" name="siya_digitalocean_plans[<?php echo $index; ?>][slug]" 
                                               value="<?php echo esc_attr($plan['slug'] ?? ''); ?>" placeholder="Enter plan slug" />
                                        <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Group slug</label>
                                        <input type="text" name="siya_digitalocean_plans[<?php echo $index; ?>][group_slug]" 
                                               value="<?php echo esc_attr($plan['group_slug'] ?? ''); ?>" placeholder="Enter group slug" />
                                        <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Plan description</label>
                                        <textarea name="siya_digitalocean_plans[<?php echo $index; ?>][description]" 
                                                  maxlength="250" placeholder="Enter plan description"><?php echo esc_textarea($plan['description'] ?? ''); ?></textarea>
                                        <p class="arsol-description">Brief description of what this plan offers</p>
                                    </div>
                                    <div class="plan-field">
                                        <button type="button" class="button remove-plan">Remove plan</button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <div class="plan-field">
                                <label>Plan slug</label>
                                <input type="text" name="plan_slug[]" placeholder="Enter plan slug" />
                                <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                            </div>
                            <div class="plan-field">
                                <label>Group slug</label>
                                <input type="text" name="group_slug[]" placeholder="Enter group slug" />
                                <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                            </div>
                            <div class="plan-field">
                                <label>Plan description</label>
                                <textarea name="plan_description[]" maxlength="250" placeholder="Enter plan description"></textarea>
                                <p class="arsol-description">Brief description of what this plan offers</p>
                            </div>
                            <div class="plan-field">
                                <button type="button" class="button remove-plan">Remove plan</button>
                            </div>
                        </div>
                        <button type="button" class="button add-plan">Add plan</button>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Hetzner Section -->
        <h2>Hetzner</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Server provider slug</th>
                <td>
                    <input type="text" value="hetzner" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Available plans</th>
                <td>
                    <div class="plan-repeater" data-provider="hetzner">
                        <?php
                        $hetzner_plans = get_option('siya_hetzner_plans', array());
                        if (!empty($hetzner_plans)) {
                            foreach ($hetzner_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <div class="plan-field">
                                        <label>Plan slug</label>
                                        <input type="text" name="siya_hetzner_plans[<?php echo $index; ?>][slug]" 
                                               value="<?php echo esc_attr($plan['slug'] ?? ''); ?>" placeholder="Enter plan slug" />
                                        <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Group slug</label>
                                        <input type="text" name="siya_hetzner_plans[<?php echo $index; ?>][group_slug]" 
                                               value="<?php echo esc_attr($plan['group_slug'] ?? ''); ?>" placeholder="Enter group slug" />
                                        <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Plan description</label>
                                        <textarea name="siya_hetzner_plans[<?php echo $index; ?>][description]" 
                                                  maxlength="250" placeholder="Enter plan description"><?php echo esc_textarea($plan['description'] ?? ''); ?></textarea>
                                        <p class="arsol-description">Brief description of what this plan offers</p>
                                    </div>
                                    <div class="plan-field">
                                        <button type="button" class="button remove-plan">Remove plan</button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <div class="plan-field">
                                <label>Plan slug</label>
                                <input type="text" name="plan_slug[]" placeholder="Enter plan slug" />
                                <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                            </div>
                            <div class="plan-field">
                                <label>Group slug</label>
                                <input type="text" name="group_slug[]" placeholder="Enter group slug" />
                                <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                            </div>
                            <div class="plan-field">
                                <label>Plan description</label>
                                <textarea name="plan_description[]" maxlength="250" placeholder="Enter plan description"></textarea>
                                <p class="arsol-description">Brief description of what this plan offers</p>
                            </div>
                            <div class="plan-field">
                                <button type="button" class="button remove-plan">Remove plan</button>
                            </div>
                        </div>
                        <button type="button" class="button add-plan">Add plan</button>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Vultr Section -->
        <h2>Vultr</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Server provider slug</th>
                <td>
                    <input type="text" value="vultr" disabled />
                </td>
            </tr>
            <tr>
                <th scope="row">Available plans</th>
                <td>
                    <div class="plan-repeater" data-provider="vultr">
                        <?php
                        $vultr_plans = get_option('siya_vultr_plans', array());
                        if (!empty($vultr_plans)) {
                            foreach ($vultr_plans as $index => $plan) {
                                ?>
                                <div class="plan-row">
                                    <div class="plan-field">
                                        <label>Plan slug</label>
                                        <input type="text" name="siya_vultr_plans[<?php echo $index; ?>][slug]" 
                                               value="<?php echo esc_attr($plan['slug'] ?? ''); ?>" placeholder="Enter plan slug" />
                                        <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Group slug</label>
                                        <input type="text" name="siya_vultr_plans[<?php echo $index; ?>][group_slug]" 
                                               value="<?php echo esc_attr($plan['group_slug'] ?? ''); ?>" placeholder="Enter group slug" />
                                        <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                                    </div>
                                    <div class="plan-field">
                                        <label>Plan description</label>
                                        <textarea name="siya_vultr_plans[<?php echo $index; ?>][description]" 
                                                  maxlength="250" placeholder="Enter plan description"><?php echo esc_textarea($plan['description'] ?? ''); ?></textarea>
                                        <p class="arsol-description">Brief description of what this plan offers</p>
                                    </div>
                                    <div class="plan-field">
                                        <button type="button" class="button remove-plan">Remove plan</button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div class="plan-row template" style="display: none;">
                            <div class="plan-field">
                                <label>Plan slug</label>
                                <input type="text" name="plan_slug[]" placeholder="Enter plan slug" />
                                <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                            </div>
                            <div class="plan-field">
                                <label>Group slug</label>
                                <input type="text" name="group_slug[]" placeholder="Enter group slug" />
                                <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                            </div>
                            <div class="plan-field">
                                <label>Plan description</label>
                                <textarea name="plan_description[]" maxlength="250" placeholder="Enter plan description"></textarea>
                                <p class="arsol-description">Brief description of what this plan offers</p>
                            </div>
                            <div class="plan-field">
                                <button type="button" class="button remove-plan">Remove plan</button>
                            </div>
                        </div>
                        <button type="button" class="button add-plan">Add plan</button>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>

<style>

.wp-core-ui .button {
    margin-left: 0;
    margin-right: 1em;
}

.plan-row {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.plan-field {
    margin-bottom: 15px;
}

.plan-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    font-size: 14px;
}

.plan-field input[type="text"] {
    width: 100%;
    max-width: 400px;
}

.plan-field textarea {
    width: 100%;
    max-width: 400px;
    height: 100px;
}

.add-plan {
    margin-top: 10px !important;
}

.remove-plan {
    color: #dc3545;
    border-color: #dc3545;
}

.remove-plan:hover {
    background: #dc3545;
    color: #fff;
}

.arsol-description {
    margin-top: 4px;
    margin-bottom: 0;
    color: #666;
    font-size: 13px;
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
                    (name.includes('group_slug') ? 'group_slug' : name.includes('slug') ? 'slug' : 'description') + ']');
            });

        $(this).before($template);
    });

    $(document).on('click', '.remove-plan', function() {
        $(this).closest('.plan-row').remove();
    });
});
</script>
