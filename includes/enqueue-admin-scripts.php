<?php


// Enqueue admin scripts only on user profile and edit user pages
add_action('admin_enqueue_scripts', function ($hook) {
    // Load only on user profile or edit user screen
    if ($hook !== 'user-edit.php' && $hook !== 'profile.php') {
        return;
    }

    // Enqueue your custom admin.js script
    wp_enqueue_script(
        'clickup-admin-js',
        plugin_dir_url(__DIR__) . 'assets/admin.js',
        ['jquery'],
        '1.0',
        true
    );

    // Localize script to provide AJAX URL to JS
    wp_localize_script('clickup-admin-js', 'clickup_ajax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
});
