<?php
/* ============================================================
   POST /api/stripe-webhook.php
   Receives Stripe events (payment succeeded, subscription
   created/cancelled, etc.), verifies the signature, and
   emails the dojo a notification.

   Configure at: https://dashboard.stripe.com/webhooks
     Endpoint URL : https://mmshotokankarate.com/api/stripe-webhook.php
     Events       : checkout.session.completed
                    customer.subscription.deleted
   Copy the signing secret (whsec_...) into config.php.
   ============================================================ */

require __DIR__ . '/_stripe.php';

$config = mms_load_config();
if (!$config) { http_response_code(500); echo 'not configured'; exit; }

$payload = file_get_contents('php://input');
$sigHeader = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$secret = isset($config['stripe_webhook_secret']) ? $config['stripe_webhook_secret'] : '';

/**
 * Verify Stripe's signature (t=timestamp,v1=signature).
 * Avoids needing the Stripe SDK.
 */
function mms_verify_stripe_sig($payload, $header, $secret, $tolerance = 300) {
  if (!$secret || strpos($secret, 'REPLACE') !== false) return false;
  $t = null; $sigs = array();
  foreach (explode(',', $header) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) !== 2) continue;
    if ($kv[0] === 't') $t = $kv[1];
    if ($kv[0] === 'v1') $sigs[] = $kv[1];
  }
  if ($t === null || !$sigs) return false;
  $signedPayload = $t . '.' . $payload;
  $expected = hash_hmac('sha256', $signedPayload, $secret);
  $match = false;
  foreach ($sigs as $s) {
    if (hash_equals($expected, $s)) { $match = true; break; }
  }
  if (!$match) return false;
  // Optional replay protection.
  if (abs(time() - (int) $t) > $tolerance) return false;
  return true;
}

if (!mms_verify_stripe_sig($payload, $sigHeader, $secret)) {
  http_response_code(400);
  echo 'signature verification failed';
  exit;
}

$event = json_decode($payload, true);
$type = isset($event['type']) ? $event['type'] : '';
$obj = isset($event['data']['object']) ? $event['data']['object'] : array();

/**
 * Idempotency: Stripe may deliver the same event more than once (retries).
 * Record processed event IDs so we notify the dojo only once per event.
 * Best-effort file store; capped so it can't grow without bound.
 */
function mms_event_already_processed($eventId) {
  if (!$eventId) { return false; }
  $store = sys_get_temp_dir() . '/mms_stripe_events.log';
  $fh = @fopen($store, 'c+');
  if (!$fh) { return false; } // can't dedupe → fail open (deliver)
  $seen = false;
  if (flock($fh, LOCK_EX)) {
    $contents = stream_get_contents($fh);
    $ids = $contents !== '' ? explode("\n", trim($contents)) : array();
    if (in_array($eventId, $ids, true)) {
      $seen = true;
    } else {
      $ids[] = $eventId;
      if (count($ids) > 500) { $ids = array_slice($ids, -500); } // cap
      ftruncate($fh, 0);
      rewind($fh);
      fwrite($fh, implode("\n", $ids) . "\n");
      fflush($fh);
    }
    flock($fh, LOCK_UN);
  }
  fclose($fh);
  return $seen;
}

if (mms_event_already_processed(isset($event['id']) ? $event['id'] : '')) {
  http_response_code(200);
  echo 'ok (duplicate ignored)';
  exit;
}

$notifyTo = 'mms.shotokan.karate@gmail.com';
$subject = null;
$lines = array();

if ($type === 'checkout.session.completed') {
  $plan = isset($obj['metadata']['plan']) ? $obj['metadata']['plan'] : 'unknown';
  $email = isset($obj['customer_details']['email']) ? $obj['customer_details']['email'] : 'n/a';
  $name = isset($obj['customer_details']['name']) ? $obj['customer_details']['name'] : 'n/a';
  $amount = isset($obj['amount_total']) ? number_format($obj['amount_total'] / 100, 2) : '?';
  $currency = isset($obj['currency']) ? strtoupper($obj['currency']) : '';
  $subject = 'New payment: ' . $plan . ' — MMS Shotokan';
  $lines = array(
    'A payment was completed.',
    'Plan: ' . $plan,
    'Name: ' . $name,
    'Email: ' . $email,
    'Amount: ' . $amount . ' ' . $currency,
  );
} elseif ($type === 'customer.subscription.deleted') {
  $subject = 'Membership cancelled — MMS Shotokan';
  $lines = array('A monthly membership was cancelled.', 'Subscription: ' . (isset($obj['id']) ? $obj['id'] : ''));
}

if ($subject) {
  $headers = 'From: MMS Shotokan Website <no-reply@mmshotokankarate.com>' . "\r\n" .
             'Content-Type: text/plain; charset=utf-8';
  @mail($notifyTo, $subject, implode("\n", $lines), $headers);
}

http_response_code(200);
echo 'ok';
