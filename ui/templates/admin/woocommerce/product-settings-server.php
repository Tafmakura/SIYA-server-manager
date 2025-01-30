<div id="arsol_server_settings_data" class="panel woocommerce_options_panel">
    <div class="options_group">
        <div id="arsol_server_settings" style="padding: 9px 12px;">
            <div class="toolbar toolbar-top">
                <div class="inline notice woocommerce-message">
                    <p class="help arsol">
                        <?php _e('Notes: Changing server settings here will not affect servers associated with completed or pending subscriptions', 'woocommerce'); ?>
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
            'id'          => 'arsol_server_type',
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
                'id'          => 'arsol_max_applications',
                'label'       => __('Maximum applications', 'woocommerce'),
                'description' => __('Enter the maximum number of applications or sites that can be installed on this server, 0 indicates no restriction.', 'woocommerce'),
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
            'id'          => 'arsol_server_manager_required',
            'label'       => __('Runcloud', 'woocommerce'),
            'description' => __('Connect this server to Runcloud server manager.', 'woocommerce'),
            'desc_tip'    => 'true',
            'cbvalue'     => 'yes',
            'value'       => $is_server_manager ? 'yes' : 'no' // Use saved value directly
        ));
        ?>
        <div class="arsol_ecommerce_optimized_field show_if_arsol_sites_server">
            <?php
            woocommerce_wp_checkbox(array(
                'id'          => 'arsol_ecommerce_optimized',
                'label'       => __('E-commerce', 'woocommerce'),
                'description' => __('Enable this option if the server setup is optimized for ecommerce.', 'woocommerce'),
                'desc_tip'    => 'true',
                'cbvalue'     => 'yes',
                'value'       => $is_ecommerce ? 'yes' : 'no'
            ));
            ?>
        </div>
        <?php
        // Provider Dropdownn
        $providers = $slugs->get_provider_slugs();
        $selected_provider = get_post_meta($post->ID, '_arsol_server_provider_slug', true);

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_provider_slug', 
            'label'       => __('Server provider', 'woocommerce'),
            'description' => __('Select the server provider.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => array_combine($providers, array_map(function($provider) {
            return ucfirst($provider); // Capitalize first letter
            }, $providers)),
            'value'       => $selected_provider,
            'required'    => true,
            'custom_attributes' => array('disabled' => 'disabled')  // Disable on load
        ));

        // Group Dropdown
        $selected_group = get_post_meta($post->ID, '_arsol_server_plan_group_slug', true);
        $groups = $selected_provider ? $slugs->get_provider_group_slugs($selected_provider) : [];

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_plan_group_slug',
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
            'id'          => 'arsol_server_plan_slug',
            'label'       => __('Server plan', 'woocommerce'),
            'description' => __('Select the server plan.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $plan_options,
            'value'       => $selected_plan,
            'custom_attributes' => empty($selected_group) ? array('disabled' => 'disabled') : array()
        ));

        // Add wrapper div for region and image fields
        ?>
        <div class="hide_if_arsol_sites_server">
            <?php
            // Region Text Field
            $region = get_post_meta($post->ID, '_arsol_server_region', true);
            woocommerce_wp_text_input(array(
                'id'          => 'arsol_server_region',
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
                'id'          => 'arsol_server_image',
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
                id="arsol_assigned_server_groups"
                name="arsol_assigned_server_groups[]"
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
                id="arsol_assigned_server_tags"
                name="arsol_assigned_server_tags[]"
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
    .arsol_max_applications_field.hidden {
        display: none;
    }
    .arsol_ecommerce_optimized_field.hidden {
        display: none;
    }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Remove redundant functions and consolidate the logic
    
    // Remove disableAllDropdowns since its functionality is handled in updateServerTypeFields
    // function disableAllDropdowns() { ... } - removed

    // Combine toggleApplicationsField into toggleServerElements since they're always called together
    function toggleServerElements() {
        var serverType = $('#arsol_server_type').val();
        var isServerEnabled = $('#arsol_server').is(':checked');

        // First hide all server-type specific elements and general server elements
        $('.show_if_arsol_sites_server, .show_if_arsol_application_server, .show_if_arsol_server')
            .attr('style', 'display: none !important')
            .addClass('hidden');
        $('.hide_if_arsol_sites_server, .hide_if_arsol_application_server, .hide_if_arsol_server')
            .attr('style', '')
            .removeClass('hidden');

        if (isServerEnabled) {
            // Show general server elements first
            $('.show_if_arsol_server').attr('style', '').removeClass('hidden');
            $('.hide_if_arsol_server').attr('style', 'display: none !important').addClass('hidden');
            
            // Show elements based on server type
            if (serverType === 'sites_server') {
                $('.show_if_arsol_sites_server').attr('style', '').removeClass('hidden');
                $('.hide_if_arsol_sites_server').attr('style', 'display: none !important').addClass('hidden');
            }
            else if (serverType === 'application_server') {
                $('.show_if_arsol_application_server').attr('style', '').removeClass('hidden');
                $('.hide_if_arsol_application_server').attr('style', 'display: none !important').addClass('hidden');
            }
            
            // Handle elements that should show for both types
            if (serverType === 'sites_server' || serverType === 'application_server') {
                $('.show_if_arsol_sites_server.show_if_arsol_application_server').attr('style', '').removeClass('hidden');
                // Handle applications field visibility here instead of separate function
                $('.arsol_max_applications_field').removeClass('hidden');
            } else {
                $('.arsol_max_applications_field').addClass('hidden');
            }
        } else {
            // Hide applications field when server is disabled
            $('.arsol_max_applications_field').addClass('hidden');
        }
    }

    function updateServerTypeFields(serverType) {
        var $providerSelect = $('#arsol_server_provider_slug');
        var $groupSelect = $('#arsol_server_plan_group_slug');
        var $planSelect = $('#arsol_server_plan_slug');

        if (serverType === 'sites_server') {
            var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
            var wpGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';

            $providerSelect.empty()
                          .append(new Option(wpProvider.charAt(0).toUpperCase() + wpProvider.slice(1), wpProvider))
                          .val(wpProvider)
                          .prop('disabled', true);
            
            setRuncloudCheckboxState(true, true);
            
            updateGroups(wpProvider, function(groups) {
                if (groups.includes(wpGroup)) {
                    $('#arsol_server_plan_group_slug').val(wpGroup).prop('disabled', true);
                    updatePlans(wpProvider, wpGroup);
                }
            });
        } else {
            setRuncloudCheckboxState(false, false);
            
            // Clear and disable dropdowns
            $providerSelect.prop('disabled', true).empty().append(new Option('empty', ''));
            $groupSelect.prop('disabled', true).empty().append(new Option('empty', ''));
            $planSelect.prop('disabled', true).empty().append(new Option('empty', ''));
            
            if (serverType) {
                updateProvidersByServerType(serverType);
            }
        }
        
        toggleServerElements();
    }

    // Simplified initialization
    function initializeServerSettings() {
        var initialProvider = $('#arsol_server_provider_slug').val();
        var initialServerType = $('#arsol_server_type').val();
        
        toggleServerElements();
        
        if (initialProvider && initialServerType) {
            updateServerTypeFields(initialServerType);
        }
    }

    // Event handlers
    $('#arsol_server').on('change', toggleServerElements);
    $('#arsol_server_type').on('change', function() {
        updateServerTypeFields($(this).val());
    });

    // Initialize
    initializeServerSettings();

    // ...keep existing AJAX-related functions (updateGroups, updatePlans, updateProvidersByServerType)...
});
</script>





