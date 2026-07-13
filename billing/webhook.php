<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
$secret = stripe_webhook_secret();
if ($secret === '') { http_response_code(503); echo '{"error":"Webhook is not configured."}'; exit; }

$payload = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$parts = [];
foreach (explode(',', $signature) as $part) {
    [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
    $parts[$key][] = $value;
}
$timestamp = isset($parts['t'][0]) ? (int)$parts['t'][0] : 0;
$valid = false;
if ($timestamp > 0 && abs(time() - $timestamp) <= 300) {
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($parts['v1'] ?? [] as $candidate) {
        if (is_string($candidate) && hash_equals($expected, $candidate)) { $valid = true; break; }
    }
}
if (!$valid) { http_response_code(400); echo '{"error":"Invalid signature."}'; exit; }

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['type']) || !isset($event['data']['object'])) { http_response_code(400); echo '{"error":"Invalid event."}'; exit; }
$type = (string)$event['type'];
$object = $event['data']['object'];

if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
    $orderId = (string)($object['metadata']['order_id'] ?? $object['client_reference_id'] ?? '');
    $userId = (string)($object['metadata']['user_id'] ?? '');
    $membershipTier = (string)($object['metadata']['membership_tier'] ?? '');
    $orderRecord = null;
    update_json_array(ORDERS_FILE, static function (array $orders) use ($orderId, $object, &$orderRecord): array {
        foreach ($orders as $i => $order) {
            if (($order['id'] ?? '') !== $orderId) continue;
            if (($order['status'] ?? '') === 'paid') { $orderRecord = $order; break; }
            $orders[$i]['status'] = 'paid';
            $orders[$i]['paidAt'] = date(DATE_ATOM);
            $orders[$i]['updatedAt'] = date(DATE_ATOM);
            $orders[$i]['stripeSessionId'] = (string)($object['id'] ?? ($order['stripeSessionId'] ?? ''));
            $orders[$i]['stripeCustomerId'] = (string)($object['customer'] ?? '');
            $orders[$i]['stripeSubscriptionId'] = (string)($object['subscription'] ?? '');
            $orders[$i]['paymentStatus'] = (string)($object['payment_status'] ?? 'paid');
            $orderRecord = $orders[$i];
            break;
        }
        return $orders;
    });
    if ($orderRecord && $userId !== '') {
        update_user_record($userId, static function (array $user) use ($orderRecord, $membershipTier, $object): array {
            $entitlements = is_array($user['entitlements'] ?? null) ? $user['entitlements'] : [];
            foreach ((array)($orderRecord['items'] ?? []) as $item) {
                if (($item['kind'] ?? '') === 'product' && !in_array((string)($item['slug'] ?? ''), $entitlements, true)) $entitlements[] = (string)$item['slug'];
            }
            $user['entitlements'] = array_values(array_filter(array_unique($entitlements)));
            if ($membershipTier !== '' && isset(membership_levels()[$membershipTier])) {
                $user['membershipTier'] = $membershipTier;
                $user['membershipStatus'] = 'active';
                $user['stripeSubscriptionId'] = (string)($object['subscription'] ?? '');
            }
            $user['stripeCustomerId'] = (string)($object['customer'] ?? ($user['stripeCustomerId'] ?? ''));
            $user['updatedAt'] = date(DATE_ATOM);
            return $user;
        });
        audit_event('checkout-paid', $orderId, ['userId' => $userId, 'membershipTier' => $membershipTier]);
    }
} elseif ($type === 'customer.subscription.deleted') {
    $subscriptionId = (string)($object['id'] ?? '');
    if ($subscriptionId !== '') {
        $users = read_json_array(USERS_FILE);
        foreach ($users as $candidate) {
            if (($candidate['stripeSubscriptionId'] ?? '') !== $subscriptionId) continue;
            update_user_record((string)$candidate['id'], static function (array $user): array {
                $user['membershipTier'] = 'listener';
                $user['membershipStatus'] = 'canceled';
                $user['stripeSubscriptionId'] = '';
                $user['updatedAt'] = date(DATE_ATOM);
                return $user;
            });
            audit_event('membership-canceled', (string)$candidate['id']);
            break;
        }
    }
}

echo '{"received":true}';
