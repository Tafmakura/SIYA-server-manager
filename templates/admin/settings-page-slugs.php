<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('siya_settings');
        do_settings_sections('siya-settings'); 
        submit_button();
        ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.add-plan').on('click', function() {
        const container = $(this).closest('.plan-repeater');
        const provider = container.data('provider');
        const index = container.find('.plan-row').length;
        
        const newRow = $(`
            <div class="plan-row">
                <div class="plan-field">
                    <label><?php _e('Plan slug', 'siya'); ?></label>
                    <input type="text" name="siya_${provider}_plans[${index}][slug]" placeholder="<?php _e('Enter plan slug', 'siya'); ?>" />
                </div>
                <div class="plan-field">
                    <label><?php _e('Description', 'siya'); ?></label>
                    <textarea name="siya_${provider}_plans[${index}][description]" placeholder="<?php _e('Enter plan description', 'siya'); ?>"></textarea>
                </div>
                <button type="button" class="button remove-plan"><?php _e('Remove', 'siya'); ?></button>
            </div>
        `);
        
        $(this).before(newRow);
    });

    $(document).on('click', '.remove-plan', function() {
        $(this).closest('.plan-row').remove();
    });
});
</script>

<style>
.plan-row {
    margin-bottom: 10px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}

.plan-field {
    margin-bottom: 10px;
}

.plan-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: normal;
    font-size: 14px;
}

.plan-field input[type="text"],
.plan-field textarea {
    width: 100%;
    max-width: 300px;
    margin-bottom: 5px;
}

.plan-field textarea {
    height: 60px;
}

.remove-plan {
    color: #dc3545;
}
</style>
