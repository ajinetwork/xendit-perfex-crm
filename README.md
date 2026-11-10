# Xendit Payment Gateway untuk Perfex CRM

Modul pembayaran Xendit (Invoice API / hosted checkout) untuk invoice Perfex CRM. Mendukung metode
yang aktif di akun Xendit Anda: Virtual Account, e-wallet, QRIS, kartu, retail outlet, dll.

## Instalasi
1. Ekstrak folder `xendit` ke `modules/` Perfex CRM (hasil: `modules/xendit/xendit.php`).
2. **Setup > Modules**, aktifkan *Xendit Payment Gateway*.
3. **Setup > Settings > Payment Gateways > Xendit**: aktifkan, isi **Secret API Key** dan
   **Callback Verification Token**.
4. Di Xendit Dashboard (**Settings > Developers > Webhooks**), isi URL callback
   `https://DOMAIN-ANDA/xendit/webhook`
   untuk event **Invoices paid** dan **Invoices expired**.

## Catatan
- Test/live ditentukan oleh API key: `xnd_development_...` (test) atau `xnd_production_...` (live).
- Secret key harus punya izin Money-in products: Write (agar bisa membuat invoice) dan Read.
- Mata uang: IDR, PHP, THB, VND, MYR.
- Status pembayaran diverifikasi ke API Xendit (bukan parameter URL); callback divalidasi dengan
  header `x-callback-token`. Pencatatan pembayaran idempotent.
- Jika webhook 403 karena CSRF, tambahkan `'xendit/webhook'` ke `$config['csrf_exclude_uris']`.
