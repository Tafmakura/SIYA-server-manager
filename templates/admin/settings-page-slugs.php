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

<style>
.plan-row {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.plan-field {
    margin-bottom: 15px;
}

.plan-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: normal;
    font-size: 14px;
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
</style>

<script>
jQuery(document).ready(function($) {
    $('.add-plan').on('click', function() {
        var $repeater = $(this).closest('.plan-repeater');
        var $template = $repeater.find('.template').clone();
        var provider = $repeater.data('provider');
        var index = $repeater.find('.plan-row').length - 1;

        $template.removeClass('template').show()
            .find('input, textarea').each(function() {
                var name = $(this).attr('name');
                $(this).attr('name', 'siya_' + provider + '_plans[' + index + '][' + 
                    (name.includes('slug') ? 'slug' : 'description') + ']');
            });

        $(this).before($template);
    });

    $(document).on('click', '.remove-plan', function() {
        $(this).closest('.plan-row').remove();
    });
});
</script>
