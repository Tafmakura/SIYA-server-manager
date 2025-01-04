<?php 

use Siya\AdminSettings\Slugs;

global $post;
$slugs = new Slugs();

?>

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

        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_applications',
            'label'       => __('Maximum Applications', 'woocommerce'),
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
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_max_staging_sites',
            'label'       => __('Maximum Staging Sites', 'woocommerce'),
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
        ));
        woocommerce_wp_checkbox(array(
            'id'          => '_arsol_wordpress_server',
            'label'       => __('WordPress Server', 'woocommerce'),
            'description' => __('Enable this option to set up a WordPress server.', 'woocommerce'),
            'desc_tip'    => 'true',
        ));
        ?>
        <div class="arsol_ecommerce_field">
            <?php
            woocommerce_wp_checkbox(array(
                'id'          => '_arsol_ecommerce',
                'label'       => __('WordPress Ecommerce', 'woocommerce'),
                'description' => __('Enable this option if the server will support ecommerce.', 'woocommerce'),
                'desc_tip'    => 'true',
            ));
            ?>
        </div>
        <?php
        // Provider Dropdown
        $providers = $slugs->get_provider_slugs();
        $selected_provider = get_post_meta($post->ID, '_arsol_server_provider_slug', true);

        woocommerce_wp_select(array(
            'id'          => '_arsol_server_provider_slug',
            'label'       => __('Server Provider', 'woocommerce'),
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
            'label'       => __('Server Group', 'woocommerce'),
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
            'label'       => __('Server Plan', 'woocommerce'),
            'description' => __('Select the server plan.', 'woocommerce'),
            'desc_tip'    => true,
            'options'     => $plan_options,
            'value'       => $selected_plan
        ));
        ?>
    </div>


<?php
// Debug variable dump
echo '<pre>';

echo 'Providers: ';
print_r($slugs->get_provider_slugs());

echo "\nProvider Groups:\n";
foreach ($slugs->get_provider_slugs() as $provider) {
    echo "\n$provider Groups: ";
    print_r($slugs->get_provider_group_slugs($provider));
}

echo "\nPlans for each Provider/Group:\n";
foreach ($slugs->get_provider_slugs() as $provider) {
    $groups = $slugs->get_provider_group_slugs($provider);
    foreach ($groups as $group) {
        echo "\n$provider - $group Plans: ";
        print_r($slugs->get_filtered_plans($provider, $group));
    }
}
echo '</pre>';
?>


</div>

<style>
select {
    width: 300px; /* Fixed width for dropdowns */
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
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
                    plans = JSON.parse(response);  // Parse the response as JSON
                    if (!Array.isArray(plans)) {
                        throw new Error('Parsed response is not an array');
                    }
                } catch (e) {
                    console.error('Failed to parse plans:', e);
                    plans = [];
                }
                var $planSelect = $('#_arsol_server_plan_slug');
                $planSelect.empty();
                
                if (plans.length === 0) {
                    $planSelect.prop('disabled', true);
                } else {
                    $planSelect.prop('disabled', false);
                    plans.forEach(function(plan) {
                        $planSelect.append(new Option(plan.slug, plan.slug));
                    });

                    // Set the selected plan
                    var selectedPlan = '<?php echo esc_js(get_post_meta($post->ID, '_arsol_server_plan_slug', true)); ?>';
                    $planSelect.val(selectedPlan);
                }
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

    $('#_arsol_server_provider_slug').on('change', function() {
        var provider = $(this).val();
        updateGroups(provider);
    });

    $('#_arsol_server_group_slug').on('change', function() {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $(this).val();
        updatePlans(provider, group);
    });

    $('#_arsol_wordpress_server').on('change', function() {
        if ($(this).is(':checked')) {
            setWordPressProvider();
        } else {
            $('#_arsol_server_provider_slug').prop('disabled', false);
            $('#_arsol_server_group_slug').prop('disabled', false);
        }
    });

    // Initial load
    var initialProvider = $('#_arsol_server_provider_slug').val();
    if (initialProvider) {
        updateGroups(initialProvider);
    }

    if ($('#_arsol_wordpress_server').is(':checked')) {
        setWordPressProvider();
    }

    // Ensure required fields are filled before saving
    $('#post').on('submit', function(e) {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $('#_arsol_server_group_slug').val();
        var plan = $('#_arsol_server_plan_slug').val();

        if (!provider || !group || !plan) {
            alert('Please select a Server Provider, Server Group, and Server Plan.');
            e.preventDefault();
            return false;
        }
    });
});
</script>


