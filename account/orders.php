<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$flash = take_flash();
$orders = array_values(array_filter(read_json_array(ORDERS_FILE), static fn(array $order): bool => (string)($order['userId'] ?? '') === (string)$user['id']));
usort($orders, static fn(array $a, array $b): int => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
account_header('Orders & Billing', 'dashboard');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Purchases and billing</p><h1>Order history.</h1><p>Completed digital purchases and membership transactions are connected to your account.</p></div></section>
<section class="section"><div class="wrap">
  <?php account_alert($flash); ?>
  <?php if (!empty($stored['stripeCustomerId'])): ?><div class="billing-actions"><div><p class="eyebrow">Membership billing</p><h2>Manage payment details or cancellation.</h2></div><form method="post" action="billing.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button--ghost" type="submit">Open secure billing portal</button></form></div><?php endif; ?>
  <?php if ($orders): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td><?= e(date('M j, Y', strtotime((string)($order['createdAt'] ?? 'now')))) ?></td><td><?php foreach ((array)($order['items'] ?? []) as $item): ?><div><strong><?= e($item['title'] ?? $item['slug'] ?? 'Item') ?></strong><?= !empty($item['quantity']) && (int)$item['quantity'] > 1 ? ' × ' . (int)$item['quantity'] : '' ?></div><?php endforeach; ?></td><td>$<?= e(number_format(((int)($order['totalCents'] ?? 0)) / 100, 2)) ?></td><td><?= e(ucfirst((string)($order['status'] ?? 'pending'))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No purchases yet.</strong><p>Books, games, guides, memberships, and other releases purchased through the store will appear here.</p><a class="button button--primary" href="../store.html">Browse the store</a></div><?php endif; ?>
</div></section>
<?php account_footer(); ?>
