<?php
/**
 * Plugin Name: ClickUp Client Dashboard
 * Description: Connect with API.
 * Version: 1.0
 * Author: Nibir
 */

if (!defined('ABSPATH')) exit;

// Load all modules
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/acf-space-field.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handlers.php';
require_once plugin_dir_path(__FILE__) . 'includes/client-dashboard-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/enqueue-admin-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/parsedown.php';
// $template_path = plugin_dir_path(__FILE__) . 'templates/iframe.php';



wp_localize_script(
    'clickup-admin-js',
    'clickup_ajax',
    ['ajax_url' => admin_url('admin-ajax.php')]
);
