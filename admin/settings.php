<?php
defined('ABSPATH') || exit;

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_admin_referer('save');

    if (isset($_POST['burst'])) {
        $data = wp_unslash($_POST['data']);
        for ($i = 1; $i <= MULTISMTP_MAX; $i++) {
            wp_mail(sanitize_email($data['test_email']), 'Test email from MultiSMTP', 'This is a simple message to check the correct SMTP connection and message delivery');
        }
    }

    for ($i = 1; $i <= MULTISMTP_MAX; $i++) {

        if (isset($_POST['test_' . $i])) {
            // Globals required to communicate with hook functions
            global $multismtp_test_settings, $multismtp_error;

            // Intercept errors to display them during tests
            add_action('wp_mail_failed', function ($wp_error) {
                global $multismtp_error;
                $multismtp_error = $wp_error;
            }, 0);

            // Overrides the mailer configuration during tests
            add_filter('phpmailer_init', function ($mailer) {
                global $multismtp_test_settings;
                $which = $multismtp_test_settings['which'];

                /** @var \PHPMailer\PHPMailer\PHPMailer $mailer */

                $mailer->IsSMTP();
                $mailer->Host = $multismtp_test_settings['host_' . $which];
                $mailer->Port = $multismtp_test_settings['port_' . $which];
                $mailer->SMTPSecure = $multismtp_test_settings['protocol_' . $which];
                $mailer->SMTPAutoTLS = true;
                $mailer->SMTPAuth = true;
                $mailer->Username = $multismtp_test_settings['username_' . $which];
                $mailer->Password = $multismtp_test_settings['password_' . $which];

                if (!empty($multismtp_test_settings['sender_email'])) {
                    $mailer->setFrom($multismtp_test_settings['sender_email']);
                }

                return $mailer;
            }, 9999);

            $data = wp_unslash($_POST['data']);
            // TODO
            $multismtp_test_settings = $data;
            $multismtp_test_settings['which'] = $i;
            wp_mail(sanitize_email($data['test_email']), 'Test email from the SMTP ' . $i, 'This is a simple message to check the correct SMTP connection and message delivery');
            if (isset($multismtp_error)) {
                global $phpmailer;

                /** @var WP_Error $multismtp_error */
                /** @var \PHPMailer\PHPMailer\PHPMailer $phpmailer */
                $error = 'SMTP ' . $i . ' ERROR<br><br>';

                // phpmailer has better error info than what's inside the exception
                if (isset($phpmailer)) {
                    $error .= $phpmailer->ErrorInfo . '<br><br>';
                } else {
                    $error .= $multismtp_error->get_error_message() . '<br><br>';
                }

                //$error .= '<pre>' . wp_json_encode($multismtp_error->get_error_data(), JSON_PRETTY_PRINT) . '</pre><br><br>';
                if (stripos($error, 'could not connect') !== false) {
                    $error .= '<br><br>This error means you need to check or change the parameters OR, worse, your hosting provider do not let your site to connect to the SMTP.';
                } elseif (stripos($error, 'timeout') !== false) {
                    $error .= '<br><br>This error means your hosting provider do not let your site to connect to the SMTP and is blocking it with a firewall rule. Contact them!';
                }
            }
            break;
        }
    }

    if (isset($_POST['save'])) {
        $data = wp_unslash($_POST['data']);

        for ($i = 1; $i <= MULTISMTP_MAX; $i++) {
            $data['host_' . $i] = strtolower(trim($data['host_' . $i]));
            $data['username_' . $i] = trim($data['username_' . $i]);
            $data['password_' . $i] = trim($data['password_' . $i]);
            $data['port_' . $i] = (int) $data['port_' . $i];
            $data['protocol_' . $i] = trim($data['protocol_' . $i]);
            if ($data['protocol_' . $i] && $data['protocol_' . $i] !== 'ssl' && $data['protocol_' . $i] !== 'tls') {
                $data['protocol_' . $i] = '';
            }
            $data['delay_' . $i] = (int) $data['delay_' . $i];
            if ($data['delay_' . $i] < 0) {
                $data['delay_' . $i] = 0;
            }
        }
        $data['from_email'] = sanitize_email($data['from_email']);
        $data = array_filter($data);
        $data['smtp'] = (int) $data['smtp'];
        if ($data['smtp'] < -3 || $data['smtp'] > MULTISMTP_MAX) {
            $data['smtp'] = 0;
        }
        if ($data['smtp'] < 0) {
            $data['smtps'] = $data['smtps'] ?? [];
        }

        update_option('multismtp_settings', $data, false);
        update_option('multismtp_last', -1, false);
    }
} else {
    $data = get_option('multismtp_settings', []);
    $data['smtp'] = (int) ($data['smtp'] ?? 0);
    $data['smtps'] = $data['smtps'] ?? [];
}
?>

<div style="margin-left: -20px; background-color: #3B2D9B; color: white; padding: 1rem 20px; font-size: 1.2rem; display: flex; justify-content: space-between">
    <div>MultiSMTP</div>
    <div></div>
</div>

