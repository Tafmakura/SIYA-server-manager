<div id="arsol_server_settings_data" class="panel woocommerce_options_panel">
    <div class="options_group">
        <div id="arsol_server_settings" style="padding: 9px 12px;">
            <div class="toolbar toolbar-top">
                <div class="inline notice woocommerce-message">
                    <p class="help arsol">
                        <?php _e('Note: Changing server settings here will not affect servers associated with completed or pending subscriptions', 'woocommerce'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php

        $max_applications = get_post_meta($post->ID, '_arsol_max_applications', true);
        $max_staging_sites = get_post_meta($post->ID, '_arsol_max_staging_sites', true);
        $is_wordpress_server = get_post_meta($post->ID, '_arsol_wordpress_server', true) === 'yes';
        $is_ecommerce = get_post_meta($post->ID, '_arsol_wordpress_ecommerce', true) === 'yes';
        $is_server_manager = get_post_meta($post->ID, '_arsol_connect_server_manager', true) === 'yes';

        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_applications',
            'label'       => __('Maximum applications', 'woocommerce'),
            'description' => __('Enter the maximum number of applications allowed.', 'woocommerce'),
            'desc_tip'    => 'true',
            'type'        => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '999',
                'step' => '1',
                'style' => 'width: 3em; text-align: center;',  // Enough for 3 characters and centered
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, \'\')'  // Only accept numbers
            ),
            'value'       => empty($max_applications) ? '0' : $max_applications
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_staging_sites',
            'label'       => __('Maximum staging sites', 'woocommerce'),
            'description' => __('Enter the maximum number of staging sites allowed.', 'woocommerce'),
            'desc_tip'    => 'true',
            'type'        => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'max' => '999',
                'step' => '1',
                'style' => 'width: 3em; text-align: center;',  // Enough for 3 characters and centered
                'oninput' => 'this.value = this.value.replace(/[^0-9]/g, \'\')'  // Only accept numbers
            ),
            'value'       => empty($max_staging_sites) ? '0' : $max_staging_sites
        ));
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_connect_server_manager',
            'label'       => __('Runcloud', 'woocommerce'),
            'description' => __('Connect this server to Runcloud server manager.', 'woocommerce'),
            'desc_tip'    => 'true',
            'cbvalue'     => 'yes',
            'value'       => $is_server_manager ? 'yes' : 'no'
        ));
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_wordpress_server',
            'label'       => __('WordPress server', 'woocommerce'),
            'description' => __('Enable this option to set up a WordPress server.', 'woocommerce'),
            'desc_tip'    => 'true',
            'cbvalue'     => 'yes',
            'value'       => $is_wordpress_server ? 'yes' : 'no'
        ));    
        ?>
        <div class="arsol_wordpress_ecommerce_field">
            <?php
            woocommerce_wp_checkbox(array(
                'id'          => '_arsol_wordpress_ecommerce',
                'label'       => __('E-commerce', 'woocommerce'),
                'description' => __('Enable this option if the server will support ecommerce.', 'woocommerce'),
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
            'value'       => $selected_provider
        ));

        // Group Dropdown
        $selected_group = get_post_meta($post->ID, '_arsol_server_group_slug', true);
        $groups = $selected_provider ? $slugs->get_provider_group_slugs($selected_provider) : [];

        woocommerce_wp_select(array(
            'id'          => '_arsol_server_group_slug',
            'label'       => __('Server group', 'woocommerce'),
            'description' => __('Select the server group.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => array_combine($groups, $groups),
            'value'       => $selected_group
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
        <div class="arsol_non_wordpress_fields">
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
        ?>
    </div>
</div>

<style>
.hidden {
    display: none;
}
.arsol_non_wordpress_fields.hidden {
    display: none;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#post').on('submit', function(e) {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $('#_arsol_server_group_slug').val();
        var plan = $('#_arsol_server_plan_slug').val();

        if (!provider || !group || !plan) {
            e.preventDefault();
            alert('Please fill in all required fields: Server provider, Server group, and Server plan.');
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
                var $groupSelect = $('#_arsol_server_group_slug');
                $groupSelect.empty();
                
                if (groups.length === 0) {
                    $groupSelect.prop('disabled', true);
                } else {
                    $groupSelect.prop('disabled', false);
                    groups.forEach(function(group) {
                        $groupSelect.append(new Option(group, group));
                    });

                    // Set the selected group
                    var selectedGroup = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_group_slug', true)); ?>';
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

    function setWordPressProvider() {
        var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
        var wpGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';
        $('#_arsol_server_provider_slug').val(wpProvider).prop('disabled', true);
        updateGroups(wpProvider, function(groups) {
            if (groups.includes(wpGroup)) {
                $('#_arsol_server_group_slug').val(wpGroup).prop('disabled', true);
                updatePlans(wpProvider, wpGroup);
            }
        });
    }

    function toggleWordPressFields() {
        if ($('#_arsol_wordpress_server').is(':checked')) {
            $('.arsol_non_wordpress_fields').addClass('hidden');
        } else {
            $('.arsol_non_wordpress_fields').removeClass('hidden');
        }
    }

    $('#_arsol_server_provider_slug').on('change', function() {
        var provider = $(this).val();
        updateGroups(provider, function(groups) {
            var selectedGroup = $('#_arsol_server_group_slug').val();
            if (selectedGroup) {
                updatePlans(provider, selectedGroup);
            }
        });
    });

    $('#_arsol_server_group_slug').on('change', function() {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $(this).val();
        updatePlans(provider, group);
    });

    $('#_arsol_wordpress_server').on('change', function() {
        if ($(this).is(':checked')) {
            setWordPressProvider();
            toggleWordPressFields();
        } else {
            $('#_arsol_server_provider_slug').prop('disabled', false);
            $('#_arsol_server_group_slug').prop('disabled', false);
            toggleWordPressFields();
        }
    });

    // Initial load
    var initialProvider = $('#_arsol_server_provider_slug').val();
    if (initialProvider) {
        updateGroups(initialProvider, function(groups) {
            var selectedGroup = $('#_arsol_server_group_slug').val();
            if (selectedGroup) {
                updatePlans(initialProvider, selectedGroup);
            }
        });
    }

    if ($('#_arsol_wordpress_server').is(':checked')) {
        setWordPressProvider();
    }

    // Initial state
    toggleWordPressFields();

    // Add validation for region and server image fields
    $('#arsol_server_region, #_arsol_server_image').on('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9-]/g, '');
    });
});
</script>




