<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('orders.php');
verify_csrf();
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$customerId = trim((string)($stored['stripeCustomerId'] ?? ''));
$secret = stripe_secret_key();
if ($customerId === '' || $secret === '' || !function_exists('curl_init')) {
    set_flash('error', 'Online billing management is not available for this account. Contact support for help.');
    redirect('orders.php');
}
$siteUrl = rtrim((string)(getenv('ORG_SITE_URL') ?: ''), '/');
if ($siteUrl === '') {
    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
    $siteUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = trim(SITE_BASE_PATH, '/');
    if ($base !== '') $siteUrl .= '/' . $base;
}
$curl = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['customer' => $customerId, 'return_url' => $siteUrl . '/account/orders.php']),
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
]);
$body = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
$result = is_string($body) ? json_decode($body, true) : null;
if ($status < 200 || $status >= 300 || !is_array($result) || empty($result['url'])) {
    audit_event('billing-portal-create-failed', (string)$user['id'], ['status' => $status]);
    set_flash('error', 'Billing settings could not be opened. Try again later or contact support.');
    redirect('orders.php');
}
audit_event('billing-portal-opened', (string)$user['id']);
redirect((string)$result['url']);
