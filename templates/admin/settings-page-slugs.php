<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('siya_slugs_options');
        do_settings_sections('siya-slugs-settings');
        submit_button();
        ?>
    </form>
</div>
