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
            'id'          => '_arsol_server_provider_slug',
            'label'       => __('Server Provider Slug', 'woocommerce'),
            'description' => __('Enter the server provider slug.', 'woocommerce'),
            'desc_tip'    => 'true',
            'custom_attributes' => array(
                'required' => 'required'
            ),
        ));
        woocommerce_wp_text_input(array(
            'id'          => '_arsol_server_plan_slug',
            'label'       => __('Server Plan Slug', 'woocommerce'),
            'description' => __('Enter the server plan slug.', 'woocommerce'),
            'desc_tip'    => 'true',
            'custom_attributes' => array(
                'required' => 'required'
            ),
        ));
        ?>
        <div class="arsol_server_type_slug_field">
            <?php
            woocommerce_wp_text_input(array(
                'id'          => '_arsol_server_type_slug',
                'label'       => __('Server Type Slug', 'woocommerce'),
                'description' => __('Enter the server type slug.', 'woocommerce'),
                'desc_tip'    => 'true',
            ));
            ?>
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
    </div>
</div>
