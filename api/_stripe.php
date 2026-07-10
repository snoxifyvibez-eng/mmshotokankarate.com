<?php
/* ============================================================
   Shared Stripe helper — MMS Shotokan
   Talks to the Stripe REST API with plain cURL, so it works on
   shared hosting without Composer / the Stripe PHP SDK.
   ============================================================ */

function mms_load_config() {
  $path = __DIR__ . '/config.php';
  if (!file_exists($path)) {
    return null;
  }
  return require $path;
}

function mms_json_out($data, $status = 200) {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  // Same-origin site; allow the page to call these endpoints.
  echo json_encode($data);
  exit;
}

/**
 * Call the Stripe API.
 * @param string $secret  sk_... key
 * @param string $method  GET|POST
 * @param string $path    e.g. 'checkout/sessions'
 * @param array  $params  form params (nested arrays OK)
 * @return array          decoded JSON response
 * @throws Exception      on transport failure
 */
function mms_stripe($secret, $method, $path, $params = array()) {
  $url = 'https://api.stripe.com/v1/' . $path;
  $ch = curl_init();

  $opts = array(
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => $secret . ':',
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => array('Stripe-Version: 2024-06-20'),
  );

  if (strtoupper($method) === 'POST') {
    $opts[CURLOPT_POST] = true;
    // Stripe expects application/x-www-form-urlencoded with bracket nesting.
    $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
  }

  curl_setopt_array($ch, $opts);
  $raw = curl_exec($ch);
  $errno = curl_errno($ch);
  $err = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($errno) {
    throw new Exception('Network error contacting Stripe: ' . $err);
  }

  $decoded = json_decode($raw, true);
  if ($code >= 400) {
    $msg = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Stripe error (HTTP ' . $code . ')';
    throw new Exception($msg);
  }
  return $decoded;
}
