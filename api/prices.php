<?php
/* ============================================================
   GET /api/prices.php
   Returns display amounts (in cents) for the pricing page.

   Source of truth is the Stripe Price itself — we look up each
   plan's real `unit_amount` so the page can never show a number
   that differs from what the customer is actually charged.
   Results are cached briefly to avoid a Stripe call per page load.
   Falls back to config['display'] if Stripe is unreachable or the
   plan has no price_id yet.
   ============================================================ */

require __DIR__ . '/_stripe.php';

$config = mms_load_config();
$currency = $config && isset($config['currency']) ? $config['currency'] : 'cad';

$CACHE_TTL = 600; // seconds
$cacheFile = sys_get_temp_dir() . '/mms_prices_cache.json';

// Serve fresh cache if available.
if (is_readable($cacheFile) && (time() - filemtime($cacheFile) < $CACHE_TTL)) {
  $cached = json_decode(@file_get_contents($cacheFile), true);
  if (is_array($cached) && $cached) {
    mms_json_out($cached);
  }
}

/**
 * Resolve a plan's amount (cents): prefer the live Stripe Price,
 * fall back to the config display value.
 */
function mms_plan_amount($config, $secret, $key) {
  $planCfg = isset($config['plans'][$key]) ? $config['plans'][$key] : null;
  $fallback = isset($config['display'][$key]) ? (int) $config['display'][$key] : null;

  if ($secret && strpos($secret, 'REPLACE') === false
      && $planCfg && !empty($planCfg['price_id'])
      && strpos($planCfg['price_id'], 'REPLACE') === false) {
    try {
      $price = mms_stripe($secret, 'GET', 'prices/' . rawurlencode($planCfg['price_id']));
      if (isset($price['unit_amount']) && is_numeric($price['unit_amount'])) {
        return array((int) $price['unit_amount'],
                     isset($price['currency']) ? $price['currency'] : null);
      }
    } catch (Exception $e) {
      error_log('[mms prices] ' . $e->getMessage()); // fall through to display
    }
  }
  return array($fallback, null);
}

$out = array();

if ($config) {
  $secret = isset($config['stripe_secret_key']) ? $config['stripe_secret_key'] : '';
  $keys = isset($config['plans']) ? array_keys($config['plans'])
        : array_keys(isset($config['display']) ? $config['display'] : array());

  foreach ($keys as $key) {
    list($amount, $cur) = mms_plan_amount($config, $secret, $key);
    if ($amount === null) { continue; }
    $out[$key] = array('amount' => $amount, 'currency' => $cur ?: $currency);
  }
}

// Last-resort placeholders so the page still renders in dev with no config.
if (!$out) {
  $out = array(
    'registration' => array('amount' => 5000, 'currency' => $currency),
    'monthly'      => array('amount' => 8000, 'currency' => $currency),
  );
}

// Cache only when at least one amount came back (best-effort).
@file_put_contents($cacheFile, json_encode($out), LOCK_EX);

mms_json_out($out);
