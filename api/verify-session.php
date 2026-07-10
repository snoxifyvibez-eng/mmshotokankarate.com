<?php
/* ============================================================
   GET /api/verify-session.php?session_id=cs_...
   Retrieves the Checkout Session server-side and reports whether
   the payment actually completed. Used by success.html so a
   "thank you" is only shown for a genuinely paid session.
   The Stripe secret key never leaves the server.
   ============================================================ */

require __DIR__ . '/_stripe.php';

$config = mms_load_config();
if (!$config) {
  mms_json_out(array('verified' => false, 'reason' => 'not_configured'), 200);
}

$sid = isset($_GET['session_id']) ? trim($_GET['session_id']) : '';
// Stripe session IDs look like cs_test_a1B2... — allow only safe chars.
if ($sid === '' || !preg_match('/^cs_[A-Za-z0-9_]+$/', $sid)) {
  mms_json_out(array('verified' => false, 'reason' => 'invalid_session'), 200);
}

$secret = isset($config['stripe_secret_key']) ? $config['stripe_secret_key'] : '';
if (!$secret || strpos($secret, 'REPLACE') !== false) {
  mms_json_out(array('verified' => false, 'reason' => 'not_configured'), 200);
}

try {
  $session = mms_stripe($secret, 'GET', 'checkout/sessions/' . rawurlencode($sid));
  $paymentStatus = isset($session['payment_status']) ? $session['payment_status'] : '';
  // 'paid' for one-time; subscriptions in trial may be 'no_payment_required'.
  $ok = in_array($paymentStatus, array('paid', 'no_payment_required'), true);
  mms_json_out(array(
    'verified' => $ok,
    'payment_status' => $paymentStatus,
    'plan' => isset($session['metadata']['plan']) ? $session['metadata']['plan'] : null,
  ), 200);
} catch (Exception $e) {
  error_log('[mms verify-session] ' . $e->getMessage());
  mms_json_out(array('verified' => false, 'reason' => 'lookup_failed'), 200);
}
