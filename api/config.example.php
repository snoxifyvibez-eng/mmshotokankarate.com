<?php
/* ============================================================
   MMS Shotokan — payment configuration (TEMPLATE)

   1. Copy this file to  config.php   (same folder).
   2. Fill in your real Stripe keys and Price IDs.
   3. NEVER commit config.php — it is gitignored.

   Get your keys:  https://dashboard.stripe.com/apikeys
   Create Prices:  https://dashboard.stripe.com/products
     - Registration : a ONE-TIME price  (e.g. $50 CAD)  -> price_xxx
     - Membership   : a RECURRING price (monthly, e.g. $80 CAD/mo) -> price_xxx
   ============================================================ */

return array(

  // --- Stripe secret key (server-side only, keep private) ---
  // Test key starts with sk_test_...  Live key starts with sk_live_...
  'stripe_secret_key' => 'sk_test_REPLACE_ME',

  // --- Webhook signing secret (optional but recommended) ---
  // From: https://dashboard.stripe.com/webhooks  ->  whsec_...
  'stripe_webhook_secret' => 'whsec_REPLACE_ME',

  // --- Currency ---
  'currency' => 'cad',

  // --- Plans -> Stripe Price IDs ---
  // Create these in the Stripe Dashboard, then paste the price IDs here.
  'plans' => array(
    'registration' => array(
      'label'    => 'Registration (one-time)',
      'price_id' => 'price_REPLACE_ONE_TIME',
      'mode'     => 'payment',        // one-time
    ),
    'monthly' => array(
      'label'    => 'Monthly Membership',
      'price_id' => 'price_REPLACE_RECURRING',
      'mode'     => 'subscription',   // recurring
    ),
  ),

  // --- Return URLs (absolute, https in production) ---
  // Update the domain to your live site before going live.
  'success_url' => 'https://mmshotokankarate.com/success.html?session_id={CHECKOUT_SESSION_ID}',
  'cancel_url'  => 'https://mmshotokankarate.com/cancel.html',

  // --- Where the "prices.php" endpoint reads display amounts from ---
  // (Purely for showing prices on the page; the real charge always
  //  comes from the Stripe Price above.) Amounts in cents.
  'display' => array(
    'registration' => 5000,   // $50.00
    'monthly'      => 8000,   // $80.00 / month
  ),
);
