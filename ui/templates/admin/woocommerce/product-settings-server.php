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
        $selected_server_type = get_post_meta($post->ID, '_arsol_server_type', true);
        $all_types = [
            'sites_server'          => __('Sites Server', 'woocommerce'),
            'application_server'    => __('Application Server', 'woocommerce'),
            'block_storage_server'  => __('Block Storage Server', 'woocommerce'),
            'cloud_server'          => __('Cloud Server', 'woocommerce'),
            'email_server'          => __('Email Server', 'woocommerce'),
            'object_storage_server' => __('Object Storage Server', 'woocommerce'),
            'vps_server'            => __('VPS Server', 'woocommerce'),
        ];
        
        // Only show the saved option if it exists, otherwise empty array
        $options = [];
        if (!empty($selected_server_type) && isset($all_types[$selected_server_type])) {
            $options[$selected_server_type] = $all_types[$selected_server_type];
        }

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_type',
            'label'       => __('Server Type', 'woocommerce'),
            'description' => __('Select the server type.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $options,
            'value'       => $selected_server_type ?: '',
            'custom_attributes' => !empty($selected_server_type) ? array('disabled' => 'disabled') : array()
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
        // Fix undefined variable by moving declaration here
        $server_type = get_post_meta($post->ID, '_arsol_server_type', true);
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
        // Provider Dropdown
        $providers = $slugs->get_provider_slugs();
        $selected_provider = get_post_meta($post->ID, '_arsol_server_provider_slug', true);

        // Only show saved provider if it exists
        $provider_options = [];
        if (!empty($selected_provider) && in_array($selected_provider, $providers)) {
            $provider_options[$selected_provider] = $slugs->get_provider_name($selected_provider);
        }

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_provider_slug', 
            'label'       => __('Server provider', 'woocommerce'),
            'description' => __('Select the server provider.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $provider_options,
            'value'       => $selected_provider ?: '',
            'custom_attributes' => !empty($selected_provider) ? array('disabled' => 'disabled') : array()
        ));

        // Group Dropdown
        $selected_group = get_post_meta($post->ID, '_arsol_server_plan_group_slug', true);
        $group_options = [];

        // Simply check if we have a selected group and add it to options
        if (!empty($selected_group)) {
            $group_options[$selected_group] = $selected_group;
        }

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_plan_group_slug',
            'label'       => __('Server plan group', 'woocommerce'),
            'description' => __('Select the server plan group, which the plan you want belongs to.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $group_options,
            'value'       => $selected_group ?: '',
            'custom_attributes' => !empty($selected_group) ? array('disabled' => 'disabled') : array()
        ));

        // Plan Dropdown setup
        $selected_plan = get_post_meta($post->ID, '_arsol_server_plan_slug', true);
        $plan_options = [];

        // Simply check if we have a selected plan and add it to options
        if (!empty($selected_plan)) {
            $plan_options[$selected_plan] = $selected_plan;
        }

        woocommerce_wp_select(array(
            'id'          => 'arsol_server_plan_slug',
            'label'       => __('Server plan', 'woocommerce'),
            'description' => __('Select the server plan.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $plan_options,
            'value'       => $selected_plan ?: '',
            'custom_attributes' => !empty($selected_plan) ? array('disabled' => 'disabled') : array()
        ));

        // Add wrapper div for region and image fields
        ?>
        <div class="show_if_arsol_server">
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
            ?>
        </div>
        <div class="hide_if_arsol_sites_server">
            <?php 
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
    function clearServerOptionFields() {
        // Get all other select fields and clear their options
        $('select#arsol_server_provider_slug, select#arsol_server_plan_group_slug, select#arsol_server_plan_slug').each(function() {
            $(this).empty();
        });
    }

    // Add this new function after clearServerOptionFields
    function initializeServerTypeField() {
        var $serverType = $('#arsol_server_type');
        var allowedTypes = <?php 
            $saved_types = (array) get_option('arsol_allowed_server_types', ['sites_server']);
            echo json_encode($all_types); // $all_types is already defined in the PHP section above
        ?>;
        var savedTypes = <?php echo json_encode($saved_types); ?>;
        
        // Store current selection before clearing
        var currentValue = $serverType.val();
        
        // Enable and clear the field
        $serverType.prop('disabled', false).empty();

        // Add allowed options
        $.each(allowedTypes, function(value, text) {
            if (savedTypes.includes(value)) {
                $serverType.append($('<option></option>').val(value).text(text));
            }
        });

        // Restore previous value if it's in allowed types, otherwise default to first allowed type
        if (currentValue && savedTypes.includes(currentValue)) {
            $serverType.val(currentValue);
        } else {
            $serverType.val(savedTypes[0]);
        }
        $serverType.trigger('change');
    }

    function initializeServerProviderField() {
        var $providerField = $('#arsol_server_provider_slug');
        var selectedServerType = $('#arsol_server_type').val();
        var savedProvider = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_provider_slug', true)); ?>';

        if (selectedServerType === 'sites_server') {
            var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
            $providerField.prop('disabled', true)
                         .empty()
                         .append($('<option></option>').val(wpProvider).text(wpProvider))
                         .val(wpProvider)
                         .trigger('change');
            return;
        }

        // Regular AJAX flow for other server types
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_providers_by_server_type',
                server_type: selectedServerType
            },
            success: function(providers) {
                // Enable field and clear existing options
                $providerField.prop('disabled', false).empty();
                
                // Add provider options
                providers.forEach(function(provider) {
                    $providerField.append($('<option></option>')
                        .val(provider)
                        .text(provider)
                    );
                });
                
                // Select saved provider if it exists in allowed list
                if (savedProvider && providers.includes(savedProvider)) {
                    $providerField.val(savedProvider);
                }
                
                $providerField.trigger('change');
            }
        });
    }

    function initializeServerPlanGroupField() {
        var $groupField = $('#arsol_server_plan_group_slug');
        var selectedServerType = $('#arsol_server_type').val();
        var selectedProvider = $('#arsol_server_provider_slug').val();

        // Only proceed if we have both server type and provider
        if (!selectedServerType || !selectedProvider) {
            $groupField.prop('disabled', true).empty();
            return;
        }

        var savedGroup = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_plan_group_slug', true)); ?>';

        if (selectedServerType === 'sites_server') {
            var wpGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';
            $groupField.prop('disabled', true)
                      .empty()
                      .append($('<option></option>').val(wpGroup).text(wpGroup))
                      .val(wpGroup)
                      .trigger('change');
            return;
        }

        // Get groups filtered by both server type and provider
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_plan_groups',
                provider: selectedProvider,
                server_type: selectedServerType
            },
            success: function(groups) {
                // Enable field for non-sites-server types
                $groupField.prop('disabled', false).empty();
                
                groups.forEach(function(group) {
                    $groupField.append($('<option></option>').val(group).text(group));
                });
                
                if (savedGroup && groups.includes(savedGroup)) {
                    $groupField.val(savedGroup);
                }
                
                $groupField.trigger('change');
            }
        });
    }

    function initializeServerPlanField() {
        var $planField = $('#arsol_server_plan_slug');
        var selectedServerType = $('#arsol_server_type').val();
        var selectedProvider = $('#arsol_server_provider_slug').val();
        var selectedGroup = $('#arsol_server_plan_group_slug').val();
        var savedPlan = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_plan_slug', true)); ?>';

        // Return early if any required field is missing
        if (!selectedServerType || !selectedProvider || !selectedGroup) {
            $planField.prop('disabled', true).empty();
            return;
        }

        // Get available plans via AJAX
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_group_plans',
                provider: selectedProvider,
                group: selectedGroup,
                server_type: selectedServerType
            },
            success: function(plans) {
                // If no plans available, disable field and return
                if (!plans.length) {
                    $planField.prop('disabled', true).empty();
                    return;
                }

                // Enable field and update options
                $planField.prop('disabled', false).empty();
                
                // Add plan options
                plans.forEach(function(plan) {
                    $planField.append($('<option></option>')
                        .val(plan.slug)
                        .text(plan.description || plan.slug)
                    );
                });

                // Select saved plan if it exists in available plans
                if (savedPlan && plans.some(plan => plan.slug === savedPlan)) {
                    $planField.val(savedPlan);
                }

                $planField.trigger('change');
            }
        });
    }

    // Call both initialization functions
    clearServerOptionFields();
    initializeServerTypeField();
    initializeServerProviderField();

    // Add event listener for server type changes
    $('#arsol_server_type').on('change', function() {
        initializeServerProviderField();
        // Plan group will be initialized after provider field updates
    });

    $('#arsol_server_provider_slug').on('change', function() {
        initializeServerPlanGroupField();
        // Plan field will be initialized after group field updates
    });

    $('#arsol_server_plan_group_slug').on('change', function() {
        initializeServerPlanField();
    });

    // Call both initialization functions
    clearServerOptionFields();
    initializeServerTypeField();
    initializeServerProviderField();

    function setRuncloudCheckboxState(checked = true, disabled = true) {
        var $checkbox = $('#arsol_server_manager_required');
        var savedValue = '<?php echo esc_js($is_server_manager ? "yes" : "no"); ?>';
        
        $checkbox.prop('checked', checked ? true : (savedValue === 'yes'))
                .prop('disabled', disabled)
                .trigger('change');
    }

    function updateServerTypeFields(serverType) {
        if (serverType === 'sites_server') {
            var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
            
            // Set Runcloud state
            setRuncloudCheckboxState(true, true);
        } else {
            // Reset Runcloud state
            setRuncloudCheckboxState(false, false);
        }
    }

    function toggleServerVisibility() {
        var isServerEnabled = $('#arsol_server').is(':checked');
        const $container = $('#woocommerce-product-data');
        let timeout;

        const observer = new MutationObserver((mutations) => {
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(() => {
                applyVisibilityRules(isServerEnabled);
            }, 100);
        });

        let isObserving = false;

        function startObserver() {
            if (!isObserving) {
                observer.observe($container[0], {
                    childList: true,
                    subtree: true,
                    attributes: false,
                    characterData: false
                });
                isObserving = true;
            }
        }

        function stopObserver() {
            if (isObserving) {
                observer.disconnect();
                isObserving = false;
            }
        }

        function applyVisibilityRules(isEnabled) {
            stopObserver();

            if (!isEnabled) {
                $container.find('.show_if_arsol_server, .show_if_arsol_sites_server, .show_if_arsol_application_server')
                    .hide()
                    .addClass('hidden');
                $container.find('.hide_if_arsol_server')
                    .show()
                    .removeClass('hidden');
            } else {
                $container.find('.show_if_arsol_server')
                    .show()
                    .removeClass('hidden');
                $container.find('.hide_if_arsol_server')
                    .hide()
                    .addClass('hidden');
                
                toggleServerTypeVisibility();
            }

            startObserver();
        }

        applyVisibilityRules(isServerEnabled);

        return function cleanup() {
            stopObserver();
            if (timeout) {
                clearTimeout(timeout);
            }
        };
    }

    function toggleServerTypeVisibility() {
        var serverType = $('#arsol_server_type').val();
        var isServerEnabled = $('#arsol_server').is(':checked');
        
        const $container = $('#woocommerce-product-data');
        const $siteServer = $container.find('.show_if_arsol_sites_server');
        const $hideSiteServer = $container.find('.hide_if_arsol_sites_server');
        const $appServer = $container.find('.show_if_arsol_application_server');
        const $hideAppServer = $container.find('.hide_if_arsol_application_server');
        const $maxApps = $container.find('.arsol_max_applications_field');

        function applyTypeVisibilityRules(type, enabled) {
            const showClass = 'visible';
            const hideClass = 'hidden';

            $siteServer.addClass(hideClass).hide();
            $appServer.addClass(hideClass).hide();
            $hideSiteServer.removeClass(hideClass).show();
            $hideAppServer.removeClass(hideClass).show();

            if (enabled) {
                if (type === 'sites_server') {
                    $siteServer.removeClass(hideClass).show();
                    $hideSiteServer.addClass(hideClass).hide();
                } 
                else if (type === 'application_server') {
                    $appServer.removeClass(hideClass).show();
                    $hideAppServer.addClass(hideClass).hide();
                }
            }

            $maxApps.toggleClass(hideClass, 
                !(enabled && (type === 'sites_server' || type === 'application_server'))
            );
        }

        applyTypeVisibilityRules(serverType, isServerEnabled);
    }

    const cleanup = toggleServerVisibility();

    $(window).on('unload', cleanup);

    $('#arsol_server').on('change', toggleServerVisibility);
    $('#arsol_server_type').on('change', function() {
        updateServerTypeFields($(this).val());
        toggleServerTypeVisibility();
    });

    toggleServerVisibility();

    var initialServerType = $('#arsol_server_type').val();
    if (initialServerType === 'sites_server') {
        updateServerTypeFields('sites_server');
    }

    $('#arsol_server').on('change', function() {
        if (!$(this).is(':checked')) {
            if ($('.arsol_server_settings_tab').hasClass('active')) {
                const $generalTab = $('.general_tab a:visible');
                const $variationsTab = $('.variations_tab a:visible'); 
                
                if ($generalTab.length && $generalTab.is(':visible')) {
                    $generalTab[0].click();
                } else if ($variationsTab.length && $variationsTab.is(':visible')) {
                    $variationsTab[0].click();
                }
            }
        }
    });


});
</script>





