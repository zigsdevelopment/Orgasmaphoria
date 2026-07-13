<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_permission('manage_orders');
$orders = read_json_array(ORDERS_FILE);
usort($orders, static fn(array $a, array $b): int => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
admin_header('Orders & Memberships', 'orders');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Commerce administration</p><h1>Orders and subscriptions.</h1><p>Verified payment events create purchase records and update account membership access.</p></div></section>
<section class="section"><div class="wrap"><?php if ($orders): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): $buyer = find_user_by_id((string)($order['userId'] ?? '')); ?><tr><td><?= e(date('M j, Y, g:i a', strtotime((string)($order['createdAt'] ?? 'now')))) ?></td><td><strong><?= e($buyer['displayName'] ?? 'Unknown account') ?></strong><br><small><?= e($buyer['email'] ?? '') ?></small></td><td><?php foreach ((array)($order['items'] ?? []) as $item): ?><div><?= e($item['title'] ?? $item['slug'] ?? 'Item') ?><?= !empty($item['quantity']) && (int)$item['quantity'] > 1 ? ' × ' . (int)$item['quantity'] : '' ?></div><?php endforeach; ?></td><td>$<?= e(number_format(((int)($order['totalCents'] ?? 0)) / 100, 2)) ?></td><td><?= e(ucfirst((string)($order['status'] ?? 'pending'))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No orders have been recorded.</strong></div><?php endif; ?></div></section>
<?php admin_footer(); ?>
