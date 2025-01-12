<!--  This is a template file for the SSH Keys settings page. -->
<div class="wrap">
    <h1><?php _e('SSH Keys', 'arsol_siya'); ?></h1>
    <form method="post" action="options.php">
        <?php
        // ...existing code...
        // settings_fields('siya_settings_ssh'); // Uncomment or adjust as needed
        // do_settings_sections('siya_settings_ssh'); // Uncomment or adjust as needed
        submit_button();
        ?>
    </form>
</div>