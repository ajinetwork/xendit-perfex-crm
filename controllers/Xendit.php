<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Xendit extends App_Controller
{
    /**
     * Buat Xendit Invoice lalu arahkan klien ke halaman bayar Xendit.
     */
    public function make_payment()
    {
        $invoiceid = $this->input->get('invoiceid');
        $hash      = $this->input->get('hash');

        check_invoice_restrictions($invoiceid, $hash);

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoiceid);

        load_client_language($invoice->clientid);

        $currency = strtoupper($invoice->currency_name);
        if (! in_array($currency, ['IDR', 'PHP', 'THB', 'VND', 'MYR'], true)) {
            set_alert('danger', _l('xendit_currency_unsupported'));
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        // Nominal: dari session (bisa pembayaran parsial), fallback ke sisa tagihan
        $amount = $this->session->userdata('xendit_total');
        if (! $amount) {
            $amount = get_invoice_total_left_to_pay($invoice->id, $invoice->total);
        }
        // IDR, VND tanpa desimal
        $amount = in_array($currency, ['IDR', 'VND'], true) ? (int) round((float) $amount) : round((float) $amount, 2);

        if ($amount <= 0) {
            set_alert('warning', _l('invalid_transaction'));
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        // Email pembayar
        $this->load->model('clients_model');
        $contact = null;
        if (is_client_logged_in()) {
            $contact = $this->clients_model->get_contact(get_contact_user_id());
        } elseif (total_rows(db_prefix() . 'contacts', ['userid' => $invoice->clientid]) == 1) {
            $contact = $this->clients_model->get_contact(get_primary_contact_user_id($invoice->clientid));
        }

        $invoice_number = format_invoice_number($invoice->id);
        $description    = str_replace(
            '{invoice_number}',
            $invoice_number,
            $this->xendit_gateway->getSetting('description_dashboard')
        );

        $external_id = $this->xendit_gateway->gen_external_id($invoice->id);
        $base        = 'invoiceid=' . $invoice->id . '&hash=' . $invoice->hash;

        $payload = [
            'external_id'          => $external_id,
            'amount'               => $amount,
            'currency'             => $currency,
            'description'          => $description,
            'invoice_duration'     => $this->xendit_gateway->expiry_seconds(),
            'success_redirect_url' => site_url('xendit/finish?' . $base . '&external_id=' . $external_id),
            'failure_redirect_url' => site_url('xendit/failure?' . $base),
        ];

        if ($contact && ! empty($contact->email)) {
            $payload['payer_email'] = $contact->email;
        }

        $res = $this->xendit_gateway->create_invoice($payload);

        if (! $res['ok']) {
            log_activity('Xendit: gagal membuat invoice ' . $invoice_number . ' - ' . $res['error']);
            set_alert('danger', _l('xendit_create_failed'));
            redirect(site_url('invoice/' . $invoice->id . '/' . $invoice->hash));
        }

        redirect($res['invoice_url']);
    }

    /**
     * Halaman kembali dari Xendit. Status diverifikasi ke API Xendit, bukan dari URL.
     */
    public function finish()
    {
        $invoiceid   = $this->input->get('invoiceid');
        $hash        = $this->input->get('hash');
        $external_id = $this->input->get('external_id');

        check_invoice_restrictions($invoiceid, $hash);

        if ($external_id && $this->xendit_gateway->invoice_id_from_external($external_id) == (int) $invoiceid) {
            $xinv = $this->xendit_gateway->get_invoice_by_external($external_id);

            if ($xinv) {
                if ($this->xendit_gateway->is_paid($xinv)) {
                    $this->record_payment($xinv);
                    set_alert('success', _l('online_payment_recorded_success'));
                } elseif ($xinv['status'] === 'PENDING') {
                    set_alert('info', _l('xendit_payment_pending'));
                } else {
                    set_alert('warning', _l('xendit_payment_failed') . ' (' . $xinv['status'] . ')');
                }
            } else {
                set_alert('warning', _l('xendit_status_unknown'));
            }
        } else {
            set_alert('warning', _l('xendit_payment_pending'));
        }

        $this->session->unset_userdata('xendit_total');
        redirect(site_url('invoice/' . $invoiceid . '/' . $hash));
    }

    public function failure()
    {
        $invoiceid = $this->input->get('invoiceid');
        $hash      = $this->input->get('hash');

        check_invoice_restrictions($invoiceid, $hash);

        set_alert('warning', _l('invalid_transaction'));
        $this->session->unset_userdata('xendit_total');
        redirect(site_url('invoice/' . $invoiceid . '/' . $hash));
    }

    /**
     * Callback "Invoice paid" / "Invoice expired" dari Xendit.
     * Header x-callback-token divalidasi, lalu status dikonfirmasi ke API.
     */
    public function webhook()
    {
        $token = $this->input->get_request_header('X-Callback-Token', true);

        if (! $this->xendit_gateway->valid_callback_token($token)) {
            log_activity('Xendit webhook: callback token tidak valid');
            $this->output->set_status_header(403)->set_output('invalid token');

            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if (! is_array($body) || empty($body['id'])) {
            $this->output->set_status_header(400)->set_output('bad request');

            return;
        }

        // Callback dari invoice di luar modul ini (mis. aplikasi lain): abaikan dengan 200
        if (! $this->xendit_gateway->invoice_id_from_external(isset($body['external_id']) ? $body['external_id'] : '')) {
            $this->output->set_status_header(200)->set_output('ignored');

            return;
        }

        $xinv = $this->xendit_gateway->get_invoice($body['id']);
        if (! $xinv) {
            $this->output->set_status_header(500)->set_output('status check failed');

            return;
        }

        if ($this->xendit_gateway->is_paid($xinv)) {
            $this->record_payment($xinv);
        } elseif ($xinv['status'] === 'EXPIRED') {
            log_activity('Xendit: invoice ' . $xinv['external_id'] . ' kedaluwarsa');
        }

        $this->output->set_status_header(200)->set_output('OK');
    }

    /**
     * Catat pembayaran ke invoice Perfex, idempotent (aman dipanggil
     * oleh finish dan webhook sekaligus).
     */
    protected function record_payment(array $xinv)
    {
        $invoice_id = $this->xendit_gateway->invoice_id_from_external($xinv['external_id']);
        if (! $invoice_id) {
            return false;
        }

        $txid = $xinv['id'];

        $exists = total_rows(db_prefix() . 'invoicepaymentrecords', [
            'invoiceid'     => $invoice_id,
            'transactionid' => $txid,
        ]);
        if ($exists > 0) {
            return true;
        }

        $paid = isset($xinv['paid_amount']) ? $xinv['paid_amount'] : $xinv['amount'];

        $success = $this->xendit_gateway->addPayment([
            'amount'        => (float) $paid,
            'invoiceid'     => $invoice_id,
            'transactionid' => $txid,
            'paymentmethod' => isset($xinv['payment_method']) ? $xinv['payment_method'] : '',
        ]);

        if (! $success) {
            log_activity('Xendit: gagal mencatat pembayaran ' . $xinv['external_id']);
        }

        return $success;
    }
}
