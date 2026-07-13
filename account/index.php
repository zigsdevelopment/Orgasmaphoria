<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$flash = take_flash();
$tierLabels = ['listener' => 'Listener', 'velvet-patron' => 'Velvet Patron', 'inner-circle' => 'Inner Circle'];
$tier = (string)($stored['membershipTier'] ?? 'listener');
$resources = array_values(array_filter(read_json_array(RESOURCES_FILE), static fn(array $resource): bool => ($resource['status'] ?? 'published') === 'published' && user_can_access_resource($stored, $resource)));
$orders = array_values(array_filter(read_json_array(ORDERS_FILE), static fn(array $order): bool => (string)($order['userId'] ?? '') === (string)$stored['id']));
$messages = array_values(array_filter(read_json_array(MESSAGES_FILE), static fn(array $message): bool => (string)($message['recipientId'] ?? '') === (string)$stored['id'] && empty($message['readAt'])));
account_header('Member Overview', 'dashboard');
?>
<section class="member-page-head"><div class="wrap member-page-head__grid"><div><p class="eyebrow">Member overview</p><h1>Welcome, <?= e($stored['displayName'] ?? 'Member') ?>.</h1><p>Your memberships, purchases, resources, messages, privacy, and security settings live here.</p></div><div class="member-identity"><span class="avatar avatar--large"><?= e(account_initials((string)($stored['displayName'] ?? 'Member'))) ?></span><div><strong>@<?= e($stored['username'] ?? 'member') ?></strong><span><?= e($tierLabels[$tier] ?? 'Listener') ?> membership</span></div></div></div></section>
<section class="section"><div class="wrap">
  <?php account_alert($flash); ?>
  <div class="dashboard-stat-grid">
    <article class="dashboard-stat"><span>Membership</span><strong><?= e($tierLabels[$tier] ?? 'Listener') ?></strong><a href="../membership.html">View membership options →</a></article>
    <article class="dashboard-stat"><span>Available resources</span><strong><?= count($resources) ?></strong><a href="library.php">Open your library →</a></article>
    <article class="dashboard-stat"><span>Purchases</span><strong><?= count($orders) ?></strong><a href="orders.php">View order history →</a></article>
    <article class="dashboard-stat"><span>Unread messages</span><strong><?= count($messages) ?></strong><a href="messages.php">Open messages →</a></article>
  </div>

  <div class="dashboard-grid">
    <section class="form-card dashboard-panel"><div class="panel-heading"><div><p class="eyebrow">Recently available</p><h2>Your library</h2></div><a class="text-link" href="library.php">View all</a></div>
      <?php if ($resources): ?><div class="compact-list"><?php foreach (array_slice(array_reverse($resources), 0, 4) as $resource): ?><a class="compact-row" href="library.php#<?= e($resource['id']) ?>"><span class="mini-icon"><?= e(strtoupper(substr((string)($resource['contentType'] ?? 'R'), 0, 1))) ?></span><span><strong><?= e($resource['title'] ?? 'Resource') ?></strong><small><?= e($resource['subtitle'] ?? ($resource['accessLevel'] ?? 'Member access')) ?></small></span><span>→</span></a><?php endforeach; ?></div><?php else: ?><div class="empty-state"><strong>Your library is ready.</strong><p>Published resources connected to your membership or purchases will appear here.</p></div><?php endif; ?>
    </section>

    <section class="form-card dashboard-panel"><div class="panel-heading"><div><p class="eyebrow">Account security</p><h2>Protection status</h2></div><a class="text-link" href="security.php">Manage</a></div>
      <div class="security-status-card <?= two_factor_is_enabled($stored) ? 'is-secure' : '' ?>"><span class="security-status-card__icon" aria-hidden="true"><?= two_factor_is_enabled($stored) ? '✓' : '◇' ?></span><div><strong><?= two_factor_is_enabled($stored) ? 'Two-factor authentication is on' : 'Two-factor authentication is optional' ?></strong><p><?= two_factor_is_enabled($stored) ? 'Sign-ins require your password and an authenticator or recovery code.' : 'Add an authenticator app for an extra verification step at sign-in.' ?></p></div></div>
      <div class="button-row"><a class="button button--ghost" href="security.php"><?= two_factor_is_enabled($stored) ? 'Review security' : 'Set up 2FA' ?></a><a class="button button--ghost" href="settings.php">Profile & privacy</a></div>
    </section>
  </div>

  <?php if (is_staff()): ?><section class="staff-callout"><div><p class="eyebrow">Staff access</p><h2>Administration tools are available.</h2><p>Manage members, permissions, memberships, resources, contact messages, orders, and security records.</p></div><a class="button button--primary" href="../admin/index.php">Open staff dashboard</a></section><?php endif; ?>
</div></section>
<?php account_footer(); ?>
