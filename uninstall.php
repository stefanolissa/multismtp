<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('multismtp_settings');
delete_option('multismtp_version');
delete_option('multismtp_update_data');
delete_option('multismtp_last');
delete_option('multismtp_next');
