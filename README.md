# 📡 Nymora Hotspot — Smart Wireless Hotspot Billing & Management

PHP 8 + SQLite. No install wizard: upload, open `admin.php`, log in.

## Install
1. Upload this folder to PHP hosting (PHP 8.0+, pdo_sqlite). `data/` must be writable.
2. Open `/admin.php` — login `admin` / `admin123` (change it under Staff).
3. Branding & Settings: business name, logo, colour, currency, payment instructions.
4. Cron every minute: `* * * * * php /path/to/cron.php` (expires packages, removes users from router, updates usage and router status).

## MikroTik setup
1. `/ip service enable api` (port 8728), create an API user with hotspot rights; add it under Routers & Sites, press Test.
2. Create a hotspot user profile per package speed, e.g. `/ip hotspot user profile add name=2M-5M rate-limit=2M/5M`, and type that name in the package's "Router profile".
3. Upload the portal redirect: in Hotspot server profile, set login page to redirect to
   `http://YOUR-SERVER/portal.php?router=ID` (a tiny `login.html` on the router with a meta-refresh to that URL, passing `mac=$(mac)&ip=$(ip)&link-login-only=$(link-login-only)`).
4. Walled garden: add your server's domain/IP (and your payment provider) so customers can reach the portal before login.
Ubiquiti/TP-Link/others: use the voucher/payment records with their RADIUS or captive-portal API; the router adapter is `router_do()` in `lib.php`.

## Payments
Customer buys → receives a reference → pays by mobile money → your gateway calls `webhook.php?key=…` (key in Settings) → internet activates automatically. Staff can also confirm payments manually (Payments page). Demo mode auto-confirms for testing only.

## Roles
Admin (everything) · Manager (all but staff/settings) · Cashier (vouchers, live customers, customers, payments).

## Notes
Voucher revenue is recorded when a voucher is redeemed. Expiry is wall-clock from activation. Put the site behind HTTPS.
