<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Xendit_gateway extends App_gateway
{
    protected $api_url = 'https://api.xendit.co';

    public function __construct()
    {
        parent::__construct();

        $this->setId('xendit');
        $this->setName('Xendit');

        $this->setSettings([
            [
                'name'      => 'xendit_secret_key',
                'encrypted' => true,
                'label'     => 'Secret API Key (xnd_development_... / xnd_production_...)',
            ],
            [
                'name'      => 'xendit_callback_token',
                'encrypted' => true,
                'label'     => 'Callback Verification Token',
            ],
            [
                'name'          => 'xendit_expiry_minutes',
                'label'         => 'Masa berlaku invoice (menit)',
                'default_value' => '1440',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'IDR',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Pembayaran Invoice {invoice_number}',
            ],
        ]);
    }

    /**
     * REQUIRED - dipanggil Perfex saat klien menekan "Pay Now".
     */
    public function process_payment($data)
    {
        $this->ci->session->set_userdata([
            'xendit_total' => number_format($data['amount'], 2, '.', ''),
        ]);

        redirect(site_url('xendit/make_payment?invoiceid=' . $data['invoiceid'] . '&hash=' . $data['invoice']->hash));
    }

    public function secret_key()
    {
        return trim((string) $this->decryptSetting('xendit_secret_key'));
    }

    public function callback_token()
    {
        return trim((string) $this->decryptSetting('xendit_callback_token'));
    }

    /**
     * Xendit menentukan test/live dari kunci API, bukan dari URL.
     */
    public function is_test_key()
    {
        return strpos($this->secret_key(), 'xnd_development_') === 0;
    }

    public function expiry_seconds()
    {
        $m = (int) $this->getSetting('xendit_expiry_minutes');

        return ($m > 0 ? $m : 1440) * 60;
    }

    /**
     * external_id unik per percobaan bayar. Format: PCRM-{invoiceId}-{timestamp}
     */
    public function gen_external_id($invoice_id)
    {
        return 'PCRM-' . (int) $invoice_id . '-' . time();
    }

    public function invoice_id_from_external($external_id)
    {
        if (preg_match('/^PCRM-(\d+)-\d+$/', (string) $external_id, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Buat Xendit Invoice.
     * @return array ['ok' => bool, 'invoice_url' => string, 'id' => string, 'error' => string]
     */
    public function create_invoice(array $payload)
    {
        $res = $this->request('POST', '/v2/invoices', $payload);

        if (! $res['ok'] || empty($res['body']['invoice_url'])) {
            $msg = $res['error'];
            if (isset($res['body']['message'])) {
                $msg = $res['body']['message'];
            }

            return ['ok' => false, 'error' => $msg ?: 'Gagal membuat invoice Xendit'];
        }

        return [
            'ok'          => true,
            'id'          => $res['body']['id'],
            'invoice_url' => $res['body']['invoice_url'],
        ];
    }

    /**
     * Ambil invoice Xendit berdasarkan ID Xendit.
     * @return array|null
     */
    public function get_invoice($xendit_invoice_id)
    {
        $res = $this->request('GET', '/v2/invoices/' . rawurlencode($xendit_invoice_id));

        if (! $res['ok'] || ! is_array($res['body']) || empty($res['body']['id'])) {
            return null;
        }

        return $res['body'];
    }

    /**
     * Ambil invoice Xendit berdasarkan external_id.
     * @return array|null
     */
    public function get_invoice_by_external($external_id)
    {
        $res = $this->request('GET', '/v2/invoices?external_id=' . rawurlencode($external_id));

        if (! $res['ok'] || ! is_array($res['body']) || empty($res['body'][0]['id'])) {
            return null;
        }

        return $res['body'][0];
    }

    /**
     * Validasi header x-callback-token dari Xendit.
     */
    public function valid_callback_token($header_token)
    {
        $expected = $this->callback_token();
        if ($expected === '' || $header_token === null) {
            return false;
        }

        return hash_equals($expected, trim((string) $header_token));
    }

    public function is_paid(array $inv)
    {
        return isset($inv['status']) && in_array($inv['status'], ['PAID', 'SETTLED'], true);
    }

    /**
     * HTTP client (Basic Auth: Secret Key sebagai username, password kosong).
     */
    protected function request($method, $path, $body = null)
    {
        $ch = curl_init($this->api_url . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->secret_key() . ':'),
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            log_activity('Xendit cURL error: ' . $err);

            return ['ok' => false, 'error' => $err, 'body' => null, 'http' => 0];
        }

        $json = json_decode($raw, true);

        if ($code === 401 || $code === 403) {
            $key = $this->secret_key();
            log_activity('Xendit HTTP ' . $code . ' | key: '
                . ($key === '' ? '(KOSONG)' : substr($key, 0, 16) . '... (' . strlen($key) . ' karakter)')
                . ' | pesan: ' . (isset($json['message']) ? $json['message'] : '-'));
        }

        $ok = $code >= 200 && $code < 300;

        return [
            'ok'    => $ok,
            'error' => $ok ? '' : 'HTTP ' . $code,
            'body'  => $json,
            'http'  => $code,
        ];
    }
}
