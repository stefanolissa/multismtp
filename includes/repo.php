<?php

defined('ABSPATH') || exit;

add_filter('update_plugins_satollo-multismtp', function ($update, $plugin_data, $plugin_file, $locales) {
    $slug = 'multismtp';
    $data = get_option('smtp_update_data');
    if ($data && $data->updated < time() - WEEK_IN_SECONDS || isset($_GET['force-check'])) {
        $data = false;
    }

    if (!$data) {
        $response = wp_remote_get('https://www.satollo.net/repo/' . $slug . '/plugin.json');
        $data = json_decode(wp_remote_retrieve_body($response));
        if (is_object($data)) {
            $data->updated = time();
            update_option('smtp_update_data', $data, false);
        }
    }

    if (isset($data->version)) {

        $update = [
            'version' => $data->version,
            'slug' => $slug,
            'url' => 'https://www.satollo.net/plugins/multismtp',
            'package' => 'https://www.satollo.net/repo/' . $slug . '/' . $slug . '.zip'
        ];
        return $update;
    } else {
        return false;
    }
}, 0, 4);

function multismtp_render_markdown($text) {
    $text = preg_replace('/^### (.*$)/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^# (.*$)/m', '', $text);
    $text = preg_replace('/^- (.*$)/m', '- $1<br>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/m', '<strong>$1</strong>', $text);
    $text = preg_replace('/`(.*?)`/m', '<code>$1</code>', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    $text = wpautop($text, false);
    $text = wp_kses_post($text);
    return $text;
}

add_filter('plugins_api', function ($res, $action, $args) {
    $slug = 'multismtp';
    if ($action !== 'plugin_information' || $args->slug !== $slug) {
        return $res;
    }

    $response = wp_remote_get('https://www.satollo.net/repo/' . $slug . '/CHANGELOG.md');
    $changelog = '';
    if (wp_remote_retrieve_response_code($response) == '200') {
        $changelog = wp_remote_retrieve_body($response);
        $changelog = multismtp_render_markdown($changelog);
    }

    $response = wp_remote_get('https://www.satollo.net/repo/' . $slug . '/README.md');
    $readme = '';
    if (wp_remote_retrieve_response_code($response) == '200') {
        $readme = wp_remote_retrieve_body($response);
        $readme = multismtp_render_markdown($readme);
    }

    $res = new stdClass();
    $res->name = 'SMTP';
    $res->slug = 'smtp';
    $res->version = MULTISMTP_VERSION;
    $res->author = '<a href="https://www.satollo.net">Stefano Lissa</a>';
    $res->homepage = 'https://www.satollo.net/plugins/smtp';
    $res->download_link = 'https://www.satollo.net/repo/' . $slug . '/' . $slug . '.zip';

    $res->sections = [
        'description' => $readme,
        'changelog' => $changelog,
    ];

    $res->banners = [
        'low' => 'https://www.satollo.net/repo/' . $slug . '/banner.png',
        'high' => 'https://www.satollo.net/repo/' . $slug . '/banner.png'
    ];

    $res->icons = [
        '1x' => 'https://www.satollo.net/repo/' . $slug . '/icon.png',
        '2x' => 'https://www.satollo.net/repo/' . $slug . '/icon.png'
    ];

    return $res;
}, 20, 3);
