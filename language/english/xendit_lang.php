<?php

$lang['xendit_notice_title']         = 'Xendit Setup';
$lang['xendit_notice_webhook']       = 'Set this URL as the "Invoices paid" and "Invoices expired" callback URL in Xendit Dashboard (Settings > Developers > Webhooks):';
$lang['xendit_notice_mode']          = 'Test or live mode is determined by the API key used (xnd_development_ = test, xnd_production_ = live). Supported invoice currencies: IDR, PHP, THB, VND, MYR.';
$lang['xendit_currency_unsupported'] = 'This invoice currency is not supported by Xendit.';
$lang['xendit_create_failed']        = 'Could not start the Xendit payment. Please try again later.';
$lang['xendit_payment_pending']      = 'Thank you. Your payment is awaiting completion; the invoice will be updated automatically once Xendit confirms it.';
$lang['xendit_payment_failed']       = 'Your payment was not completed.';
$lang['xendit_status_unknown']       = 'We could not verify your payment status yet. The invoice will be updated automatically once confirmed.';
