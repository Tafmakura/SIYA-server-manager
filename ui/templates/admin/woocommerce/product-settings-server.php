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
        // Simplify provider/group/plan dropdowns
        $providers = $slugs->get_provider_slugs();
        woocommerce_wp_select(array(
            'id'      => 'arsol_server_provider_slug',
            'label'   => __('Server provider', 'woocommerce'),
            'description' => __('Select the server provider.', 'woocommerce'),
            'desc_tip'    => true,
            'options' => array_combine($providers, array_map('ucfirst', $providers)),
            'value'       => $selected_provider,
            'required'    => true,
            'custom_attributes' => array('disabled' => 'disabled')  // Disable on load
        ));

        $groups = $selected_provider ? $slugs->get_provider_group_slugs($selected_provider) : [];
        woocommerce_wp_select(array(
            'id'      => 'arsol_server_plan_group_slug',
            'label'   => __('Server plan group', 'woocommerce'),
            'description' => __('Select the server plan group, which the plan you want belongs to.', 'woocommerce'),
            'desc_tip'    => true,
            'options' => array_combine($groups, $groups),
            'value'       => $selected_group,
            'custom_attributes' => array('disabled' => 'disabled')  // Disable on load
        ));

        $plans = ($selected_provider && $selected_group)
            ? $slugs->get_filtered_plans($selected_provider, $selected_group)
            : [];
        $plan_options = [];
        foreach ($plans as $plan) {
            $plan_options[$plan['slug']] = $plan['slug'];
        }
        woocommerce_wp_select(array(
            'id'      => 'arsol_server_plan_slug',
            'label'   => __('Server plan', 'woocommerce'),
            'description' => __('Select the server plan.', 'woocommerce'),
            'desc_tip'    => true,
            'options' => $plan_options,
            'value'       => $selected_plan,
            'custom_attributes' => empty($selected_group) ? array('disabled' => 'disabled') : array()
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
    // Remove validation code
    // $('#post').on('submit'...) - removed
    // $('#arsol_server_region, #arsol_server_image').on('input'...) - removed
    
    // Keep only visibility and UI handling code
    function updateGroups(provider, callback) {
        var serverType = $('#arsol_server_type').val();
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_groups',
                provider: provider,
                server_type: serverType !== 'sites_server' ? serverType : null
            },
            success: function(groups) {
                var $groupSelect = $('#arsol_server_plan_group_slug');
                var currentValue = $groupSelect.val();
                $groupSelect.empty();
                
                if (groups.length === 0 && serverType !== 'sites_server') {
                    $groupSelect.prop('disabled', true);
                    $groupSelect.append(new Option('empty', '')); // Add empty text
                } else {
                    $groupSelect.prop('disabled', false);
                    groups.forEach(function(group) {
                        $groupSelect.append(new Option(group, group));
                    });
                    
                    // Try to keep existing selection if still valid
                    if (groups.includes(currentValue)) {
                        $groupSelect.val(currentValue);
                    }
                }
                
                $groupSelect.trigger('change');
                if (callback) callback(groups);
            }
        });
    }

    function updatePlans(provider, group) {
        var serverType = $('#arsol_server_type').val();
        var $planSelect = $('#arsol_server_plan_slug');
        var savedPlan = '<?php echo esc_js($selected_plan); ?>';
        
        // Always start with disabled empty state
        $planSelect.empty()
                  .prop('disabled', true)
                  .append(new Option('', ''));
        
        if (!provider || !group) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_group_plans',
                provider: provider,
                group: group,
                server_type: serverType !== 'sites_server' ? serverType : null
            },
            success: function(response) {
                try {
                    var plans = typeof response === 'string' ? JSON.parse(response) : response;
                    if (!Array.isArray(plans)) {
                        plans = Object.values(plans);
                    }
                    
                    // If no plans available, keep disabled empty state
                    if (!plans || plans.length === 0) {
                        return;
                    }
                    
                    // Check if saved plan exists in current context
                    var planExists = savedPlan && plans.some(p => p.slug === savedPlan);
                    
                    // Enable field if we have plans
                    $planSelect.prop('disabled', false).empty();
                    $planSelect.append(new Option('', '')); // Empty option first
                    
                    // Add all available plans
                    plans.forEach(function(plan) {
                        $planSelect.append(new Option(plan.slug, plan.slug));
                    });
                    
                    // Set value based on context
                    if (planExists) {
                        // Case 1: Saved plan matches context - show it
                        $planSelect.val(savedPlan);
                    } else {
                        // Case 2: Saved plan doesn't match but we have alternatives - leave empty but enabled
                        $planSelect.val('');
                    }
                    
                } catch (e) {
                    console.error('Failed to parse plans:', e);
                    // Case 3: Error - disabled empty state
                    $planSelect.empty()
                              .prop('disabled', true)
                              .append(new Option('', ''));
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch plans:', error);
                // Case 3: Error - disabled empty state
                $planSelect.empty()
                          .prop('disabled', true)
                          .append(new Option('', ''));
            }
        });
    }

    function setRuncloudCheckboxState(checked = true, disabled = true) {
        var $checkbox = $('#arsol_server_manager_required');
        var savedValue = '<?php echo esc_js($is_server_manager ? "yes" : "no"); ?>';
        
        $checkbox.prop('checked', checked ? true : (savedValue === 'yes'))
                .prop('disabled', disabled)
                .trigger('change');
    }

    function updateServerTypeFields(serverType) {
        var $providerSelect = $('#arsol_server_provider_slug');
        var $groupSelect = $('#arsol_server_plan_group_slug');
        var $planSelect = $('#arsol_server_plan_slug');

        if (serverType === 'sites_server') {
            var wpProvider = '<?php echo esc_js(get_option('siya_wp_server_provider')); ?>';
            var wpGroup = '<?php echo esc_js(get_option('siya_wp_server_group')); ?>';

            // Set provider for sites server
            $providerSelect.empty()
                          .append(new Option(wpProvider.charAt(0).toUpperCase() + wpProvider.slice(1), wpProvider))
                          .val(wpProvider)
                          .prop('disabled', true);
            
            // Set Runcloud state
            setRuncloudCheckboxState(true, true);
            
            // Update groups and plans for sites server
            updateGroups(wpProvider, function(groups) {
                if (groups.includes(wpGroup)) {
                    $('#arsol_server_plan_group_slug').val(wpGroup).prop('disabled', true);
                    updatePlans(wpProvider, wpGroup);
                }
            });
        } else {
            // Reset Runcloud state
            setRuncloudCheckboxState(false, false);
            
            // Only clear and disable if empty
            if (!$providerSelect.val()) {
                $providerSelect.prop('disabled', false).empty();
            }
            if (!$groupSelect.val()) {
                $groupSelect.prop('disabled', false).empty();
            }
            if (!$planSelect.val()) {
                $planSelect.prop('disabled', false).empty();
            }
            
            if (serverType) {
                updateProvidersByServerType(serverType);
            }
        }
    }

    function disableAllDropdowns(serverType) {
        if (serverType === 'sites_server') return;

        var $providerSelect = $('#arsol_server_provider_slug');
        var $groupSelect = $('#arsol_server_plan_group_slug');
        var $planSelect = $('#arsol_server_plan_slug');

        // Disable and clear all dropdowns at once
        $providerSelect.prop('disabled', true).empty().append(new Option('empty', ''));
        $groupSelect.prop('disabled', true).empty().append(new Option('empty', ''));
        $planSelect.prop('disabled', true).empty().append(new Option('empty', ''));
    }

    function updateProvidersByServerType(serverType) {
        if (!serverType) return;
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_providers_by_server_type',
                server_type: serverType
            },
            success: function(providers) {
                var $providerSelect = $('#arsol_server_provider_slug');
                var currentValue = $providerSelect.val();
                $providerSelect.empty();
                
                if (providers.length === 0) {
                    $providerSelect.prop('disabled', true);
                    $providerSelect.append(new Option('empty', '')); // Add empty text
                } else {
                    $providerSelect.prop('disabled', false);
                    providers.forEach(function(provider) {
                        var providerName = provider.charAt(0).toUpperCase() + provider.slice(1);
                        $providerSelect.append(new Option(providerName, provider));
                    });
                    
                    // Try to keep existing selection if still valid
                    if (providers.includes(currentValue)) {
                        $providerSelect.val(currentValue);
                    }
                }
                $providerSelect.trigger('change');
            }
        });
    }

    function toggleServerVisibility() {
        var isServerEnabled = $('#arsol_server').is(':checked');
        const $container = $('#woocommerce-product-data');
        
        // Debounce the observer callback
        let timeout;
        const observer = new MutationObserver((mutations) => {
            if (timeout) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(() => {
                applyVisibilityRules(isServerEnabled);
            }, 100); // Delay of 100ms
        });

        // Observe only when needed
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
            stopObserver(); // Pause observation during DOM updates

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

            startObserver(); // Resume observation after updates
        }

        // Initial application
        applyVisibilityRules(isServerEnabled);

        // Cleanup function
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
        
        // Performance optimization: Cache selectors
        const $siteServer = $container.find('.show_if_arsol_sites_server');
        const $hideSiteServer = $container.find('.hide_if_arsol_sites_server');
        const $appServer = $container.find('.show_if_arsol_application_server');
        const $hideAppServer = $container.find('.hide_if_arsol_application_server');
        const $maxApps = $container.find('.arsol_max_applications_field');

        function applyTypeVisibilityRules(type, enabled) {
            // Use CSS classes for performance
            const showClass = 'visible';
            const hideClass = 'hidden';

            // Hide all first
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

            // Toggle max applications visibility
            $maxApps.toggleClass(hideClass, 
                !(enabled && (type === 'sites_server' || type === 'application_server'))
            );
        }

        // Apply rules immediately
        applyTypeVisibilityRules(serverType, isServerEnabled);
    }

    // Initialize with cleanup handling
    const cleanup = toggleServerVisibility();

    // Cleanup on page unload
    $(window).on('unload', cleanup);

    // Event handlers
    $('#arsol_server').on('change', toggleServerVisibility);
    $('#arsol_server_type').on('change', function() {
        updateServerTypeFields($(this).val());
        toggleServerTypeVisibility();
    });

    // Initialize visibility on page load
    toggleServerVisibility();

    // Initialize server type fields if sites_server is selected
    var initialServerType = $('#arsol_server_type').val();
    if (initialServerType === 'sites_server') {
        updateServerTypeFields('sites_server');
    }

    // Add event handler for server checkbox that switches tabs when unchecked
    $('#arsol_server').on('change', function() {
        if (!$(this).is(':checked')) {
            // Check if we're currently on the server settings tab
            if ($('.arsol_server_settings_tab').hasClass('active')) {
                // Try to find visible tabs
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

    // Add provider change handler
    $('#arsol_server_provider_slug').on('change', function() {
        var provider = $(this).val();
        var serverType = $('#arsol_server_type').val();
        
        if (provider) {
            updateGroups(provider);
        } else {
            $('#arsol_server_plan_group_slug').empty()
                .prop('disabled', true)
                .append(new Option('empty', ''));
        }
    });

    // Modify updateGroups function
    function updateGroups(provider, callback) {
        var serverType = $('#arsol_server_type').val();
        var $groupSelect = $('#arsol_server_plan_group_slug');
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_groups',
                provider: provider,
                server_type: serverType !== 'sites_server' ? serverType : null
            },
            success: function(groups) {
                $groupSelect.empty();
                
                if (Array.isArray(groups) && groups.length > 0) {
                    $groupSelect.prop('disabled', false);
                    groups.forEach(function(group) {
                        $groupSelect.append(new Option(group, group));
                    });
                    $groupSelect.trigger('change');
                } else {
                    $groupSelect.prop('disabled', true)
                        .append(new Option('empty', ''));
                }
                
                if (callback) callback(groups);
            },
            error: function() {
                $groupSelect.empty()
                    .prop('disabled', true)
                    .append(new Option('empty', ''));
            }
        });
    }

    // Add group change handler
    $('#arsol_server_plan_group_slug').on('change', function() {
        var provider = $('#arsol_server_provider_slug').val();
        var group = $(this).val();
        var $planSelect = $('#arsol_server_plan_slug');
        
        if (!group) {
            $planSelect.empty()
                      .prop('disabled', true)
                      .append(new Option('', ''));
        } else {
            updatePlans(provider, group);
        }
    });

    // Modify updatePlans function to handle the response better
    function updatePlans(provider, group) {
        var serverType = $('#arsol_server_type').val();
        var $planSelect = $('#arsol_server_plan_slug');
        var currentPlan = $planSelect.val(); // Store current selection
        
        if (!provider || !group) {
            $planSelect.empty().prop('disabled', true);
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_group_plans',
                provider: provider,
                group: group,
                server_type: serverType !== 'sites_server' ? serverType : null
            },
            success: function(response) {
                var oldValue = $planSelect.val(); // Store current value
                $planSelect.empty();
                
                try {
                    var plans = typeof response === 'string' ? JSON.parse(response) : response;
                    if (!Array.isArray(plans)) {
                        plans = Object.values(plans);
                    }
                    
                    if (plans.length === 0) {
                        $planSelect.prop('disabled', true);
                    } else {
                        $planSelect.prop('disabled', false);
                        plans.forEach(function(plan) {
                            $planSelect.append(new Option(plan.slug, plan.slug));
                        });
                        
                        // Restore previous selection if it exists in new options
                        if (oldValue && plans.some(p => p.slug === oldValue)) {
                            $planSelect.val(oldValue);
                        }
                    }
                } catch (e) {
                    console.error('Failed to parse plans:', e);
                    $planSelect.prop('disabled', true);
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to fetch plans:', error);
                $planSelect.empty().prop('disabled', true);
            }
        });
    }

});
</script>





