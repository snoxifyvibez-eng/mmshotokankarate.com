<?php
/* ============================================================
   POST /api/create-checkout.php
   Body (JSON): { "plan": "registration" | "monthly" }
   Returns:     { "url": "https://checkout.stripe.com/..." }
   ============================================================ */

require __DIR__ . '/_stripe.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  mms_json_out(array('error' => 'Method not allowed'), 405);
}

$config = mms_load_config();
if (!$config) {
  mms_json_out(array('error' => 'Payments are not configured yet (missing config.php).'), 500);
}

// Read JSON body (falls back to form-encoded).
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) { $body = $_POST; }
$plan = isset($body['plan']) ? preg_replace('/[^a-z_]/', '', strtolower($body['plan'])) : '';

if (!isset($config['plans'][$plan])) {
  mms_json_out(array('error' => 'Unknown plan.'), 400);
}

$planCfg = $config['plans'][$plan];
$secret = $config['stripe_secret_key'];

if (!$secret || strpos($secret, 'REPLACE') !== false) {
  mms_json_out(array('error' => 'Stripe secret key is not set in config.php.'), 500);
}
if (empty($planCfg['price_id']) || strpos($planCfg['price_id'], 'REPLACE') !== false) {
  mms_json_out(array('error' => 'Stripe Price ID for this plan is not set in config.php.'), 500);
}

$params = array(
  'mode' => $planCfg['mode'],
  'line_items' => array(
    array('price' => $planCfg['price_id'], 'quantity' => 1),
  ),
  'success_url' => $config['success_url'],
  'cancel_url'  => $config['cancel_url'],
  'billing_address_collection' => 'auto',
  'allow_promotion_codes' => 'true',
  'metadata' => array('plan' => $plan),
);

// Collect an email + let Stripe create/reuse a customer for subscriptions.
if ($planCfg['mode'] === 'subscription') {
  $params['subscription_data'] = array('metadata' => array('plan' => $plan));
}

try {
  $session = mms_stripe($secret, 'POST', 'checkout/sessions', $params);
  if (empty($session['url'])) {
    throw new Exception('Stripe did not return a checkout URL.');
  }
  mms_json_out(array('url' => $session['url'], 'id' => $session['id']));
} catch (Exception $e) {
  // Log the real detail server-side; return a generic message to the browser
  // so we don't leak internal identifiers (price IDs, config hints).
  error_log('[mms create-checkout] ' . $e->getMessage());
  mms_json_out(array('error' => 'Could not start checkout right now. Please try again, or contact the dojo.'), 502);
}
