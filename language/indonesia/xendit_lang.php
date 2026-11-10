<?php

$lang['xendit_notice_title']         = 'Pengaturan Xendit';
$lang['xendit_notice_webhook']       = 'Isi URL ini sebagai callback "Invoices paid" dan "Invoices expired" di Xendit Dashboard (Settings > Developers > Webhooks):';
$lang['xendit_notice_mode']          = 'Mode test atau live ditentukan oleh API key yang dipakai (xnd_development_ = test, xnd_production_ = live). Mata uang invoice yang didukung: IDR, PHP, THB, VND, MYR.';
$lang['xendit_currency_unsupported'] = 'Mata uang invoice ini tidak didukung oleh Xendit.';
$lang['xendit_create_failed']        = 'Pembayaran Xendit tidak dapat dimulai. Silakan coba lagi nanti.';
$lang['xendit_payment_pending']      = 'Terima kasih. Pembayaran Anda menunggu penyelesaian; invoice akan diperbarui otomatis setelah Xendit mengonfirmasi.';
$lang['xendit_payment_failed']       = 'Pembayaran Anda belum selesai.';
$lang['xendit_status_unknown']       = 'Status pembayaran belum dapat diverifikasi. Invoice akan diperbarui otomatis setelah terkonfirmasi.';
