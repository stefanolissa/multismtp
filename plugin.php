<?php

/**
 * Plugin Name: MultiSMTP
 * Description: The lighter plugin to connect WP to multiple SMTP
 * Version: 0.0.2
 * Author: Stefano Lissa
 * Author URI: https://www.satollo.net
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: smtp
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Plugin URI: https://www.satollo.net/plugins/multismtp
 * Update URI: satollo-smtp
 */
defined('ABSPATH') || exit;

define('MULTISMTP_VERSION', '0.0.2');
if (!defined('MULTISMTP_MAX')) {
    define('MULTISMTP_MAX', 3);
}

add_filter('phpmailer_init', 'multismtp_phpmailer_init', 5);

function multismtp_phpmailer_init($mailer) {

    static $settings = null;
    static $last = 0; // The number of the SMTP to use when rotating
    static $smtp; // The SMTP to use (0, 1, 2, 3, -1, -2, ...)
    static $which;

    // First setup
    if (is_null($settings)) {
        $settings = get_option('multismtp_settings', []);
        $smtp = (int) ($settings['smtp'] ?? 0);
        if ($smtp == -2) { // Rotate once per session
            $last = (int) get_option('multismtp_last', -1);
            $last++;
            if ($last >= count($settings['smtps'])) {
                $last = 0;
            }
            update_option('multismtp_last', $last, false);
            $which = $settings['smtps'][$last];
        } elseif ($smtp == -1) { // Rotate once per email
            $last = (int) get_option('multismtp_last', -1);
        } else {
            $which = $smtp;
        }
    }

    if (!$smtp) {
        return $mailer;
    }

    // Rotate per email, $which changes every email
    if ($smtp == -1) {
        $last++;
        if ($last >= count($settings['smtps'])) {
            $last = 0;
        }
        update_option('multismtp_last', $last, false);
        $which = $settings['smtps'][$last];
    }

    /** @var \PHPMailer\PHPMailer\PHPMailer $mailer */
    $mailer->IsSMTP();
    $mailer->Host = $settings['host_' . $which] ?? '';
    $mailer->Port = $settings['port_' . $which] ?? '';
    $mailer->SMTPSecure = $settings['secure_' . $which] ?? '';
    $mailer->SMTPAutoTLS = true;
    $mailer->SMTPAuth = true;
    $mailer->Username = $settings['username_' . $which] ?? '';
    $mailer->Password = $settings['password_' . $which] ?? '';

    if (!empty($settings['from_email'])) {
        $mailer->setFrom($settings['from_email']);
    }

    if (isset($settings['delay_' . $which])) {
        usleep($settings['delay_' . $which] * 1000);
    }

    if (WP_DEBUG) {
        $mailer->addCustomHeader('X-MultiSMTP', (string) $which);
//        $mailer->Subject .= ' - SMTP ' . $which;
//        error_log(print_r($mailer, true));
    }

    return $mailer;
}

if (is_admin()) {
    require_once __DIR__ . '/admin/admin.php';
}

if (is_admin() || defined('DOING_CRON') && DOING_CRON) {
    include_once __DIR__ . '/includes/repo.php';
}


