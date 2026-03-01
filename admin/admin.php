<?php

defined('ABSPATH') || exit;

//$smtp_version = get_option('monitor_version');
//if (SMTP_VERSION !== $smtp_version) {
//    if (WP_DEBUG) {
//        error_log('SMTP > Version change');
//    }
//    include_once __DIR__ . '/activate.php';
//    update_option('smtp_version', SMTP_VERSION, false);
//}


add_action('admin_menu', function () {

    add_options_page(
            'MultiSMTP', 'MultiSMTP', 'administrator', 'multismtp',
            function () {
                include __DIR__ . '/settings.php';
            }
    );
});

add_action('admin_enqueue_scripts', function () {
    if ($_GET['page'] ?? '' === 'multismtp') {
        wp_enqueue_script('multismtp', plugin_dir_url(__FILE__) . 'admin.js', ['jquery-ui-accordion'], MULTISMTP_VERSION, true);
        wp_enqueue_style('multismtp', plugin_dir_url(__FILE__) . 'admin.css', [], MULTISMTP_VERSION);
    }
});

add_filter('plugin_action_links_multismtp/plugin.php', function ($links) {
    $links[] = '<a href="admin.php?page=multismtp">' . __('Settings') . '</a>';
    return $links;
});

