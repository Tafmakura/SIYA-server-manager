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
            $plan_options[$plan['slug']] = $plan['description'];
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
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Provider change handler
    $('#_arsol_server_provider_slug').on('change', function() {
        var provider = $(this).val();
        
        // AJAX call to get groups
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_provider_groups',
                provider: provider
            },
            success: function(groups) {
                var $groupSelect = $('#_arsol_server_group_slug');
                $groupSelect.empty();
                
                groups.forEach(function(group) {
                    $groupSelect.append(new Option(group, group));
                });
                
                $groupSelect.trigger('change');
            }
        });
    });

    // Group change handler
    $('#_arsol_server_group_slug').on('change', function() {
        var provider = $('#_arsol_server_provider_slug').val();
        var group = $(this).val();
        
        // AJAX call to get plans
        $.ajax({
            url: ajaxurl,
            data: {
                action: 'get_group_plans',
                provider: provider,
                group: group
            },
            success: function(plans) {
                var $planSelect = $('#_arsol_server_plan_slug');
                $planSelect.empty();
                
                plans.forEach(function(plan) {
                    $planSelect.append(new Option(plan.description, plan.slug));
                });
            }
        });
    });
});
</script>
