<div id="arsol_server_settings_data" class="panel woocommerce_options_panel">
    <div class="options_group">
        <div id="arsol_server_settings" style="padding: 9px 12px;">
            <div class="toolbar toolbar-top">
                <div class="inline notice woocommerce-message">
                    <p class="help arsol">
                        <?php _e('Notezss: Changing server settings here will not affect servers associated with completed or pending subscriptions', 'woocommerce'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
        $server_type = get_post_meta($post->ID, '_arsol_server_type', true);
        $all_types = [
            'sites_server'          => __('Sites Server', 'woocommerce'),
            'application_server'    => __('Application Server', 'woocommerce'),
            'block_storage_server'  => __('Block Storage Server', 'woocommerce'),
            'cloud_server'          => __('Cloud Server', 'woocommerce'),
            'email_server'          => __('Email Server', 'woocommerce'),
            'object_storage_server' => __('Object Storage Server', 'woocommerce'),
            'vps_server'            => __('VPS Server', 'woocommerce'),
        ];
        $enabled_types = array_intersect_key($all_types, array_flip($enabled_server_types));
        woocommerce_wp_select(array(
            'id'          => '_arsol_server_type',
            'label'       => __('Server Type', 'woocommerce'),
            'description' => __('Select the server type.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $enabled_types,  // No placeholder option
            'value'       => $server_type ?: ''  // Ensure the value is empty if not set
        ));
        ?>
        <div class="arsol_max_applications_field">
            <?php
            $max_applications = get_post_meta($post->ID, '_arsol_max_applications', true);
            woocommerce_wp_text_input(array(
                'id'          => '_arsol_max_applications',
                'label'       => __('Maximum applications', 'woocommerce'),
                'description' => __('Enter the maximum number of applications or sites allowed with this plan.', 'woocommerce'),
                'desc_tip'    => 'true',
                'type'        => 'number',
                'custom_attributes' => array(
                    'min' => '0',
                    'max' => '999',
                    'step' => '1',
                    'required' => 'required',
                    'style' => 'width: 3em; text-align: center;',  // Enough for 3 characters and centered
                    'oninput' => 'this.value = this.value.replace(/[^0-9]/g, \'\')'  // Only accept numbers
                ),
                'value'       => empty($max_applications) ? '0' : $max_applications
            ));
            ?>
        </div>
        <?php
        $is_sites_server = $server_type === 'sites_server';
        $is_ecommerce = get_post_meta($post->ID, '_arsol_ecommerce_optimized', true) === 'yes';
        $is_server_manager = get_post_meta($post->ID, '_arsol_server_manager_required', true) === 'yes';

        // Initialize Runcloud checkbox
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_server_manager_required',
            'label'       => __('Runcloud', 'woocommerce'),
            'description' => __('Connect this server to Runcloud server manager.', 'woocommerce'),
            'desc_tip'    => 'true',
            'cbvalue'     => 'yes',
            'value'       => $is_server_manager ? 'yes' : 'no' // Use saved value directly
        ));
        ?>
        <div class="arsol_ecommerce_optimized_field hidden">
            <?php
            woocommerce_wp_checkbox(array(
                'id'          => '_arsol_ecommerce_optimized',
                'label'       => __('E-commerce', 'woocommerce'),
                'description' => __('Enable this option if the server setup is optimized for ecommerce.', 'woocommerce'),
                'desc_tip'    => 'true',
                'cbvalue'     => 'yes',
                'value'       => $is_ecommerce ? 'yes' : 'no'
            ));
            ?>
        </div>
        <?php
        // Provider Dropdown
        $providers = $slugs->get_provider_slugs();
        $selected_provider = get_post_meta($post->ID, '_arsol_server_provider_slug', true);

        woocommerce_wp_select(array(
            'id'          => '_arsol_server_provider_slug',
            'label'       => __('Server provider', 'woocommerce'),
            'description' => __('Select the server provider.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => array_combine($providers, array_map([$slugs, 'get_provider_name'], $providers)),
            'value'       => $selected_provider,
            'custom_attributes' => array('disabled' => 'disabled')  // Disable on load
        ));

        // Group Dropdown
        $selected_group = get_post_meta($post->ID, '_arsol_server_plan_group_slug', true);
        $groups = $selected_provider ? $slugs->get_provider_group_slugs($selected_provider) : [];

        woocommerce_wp_select(array(
            'id'          => '_arsol_server_plan_group_slug',
            'label'       => __('Server plan group', 'woocommerce'),
            'description' => __('Select the server plan group, which the plan you want belongs to.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => array_combine($groups, $groups),
            'value'       => $selected_group,
            'custom_attributes' => array('disabled' => 'disabled')  // Disable on load
        ));

        // Plan Dropdown
        $selected_plan = get_post_meta($post->ID, '_arsol_server_plan_slug', true);
        $plans = $selected_provider && $selected_group ? 
            $slugs->get_filtered_plans($selected_provider, $selected_group) : [];
        $plan_options = [];
        foreach ($plans as $plan) {
            $plan_options[$plan['slug']] = $plan['slug'];
        }

        woocommerce_wp_select(array(
            'id'          => '_arsol_server_plan_slug',
            'label'       => __('Server plan', 'woocommerce'),
            'description' => __('Select the server plan.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $plan_options,
            'value'       => $selected_plan,
            'custom_attributes' => empty($selected_group) ? array('disabled' => 'disabled') : array()
        ));

        // Add wrapper div for region and image fields
        ?>
        <div class="arsol_non_sites_server_fields">
            <?php
            // Region Text Field
            $region = get_post_meta($post->ID, '_arsol_server_region', true);
            woocommerce_wp_text_input(array(
                'id'          => '_arsol_server_region',
                'label'       => __('Server region (optional)', 'woocommerce'),
                'description' => __('Enter the server region. Only letters, numbers and hyphens allowed.', 'woocommerce'),
                'desc_tip'    => true,
                'value'       => $region,
                'custom_attributes' => array(
                    'pattern' => '^[a-zA-Z0-9-]+$',
                    'title' => 'Only letters, numbers and hyphens allowed'
                )
            ));

            // Server Image Text Field
            $server_image = get_post_meta($post->ID, '_arsol_server_image', true);
            woocommerce_wp_text_input(array(
                'id'          => '_arsol_server_image',
                'label'       => __('Server image (optional)', 'woocommerce'),
                'description' => __('Enter the server image identifier. Only letters, numbers and hyphens allowed.', 'woocommerce'),
                'desc_tip'    => true,
                'value'       => $server_image,
                'custom_attributes' => array(
                    'pattern' => '^[a-zA-Z0-9-]+$',
                    'title' => 'Only letters, numbers and hyphens allowed'
                )
            ));
            ?>
        </div>
        <?php
        $selected_assigned_server_groups = get_post_meta($post->ID, '_arsol_assigned_server_groups', true);
        $selected_assigned_server_groups = is_array($selected_assigned_server_groups) ? $selected_assigned_server_groups : [];
        $server_groups_terms = get_terms([
            'taxonomy'   => 'arsol_server_group',
            'hide_empty' => false,
        ]);
        ?>
        <p class="form-field">
            <label><?php _e('Add server to groups', 'woocommerce'); ?></label>
            <select
                id="_arsol_assigned_server_groups"
                name="_arsol_assigned_server_groups[]"
                class="wc-enhanced-select"
                multiple="multiple"
                style="width: 50%;"
                data-tip="<?php _e('Select the groups to which this server should be added after it\'s created.', 'woocommerce'); ?>"
            >
                <?php foreach ($server_groups_terms as $term) : ?>
                    <option
                        value="<?php echo esc_attr($term->term_id); ?>"
                        <?php selected(in_array($term->term_id, $selected_assigned_server_groups), true); ?>
                    >
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
        $selected_assigned_server_tags = get_post_meta($post->ID, '_arsol_assigned_server_tags', true);
        $selected_assigned_server_tags = is_array($selected_assigned_server_tags) ? $selected_assigned_server_tags : [];
        $server_tags_terms = get_terms([
            'taxonomy'   => 'arsol_server_tag',
            'hide_empty' => false,
        ]);
        ?>
        <p class="form-field">
            <label><?php _e('Add tags to server', 'woocommerce'); ?></label>
            <select
                id="_arsol_assigned_server_tags"
                name="_arsol_assigned_server_tags[]"
                class="wc-enhanced-select"
                multiple="multiple"
                style="width: 50%;"
                data-tip="<?php _e('Select the tags to assign to this server after it\'s created.', 'woocommerce'); ?>"
            >
                <?php foreach ($server_tags_terms as $term) : ?>
                    <option
                        value="<?php echo esc_attr($term->term_id); ?>"
                        <?php selected(in_array($term->term_id, $selected_assigned_server_tags), true); ?>
                    >
                        <?php echo esc_html($term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
        ?>
    </div>
</div>

<style>
.hidden {
    display: none;
}
.arsol_non_sites_server_fields.hidden {
    display: none;
}
.arsol_max_applications_field.hidden {
    display: none;
}
.arsol_ecommerce_optimized_field.hidden {
    display: none;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#post').on('submit', function(e) {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $('#_arsol_server_plan_group_slug').val();
        var plan = $('#_arsol_server_plan_slug').val();
        var serverType = $('#_arsol_server_type').val();

        if (!provider || !group || !plan || !serverType) {
            e.preventDefault();
            alert('Please fill in all required fields: Server type, Server provider, Server group, and Server plan.');
        }
    });

    function updateGroups(provider, callback) {
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_groups',
                provider: provider
            },
            success: function(groups) {
                var $groupSelect = $('#_arsol_server_plan_group_slug');
                $groupSelect.empty();
                
                if (groups.length === 0) {
                    $groupSelect.prop('disabled', true);
                } else {
                    $groupSelect.prop('disabled', false);
                    groups.forEach(function(group) {
                        $groupSelect.append(new Option(group, group));
                    });

                    // Set the selected group
                    var selectedGroup = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_plan_group_slug', true)); ?>';
                    $groupSelect.val(selectedGroup);
                }
                
                $groupSelect.trigger('change');
                if (callback) callback(groups);
            }
        });
    }

    function updatePlans(provider, group) {
        var $planSelect = $('#_arsol_server_plan_slug');
        if (!group) {
            $planSelect.prop('disabled', true).val(''); // Clear value when disabled
            return;
        }
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_group_plans',
                provider: provider,
                group: group
            },
            success: function(response) {
                var plans = [];
                try {
                    if (typeof response === 'string') {
                        plans = JSON.parse(response);  // Parse the response as JSON
                    } else if (typeof response === 'object') {
                        plans = response;  // Response is already an object
                    }
                    if (!Array.isArray(plans)) {
                        plans = Object.values(plans);  // Convert object to array if necessary
                    }
                } catch (e) {
                    console.error('Failed to parse plans:', e);
                    plans = [];
                }
                $planSelect.empty();
                
                if (plans.length === 0) {
                    $planSelect.prop('disabled', true).val(''); // Clear value when disabled
                } else {
                    $planSelect.prop('disabled', false);
                    plans.forEach(function(plan) {
                        $planSelect.append(new Option(plan.slug, plan.slug));
                    });

                    // Set the selected plan
                    var selectedPlan = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_plan_slug', true)); ?>';
                    $planSelect.val(selectedPlan);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch plans:', error);
            }
        });
    }

    function setSitesProvider() {
        var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
        var wpGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';
        $('#_arsol_server_provider_slug').val(wpProvider).prop('disabled', true);
        updateGroups(wpProvider, function(groups) {
            if (groups.includes(wpGroup)) {
                $('#_arsol_server_plan_group_slug').val(wpGroup).prop('disabled', true);
                updatePlans(wpProvider, wpGroup);
            } else {
                $('#_arsol_server_plan_group_slug').prop('disabled', true); // Always disabled
                updatePlans(wpProvider, wpGroup);
            }
        });
    }

    function setRuncloudCheckboxState(checked = true, disabled = true) {
        console.log('Setting Runcloud checkbox state:', { checked, disabled }); // Debug log
        var $checkbox = $('#_arsol_server_manager_required');
        var savedValue = '<?php echo esc_js($is_server_manager ? "yes" : "no"); ?>';
        
        if (checked) {
            $checkbox.prop('checked', true);
        } else {
            // If not forced checked, use the saved value
            $checkbox.prop('checked', savedValue === 'yes');
        }
        
        $checkbox.prop('disabled', disabled).trigger('change');
    }

    function toggleSitesFields() {
        var serverType = $('#_arsol_server_type').val();
        if (serverType === 'sites_server') {
            $('.arsol_non_sites_server_fields').addClass('hidden');
            $('.arsol_ecommerce_optimized_field').removeClass('hidden'); // Show ecommerce field
            setSitesProvider();
            setRuncloudCheckboxState(true, true);
            $('#_arsol_server_provider_slug').prop('disabled', true);
            $('#_arsol_server_plan_group_slug').prop('disabled', true);
        } else if (!serverType) {
            // If no server type selected, keep Runcloud checked and disabled
            setRuncloudCheckboxState(true, true);
        } else {
            $('.arsol_non_sites_server_fields').removeClass('hidden');
            $('.arsol_ecommerce_optimized_field').addClass('hidden'); // Hide ecommerce field
            $('#_arsol_server_provider_slug').prop('disabled', false);
            $('#_arsol_server_plan_group_slug').prop('disabled', false);
            setRuncloudCheckboxState(false, false);
        }
    }

    function toggleApplicationsField() {
        var serverType = $('#_arsol_server_type').val();
        if (serverType === 'sites_server' || serverType === 'application_server') {
            $('.arsol_max_applications_field').removeClass('hidden');
        } else {
            $('.arsol_max_applications_field').addClass('hidden');
        }
    }

    $('#_arsol_server_provider_slug').on('change', function() {
        var provider = $(this).val();
        updateGroups(provider, function(groups) {
            var selectedGroup = $('#_arsol_server_plan_group_slug').val();
            if (selectedGroup) {
                updatePlans(provider, selectedGroup);
            }
        });
    });

    $('#_arsol_server_plan_group_slug').on('change', function() {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $(this).val();
        updatePlans(provider, group);
    });

    $('#_arsol_server_type').on('change', function() {
        toggleSitesFields();
        toggleApplicationsField();
    });

    // Initial load
    var initialProvider = $('#_arsol_server_provider_slug').val();
    if (initialProvider) {
        updateGroups(initialProvider, function(groups) {
            var selectedGroup = $('#_arsol_server_plan_group_slug').val();
            if (selectedGroup) {
                updatePlans(initialProvider, selectedGroup);
            }
        });
    }

    // Initial state
    toggleSitesFields();
    toggleApplicationsField();

    // Ensure Runcloud checkbox is correctly set and disabled on initial load
    if ($('#_arsol_server_type').val() === 'sites_server') {
        $('#_arsol_server_manager_required').prop('checked', true).prop('disabled', true);
        setSitesProvider();
    }

    // Add validation for region and server image fields
    $('#arsol_server_region, #_arsol_server_image').on('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');
    });

    function toggle_arsol_server_settings_tab() {
        if ($('#_arsol_server').is(':checked')) {
            $('#woocommerce-product-data .arsol_server_settings_options').show();
        } else {
            $('#woocommerce-product-data .arsol_server_settings_options').hide();
            $('.wc-tabs .general_tab a').click();
        }
    }

    // Initial state
    toggle_arsol_server_settings_tab();

    $('#_arsol_server').on('change', function() {
        toggle_arsol_server_settings_tab();
    });
});
</script>

<?php 

// Add the script to the admin footer
add_action('admin_footer', 'add_admin_footer_script');

function add_admin_footer_script() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function toggle_arsol_server_settings_tab() {
            if ($('#_arsol_server').is(':checked')) {
                $('#woocommerce-product-data .arsol_server_settings_options').show();
            } else {
                $('#woocommerce-product-data .arsol_server_settings_options').hide();
                $('.wc-tabs .general_tab a').click();
            }
        }

        // Initial state
        toggle_arsol_server_settings_tab();

        $('#_arsol_server').on('change', function() {
            toggle_arsol_server_settings_tab();
        });
    });
    </script>
    <?php
}
?>





