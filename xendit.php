<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Xendit Payment Gateway for Perfex CRM
Description: Terima pembayaran invoice Perfex CRM melalui Xendit Invoice (VA, e-wallet, QRIS, kartu, retail outlet, dll).
Version: 1.0.0
Author: ajinetwork
Author URI: https://github.com/ajinetwork
Requires at least: 2.3.*
*/

define('XENDIT_MODULE_NAME', 'xendit');

register_payment_gateway('xendit_gateway', XENDIT_MODULE_NAME);

register_language_files(XENDIT_MODULE_NAME, [XENDIT_MODULE_NAME]);

/**
 * Callback Xendit adalah POST JSON tanpa token CSRF.
 * Jika versi Perfex Anda tidak membaca filter ini, tambahkan 'xendit/webhook'
 * ke $config['csrf_exclude_uris'] di application/config/config.php.
 */
hooks()->add_filter('csrf_exclude_uris', 'xendit_csrf_exclude');
function xendit_csrf_exclude($uris)
{
    $uris[] = 'xendit/webhook';

    return $uris;
}

/**
 * Info URL callback di halaman pengaturan gateway
 */
hooks()->add_action('before_render_payment_gateway_settings', 'xendit_settings_notice');
function xendit_settings_notice($gateway)
{
    if (isset($gateway['id']) && $gateway['id'] == 'xendit') {
        echo '<div class="alert alert-info">';
        echo '<p class="bold">' . _l('xendit_notice_title') . '</p>';
        echo '<p>' . _l('xendit_notice_webhook') . '</p>';
        echo '<p><code>' . site_url('xendit/webhook') . '</code></p>';
        echo '<p>' . _l('xendit_notice_mode') . '</p>';
        echo '</div>';
    }
}