<div class="wrap">
    <h2>Settings</h2>
    <?php
    if ($error) {
        echo '<div class="notice notice-error"><p>', wp_kses_post($error), '</p></div>';
    }
    if (!$data['smtp']) {
        echo '<div class="notice notice-warning"><p>The SMTP is not enabled, when ready enable it.</p></div>';
    }

    if ($data['smtp'] < 0 && !$data['smtps']) {
        echo '<div class="notice notice-error"><p>Rotate is selected but no SMTPs have been specified.</p></div>';
    }
    ?>

    <p>
        <a href="https://www.satollo.net/plugins/multismtp" target="_blank">Read the official page, it's short.</a>. This plugin, when uninstalled,
        does not left traces on your site.
    </p>

    <form method="post">

        <?php wp_nonce_field('save'); ?>

        <table class="form-table">

            <tbody>

                <tr>
                    <th>
                        SMTP to use
                    </th>
                    <td>
                        <select name="data[smtp]" id="data-smtp">
                            <option value="0" <?php echo $data['smtp'] == 0 ? 'selected' : ''; ?>>Disabled</option>
                            <?php for ($i = 1; $i <= MULTISMTP_MAX; $i++) { ?>
                                <option value="<?php echo (int) $i; ?>" <?php echo $data['smtp'] == $i ? 'selected' : ''; ?>><?php echo (int) $i; ?></option>
                            <?php } ?>
                            <option value="-1" <?php echo $data['smtp'] == -1 ? 'selected' : ''; ?>>Rotate once per email</option>
                            <option value="-2" <?php echo $data['smtp'] == -2 ? 'selected' : ''; ?>>Rotate once per session</option>
                        </select>

                        <div id="enabled" style="margin-top: 1rem">
                            Rotate between<br>

                            <?php for ($i = 1; $i <= MULTISMTP_MAX; $i++) { ?>
                                <label style="margin-right: 2rem;"><input type="checkbox" name="data[smtps][]" value="<?php echo (int) $i; ?>" <?= in_array($i, $data['smtps']) ? 'checked' : '' ?>> SMTP <?php echo (int) $i; ?></label>
                                <?php } ?>
                        </div>

                        <p class="description">
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>
                        From email
                    </th>
                    <td>
                        <input type="email" name="data[from_email]" size="40" value="<?= esc_attr($data['from_email'] ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th>
                        Email for tests
                    </th>
                    <td>
                        <input type="email" name="data[test_email]" size="40" value="<?= esc_attr($data['test_email'] ?? ''); ?>">
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="msmtp-accordion">
            <?php for ($i = 1; $i <= MULTISMTP_MAX; $i++) { ?>
                <h3>SMTP <?php echo (int) $i; ?> <?= empty($data['host_' . $i]) ? '<span style="color: red">✖</span>' : '<span style="color: green">✔</span>' ?></h3>
                <div>
                    <table class="form-table">

                        <tbody>
                            <tr>
                                <th>
                                    Host
                                </th>
                                <td>
                                    Host<br>
                                    <input type="text" name="data[host_<?php echo (int) $i; ?>]" size="40" value="<?= esc_attr($data['host_' . $i] ?? ''); ?>">
                                </td>
                                <td>
                                    Delay<br>
                                    <input type="number" name="data[delay_<?php echo (int) $i; ?>]" size="5" value="<?= esc_attr($data['delay_' . $i] ?? ''); ?>"> ms
                                </td>

                            </tr>
                            <tr>
                                <th>
                                    Port and Protocol
                                </th>
                                <td>Port<br>
                                    <input type="text" name="data[port_<?php echo (int) $i; ?>]" size="5" value="<?= esc_attr($data['port_' . $i] ?? ''); ?>"> (25, 465, 587, ...)
                                </td>
                                <td>
                                    Protocol<br>
                                    <select name="data[protocol_<?php echo (int) $i; ?>]">
                                        <option value="">None</option>
                                        <option value="ssl" <?php echo ($data['protocol_' . $i] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="tls" <?php echo ($data['protocol_' . $i] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th>
                                    Credentials
                                </th>
                                <td>
                                    Username<br>
                                    <input type="text" name="data[username_<?php echo (int) $i; ?>]" size="40" value="<?= esc_attr($data['username_' . $i] ?? ''); ?>">
                                </td>
                                <td>
                                    Password<br>
                                    <input type="text" name="data[password_<?php echo (int) $i; ?>]" size="40" value="<?= esc_attr($data['password_' . $i] ?? ''); ?>">
                                </td>

                            </tr>


                            </tr>
                            <tr>
                                <th>
                                    Send a test
                                </th>
                                <td>
                                    <button name="test_<?php echo (int) $i; ?>" class="button button-secondary">Send</button>
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>

            <?php } ?>

        </div>

        <p>
            <button name="save" class="button button-primary">Save</button>
            <button name="burst" class="button button-secondary">Send a few emails</button>
        </p>

    </form>
    <?php if (WP_DEBUG) { ?>

        <h3>Debug</h3>
        <p>
            That helps me when supporting you...
        </p>
        <pre><?= esc_html(print_r(get_option('multismtp_settings'), true)); ?></pre>
        <pre><?= esc_html(print_r(get_option('multismtp_last'), true)); ?></pre>
    <?php } ?>
</div>
