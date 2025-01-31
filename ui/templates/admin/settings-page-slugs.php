<?php
if (!defined('ABSPATH')) {
    exit;
}

function render_repeater_section($provider_slug, $plans) {
    ?>
    <div class="plan-repeater" data-provider="<?php echo esc_attr($provider_slug); ?>">
        <?php
        if (!empty($plans)) {
            foreach ($plans as $index => $plan) {
                ?>
                <div class="plan-row">
                    <div class="plan-inner-row">   
                        <div class="plan-column">
                            <div class="plan-field">
                                <label>Plan slug</label>
                                <input type="text" name="siya_<?php echo esc_attr($provider_slug); ?>_plans[<?php echo $index; ?>][slug]" 
                                    value="<?php echo esc_attr($plan['slug'] ?? ''); ?>" placeholder="Enter plan slug" />
                                <p class="arsol-description">A unique identifier for this plan (e.g., basic-droplet)</p>
                            </div>
                            <div class="plan-field">
                                <label>Group slug</label>
                                <input type="text" name="siya_<?php echo esc_attr($provider_slug); ?>_plans[<?php echo $index; ?>][group_slug]" 
                                    value="<?php echo esc_attr($plan['group_slug'] ?? ''); ?>" placeholder="Enter group slug" />
                                <p class="arsol-description">A unique identifier for this group (e.g., basic-group)</p>
                            </div>
                            <div class="plan-field">
                                <label>Plan description</label>
                                <textarea name="siya_<?php echo esc_attr($provider_slug); ?>_plans[<?php echo $index; ?>][description]" 
                                        maxlength="250" placeholder="Enter plan description"><?php echo esc_textarea($plan['description'] ?? ''); ?></textarea>
                                <p class="arsol-description">Brief description of what this plan offers</p>
                            </div>
                        </div>
                        <div class="plan-column">
                            <div class="plan-field">
                                <label>Server Type</label>
                                <p class="arsol-description">Choose the server types on which this plan should be available. The plan will only appear as an option for the selected server types.</p></br>
                                <div class="check-box-group">
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
                                    foreach ($saved_types as $type) {
                                        $checked = in_array($type, $plan['server_types'] ?? []) ? 'checked' : '';
                                        echo '<label><input type="checkbox" name="siya_' . esc_attr($provider_slug) . '_plans[' . $index . '][server_types][]" value="' . esc_attr($type) . '" ' . $checked . '> ' . esc_html($all_types[$type]) . '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
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
            <div class="plan-inner-row">   
                <div class="plan-column">
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
                </div>
                <div class="plan-column">
                    <div class="plan-field">
                        <label>Server Type</label>
                        <p class="arsol-description">Choose the server types on which this plan should be available. The plan will only appear as an option for the selected server types.</p></br>
                        <div class="check-box-group">
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
                            foreach ($saved_types as $type) {
                                echo '<label><input type="checkbox" name="plan_server_types[]" value="' . esc_attr($type) . '"> ' . esc_html($all_types[$type]) . '</label>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="plan-field">
                <button type="button" class="button remove-plan">Remove plan</button>
            </div>
        </div>
        <button type="button" class="button add-plan">Add plan</button>
    </div>
    <?php
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') : ?>
        <div id="message" class="updated notice is-dismissible">
            <p><?php _e('Settings saved successfully.', 'siya'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="options.php">
        <?php settings_fields('siya_settings_slugs'); ?>
        <?php do_settings_sections('siya-slugs-settings'); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-sites" class="nav-tab nav-tab-active">Sites Managed Hosting</a>
            <a href="#tab-digitalocean" class="nav-tab">DigitalOcean</a>
            <a href="#tab-hetzner" class="nav-tab">Hetzner</a>
            <a href="#tab-vultr" class="nav-tab">Vultr</a>
        </h2>

        <div id="tab-sites" class="tab-content active">
            <h2>Sites Managed Hosting</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Server provider</th>
                    <td>
                        <select name="siya_wp_server_provider" id="siya_wp_server_provider">
                            <?php
                            $slugs = new Siya\AdminSettings\Slugs();
                            $providers = $slugs->get_providers_by_server_type('sites_server');
                            $selected_provider = get_option('siya_wp_server_provider');
                            foreach ($providers as $provider) {
                                $provider_name = ucfirst($provider); // Capitalize first letter
                                echo '<option value="' . esc_attr($provider) . '" ' . 
                                     selected($selected_provider, $provider, false) . '>' . 
                                     esc_html($provider_name) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="arsol-description">Select the cloud provider for Sites hosting</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Server group</th>
                    <td>
                        <select name="siya_wp_server_group" id="siya_wp_server_group">
                            <?php
                            $provider = get_option('siya_wp_server_provider');
                            $slugs = new Siya\AdminSettings\Slugs();
                            $groups = $slugs->get_provider_plan_group_slugs_by_server_type($provider, 'sites_server');
                            $selected_group = get_option('siya_wp_server_group');
                            foreach ($groups as $group) {
                                echo '<option value="' . esc_attr($group) . '" ' . selected($selected_group, $group, false) . '>' . esc_html($group) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="arsol-description">Select the server group for Sites hosting</p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-digitalocean" class="tab-content">
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
                        <?php
                        $slugs = new Siya\AdminSettings\Slugs();
                        $digitalocean_plans = get_option('siya_digitalocean_plans', array());
                        render_repeater_section('digitalocean', $digitalocean_plans);
                        ?>
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-hetzner" class="tab-content">
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
                        <?php
                        $hetzner_plans = get_option('siya_hetzner_plans', array());
                        render_repeater_section('hetzner', $hetzner_plans);
                        ?>
                    </td>
                </tr>
            </table>
        </div>

        <div id="tab-vultr" class="tab-content">
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
                        <?php
                        $vultr_plans = get_option('siya_vultr_plans', array());
                        render_repeater_section('vultr', $vultr_plans);
                        ?>
                    </td>
                </tr>
            </table>
        </div>

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

.plan-inner-row {
    display: flex;
    justify-content: space-between;
}

.plan-column {
    display: flex;
    flex-direction: column;
    flex: 1;
    margin-right: 20px;
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

.plan-field .check-box-group label {
    font-weight: initial;
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

select {
    min-width: 120px; /* Minimum width for dropdowns */
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Function to validate slug fields
    function validateSlugField($field) {
        var value = $field.val();
        var sanitizedValue = value.replace(/[^a-zA-Z0-9-]/g, '');
        if (value !== sanitizedValue) {
            $field.val(sanitizedValue);
        }
    }

    // Attach validation to slug fields
    $(document).on('input', 'input[name*="slug"], input[name*="group_slug"]', function() {
        validateSlugField($(this));
    });

    // Existing code for updating groups and adding/removing plans
    function updateGroups(provider) {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_groups',
                provider: provider
            },
            success: function(groups) {
                var $groupSelect = $('#siya_wp_server_group');
                $groupSelect.empty();
                
                if (groups.length === 0) {
                    $groupSelect.prop('disabled', true);
                } else {
                    $groupSelect.prop('disabled', false);
                    groups.forEach(function(group) {
                        $groupSelect.append(new Option(group, group));
                    });

                    // Set the selected group
                    var selectedGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';
                    $groupSelect.val(selectedGroup);
                }
            }
        });
    }

    $('#siya_wp_server_provider').on('change', function() {
        var provider = $(this).val();
        updateGroups(provider);
    });

    // Initial load
    var initialProvider = $('#siya_wp_server_provider').val();
    if (initialProvider) {
        updateGroups(initialProvider);
    }

    $('.add-plan').on('click', function() {
        var $repeater = $(this).closest('.plan-repeater');
        var $template = $repeater.find('.template').clone();
        var provider = $repeater.data('provider');
        var index = $repeater.find('.plan-row').length - 1;

        $template.removeClass('template').show()
            .find('input, textarea').each(function() {
                var name = $(this).attr('name');
                $(this).attr('name', 'siya_' + provider + '_plans[' + index + '][' + 
                    (name.includes('group_slug') ? 'group_slug' : name.includes('slug') ? 'slug' : name.includes('server_types') ? 'server_types' : 'description') + ']');
            });

        $(this).before($template);
    });

    $(document).on('click', '.remove-plan', function() {
        $(this).closest('.plan-row').remove();
    });

    // Tab navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $($(this).attr('href')).addClass('active');
    });

    // Show the first tab by default
    $('.nav-tab').first().click();
});
</script>
