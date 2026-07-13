<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function checkout_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') checkout_json(['error' => 'Method not allowed.'], 405);
verify_csrf();
$user = current_user();
if (!$user) checkout_json(['error' => 'Sign in before starting checkout.', 'loginRequired' => true], 401);

$secret = stripe_secret_key();
if ($secret === '') checkout_json(['error' => 'Secure checkout has not yet been connected by the site owner.'], 503);
if (!function_exists('curl_init')) checkout_json(['error' => 'The server is missing the secure payment connection required for checkout.'], 503);

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) checkout_json(['error' => 'Invalid checkout request.'], 400);

$catalog = product_catalog();
$kind = (string)($input['kind'] ?? 'products');
$items = [];
$mode = 'payment';
$membershipTier = '';

if ($kind === 'membership') {
    $slug = (string)($input['slug'] ?? '');
    $product = $catalog[$slug] ?? null;
    if (!is_array($product) || ($product['kind'] ?? '') !== 'membership') checkout_json(['error' => 'That membership could not be found.'], 400);
    $mode = 'subscription';
    $membershipTier = (string)($product['tier'] ?? '');
    $storedUser = find_user_by_id((string)$user['id']) ?? $user;
    if (!empty($storedUser['stripeSubscriptionId']) && ($storedUser['membershipStatus'] ?? '') === 'active') {
        checkout_json(['error' => 'This account already has an active paid membership. Manage it from My Account → Orders & Billing.'], 409);
    }
    $items[] = ['slug' => $slug, 'quantity' => 1, 'product' => $product];
} else {
    $requested = $input['items'] ?? [];
    if (!is_array($requested) || count($requested) < 1 || count($requested) > 20) checkout_json(['error' => 'Your shopping bag is empty or too large.'], 400);
    foreach ($requested as $entry) {
        if (!is_array($entry)) continue;
        $slug = (string)($entry['slug'] ?? '');
        $quantity = max(1, min(10, (int)($entry['quantity'] ?? 1)));
        $product = $catalog[$slug] ?? null;
        if (!is_array($product) || ($product['kind'] ?? '') !== 'product') checkout_json(['error' => 'One of the selected products is unavailable.'], 400);
        $items[] = ['slug' => $slug, 'quantity' => $quantity, 'product' => $product];
    }
    if (!$items) checkout_json(['error' => 'Your shopping bag is empty.'], 400);
}

$totalCents = 0;
$orderItems = [];
foreach ($items as $entry) {
    $product = $entry['product'];
    $quantity = (int)$entry['quantity'];
    $totalCents += (int)$product['priceCents'] * $quantity;
    $orderItems[] = [
        'slug' => $entry['slug'],
        'title' => (string)$product['title'],
        'quantity' => $quantity,
        'unitCents' => (int)$product['priceCents'],
        'kind' => (string)$product['kind'],
    ];
}

$order = [
    'id' => make_id('order'),
    'userId' => (string)$user['id'],
    'email' => (string)$user['email'],
    'kind' => $kind === 'membership' ? 'membership' : 'products',
    'membershipTier' => $membershipTier,
    'items' => $orderItems,
    'totalCents' => $totalCents,
    'currency' => 'usd',
    'status' => 'pending',
    'createdAt' => date(DATE_ATOM),
    'updatedAt' => date(DATE_ATOM),
];
update_json_array(ORDERS_FILE, static function (array $orders) use ($order): array {
    $orders[] = $order;
    return $orders;
});

$siteUrl = rtrim((string)(getenv('ORG_SITE_URL') ?: ''), '/');
if ($siteUrl === '') {
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = trim(SITE_BASE_PATH, '/');
    if ($base !== '') $siteUrl .= '/' . $base;
}

$params = [
    'mode' => $mode,
    'success_url' => $siteUrl . '/checkout-success.html?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => $siteUrl . '/checkout-cancel.html',
    'client_reference_id' => (string)$order['id'],
    'customer_email' => (string)$user['email'],
    'allow_promotion_codes' => 'true',
    'billing_address_collection' => 'auto',
    'metadata[order_id]' => (string)$order['id'],
    'metadata[user_id]' => (string)$user['id'],
    'metadata[membership_tier]' => $membershipTier,
];

foreach ($items as $index => $entry) {
    $product = $entry['product'];
    $prefix = "line_items[{$index}]";
    $params["{$prefix}[quantity]"] = (string)$entry['quantity'];
    $params["{$prefix}[price_data][currency]"] = 'usd';
    $params["{$prefix}[price_data][unit_amount]"] = (string)$product['priceCents'];
    $params["{$prefix}[price_data][product_data][name]"] = (string)$product['title'];
    $params["{$prefix}[price_data][product_data][description]"] = (string)$product['description'];
    $params["{$prefix}[price_data][product_data][metadata][slug]"] = (string)$entry['slug'];
    if ($mode === 'subscription') {
        $params["{$prefix}[price_data][recurring][interval]"] = (string)($product['interval'] ?? 'month');
    }
}

$curl = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($params),
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
]);
$responseBody = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);
$response = is_string($responseBody) ? json_decode($responseBody, true) : null;

if ($status < 200 || $status >= 300 || !is_array($response) || empty($response['id']) || empty($response['url'])) {
    update_json_array(ORDERS_FILE, static function (array $orders) use ($order, $response, $curlError): array {
        foreach ($orders as $i => $stored) {
            if (($stored['id'] ?? '') !== $order['id']) continue;
            $orders[$i]['status'] = 'checkout_error';
            $orders[$i]['updatedAt'] = date(DATE_ATOM);
            $orders[$i]['errorCode'] = (string)($response['error']['code'] ?? ($curlError !== '' ? 'network_error' : 'stripe_error'));
            break;
        }
        return $orders;
    });
    audit_event('checkout-create-failed', (string)$order['id'], ['status' => $status]);
    checkout_json(['error' => 'Secure checkout could not be started. Please try again or contact support.'], 502);
}

update_json_array(ORDERS_FILE, static function (array $orders) use ($order, $response): array {
    foreach ($orders as $i => $stored) {
        if (($stored['id'] ?? '') !== $order['id']) continue;
        $orders[$i]['stripeSessionId'] = (string)$response['id'];
        $orders[$i]['status'] = 'checkout_open';
        $orders[$i]['updatedAt'] = date(DATE_ATOM);
        break;
    }
    return $orders;
});
audit_event('checkout-created', (string)$order['id'], ['kind' => $order['kind'], 'totalCents' => $totalCents]);
checkout_json(['url' => (string)$response['url']]);
