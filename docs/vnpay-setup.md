# VNPay Setup

The project now loads local payment settings from [config/local.php](C:/xampp/htdocs/web-dat-ve-xem-phim/config/local.php) before bootstrapping services.

Current local defaults:
- `APP_URL=http://localhost/web-dat-ve-xem-phim`
- `VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html`
- `VNPAY_RETURN_URL=http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/return`
- `VNPAY_IPN_URL=http://localhost/web-dat-ve-xem-phim/api/payments/vnpay/ipn`

Credentials are now read from [config/local.php](C:/xampp/htdocs/web-dat-ve-xem-phim/config/local.php). When they change later, update only that file. No application code changes are needed.

Notes:
- `return_url` is the browser-facing callback that the app redirects to the Payment Result page.
- `ipn_url` is the server-to-server callback endpoint that confirms payment state idempotently.
- `localhost` works for browser-return testing on the same machine, but VNPay IPN will not reach it from the internet. Use a public domain or a tunnel such as `ngrok` before testing end-to-end confirmation outside your local machine.
- For production, prefer setting real environment variables at the server level; `config/local.php` is mainly for local/XAMPP development.
