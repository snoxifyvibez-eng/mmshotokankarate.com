# MMS Shotokan — Setup Guide

The site is static HTML/CSS with two backend-connected features:

1. **Contact & registration email** — via [Web3Forms](https://web3forms.com) (no server code)
2. **Payments** — via [Stripe Checkout](https://stripe.com) (PHP endpoints in `/api`)

You can deploy the whole folder to any shared PHP host (cPanel, GoDaddy,
Hostinger, etc.). Only the payment part needs PHP; the rest is plain static files.

---

## 1. Contact & registration email (Web3Forms)

Registration form (`index.html`) and contact form (`contact.html`) both submit
through Web3Forms, which emails submissions straight to your inbox.

1. Go to <https://web3forms.com>, enter **mms.shotokan.karate@gmail.com**, and get
   your free **Access Key** (it's emailed to you).
2. Open [`assets/forms.js`](assets/forms.js) and replace:
   ```js
   var WEB3FORMS_ACCESS_KEY = "YOUR_WEB3FORMS_ACCESS_KEY";
   ```
   with your real key.
3. Done. Test by submitting the contact form — the message should arrive in Gmail.

> No key yet? The forms still render but show a friendly "not configured" message
> instead of sending.

---

## 2. Payments (Stripe)

Supports **both**: a one-time **Registration** fee and a recurring **Monthly
Membership**. No Composer required — the PHP talks to Stripe over plain cURL.

### Step A — Create your Stripe products & prices
1. Log in to <https://dashboard.stripe.com>.
2. **Products → Add product**:
   - *Registration* → **One-time** price (e.g. `$50 CAD`) → copy the **Price ID** (`price_…`).
   - *Monthly Membership* → **Recurring / monthly** price (e.g. `$80 CAD`) → copy its **Price ID**.
3. **Developers → API keys** → copy your **Secret key** (`sk_test_…` for testing,
   `sk_live_…` when ready for real payments).

### Step B — Create `api/config.php`
1. Copy [`api/config.example.php`](api/config.example.php) → `api/config.php`.
2. Fill in:
   - `stripe_secret_key`
   - each plan's `price_id`
   - the `display` amounts (in **cents** — these are just what the page *shows*;
     the real charge always comes from the Stripe Price)
   - update `success_url` / `cancel_url` to your live domain.
3. `api/config.php` is **gitignored** — it holds your secret key and must never be
   committed.

### Step C — (Recommended) Webhook for payment notifications
So you get an email whenever someone pays:
1. <https://dashboard.stripe.com/webhooks> → **Add endpoint**
   - URL: `https://mmshotokankarate.com/api/stripe-webhook.php`
   - Events: `checkout.session.completed`, `customer.subscription.deleted`
2. Copy the **Signing secret** (`whsec_…`) into `stripe_webhook_secret` in `config.php`.

### Step D — Test
- Use Stripe **test mode** keys and card `4242 4242 4242 4242`, any future expiry, any CVC.
- Visit `/pricing.html`, click a plan → you should land on Stripe Checkout.
- Success → `success.html`; cancel → `cancel.html`.
- When happy, swap test keys for **live** keys and re-test with a real card.

### Requirements on the host
- PHP 7.4+ with the **cURL** extension (standard on virtually all shared hosts).
- For webhook email notifications, PHP `mail()` must be enabled (or adapt
  `api/stripe-webhook.php` to your host's SMTP).

---

## Managing memberships
Members can cancel/update cards through Stripe's **Customer Portal**
(<https://dashboard.stripe.com/settings/billing/portal> to enable it). You can also
manage everything directly from the Stripe Dashboard.

## Deploying to Hostinger

This site is built for exactly this kind of shared PHP host.

1. **Upload the files** — hPanel → **File Manager** (or FTP). Put everything in
   `public_html/` (or your domain's document root). Keep the `api/` and `assets/`
   folders intact.
2. **Create `api/config.php` on the server** — it's gitignored, so it won't come
   from the repo. In File Manager, copy `api/config.example.php`, rename the copy
   to `config.php`, and fill in your Stripe keys/Price IDs (see Step B above).
3. **PHP version** — hPanel → **PHP Configuration** → use **PHP 8.0+**. The cURL
   and OpenSSL extensions are enabled by default on Hostinger, so Stripe calls work
   out of the box (the CA-bundle warning you'd see running PHP locally does **not**
   happen on Hostinger).
4. **Email** — Web3Forms needs nothing server-side. The Stripe webhook uses PHP
   `mail()`, which works on Hostinger; for best deliverability you can later point it
   at Hostinger's SMTP, but `mail()` is fine to start.
5. **HTTPS** — make sure the free SSL certificate is active (hPanel → **SSL**) so
   `success_url`/`cancel_url` and the Stripe webhook use `https://`.
6. **Update URLs** — set `success_url` and `cancel_url` in `config.php` to your real
   domain (e.g. `https://mmshotokankarate.com/success.html?...`).

> Tip: keep `config.php` out of any public backups you share — it contains your
> Stripe **secret** key.

## File map
```
index.html            Home (redesigned) + registration form
contact.html          Contact page + form
pricing.html          Plans + Stripe checkout buttons
success.html          Post-payment thank-you
cancel.html           Checkout-cancelled page
styles.css            All styling ("Sumi-e Dojo" theme)
assets/forms.js       Web3Forms submission handler
assets/checkout.js    Stripe Checkout launcher
api/
  config.example.php  Template — copy to config.php and fill in
  config.php          YOUR secrets (gitignored, you create this)
  _stripe.php         Shared Stripe cURL helper
  create-checkout.php Creates a Stripe Checkout Session
  prices.php          Serves display prices to the pricing page
  stripe-webhook.php  Receives Stripe events, emails the dojo
```
