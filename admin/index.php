<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
if (!setup_is_locked()) redirect('setup.php');
require_staff('../account/login.php');
$users = read_json_array(USERS_FILE);
$resources = read_json_array(RESOURCES_FILE);
$contacts = read_json_array(CONTACTS_FILE);
$orders = read_json_array(ORDERS_FILE);
$newContacts = count(array_filter($contacts, static fn(array $item): bool => ($item['status'] ?? 'new') === 'new'));
admin_header('Staff Dashboard', 'overview');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Staff administration</p><h1>Orgasmaphoria operations.</h1><p>Manage the member platform through permission-controlled, server-side tools.</p></div></section>
<section class="section"><div class="wrap">
  <div class="dashboard-stat-grid"><article class="dashboard-stat"><span>Accounts</span><strong><?= count($users) ?></strong><?php if (has_permission('manage_accounts')): ?><a href="users.php">Manage accounts →</a><?php endif; ?></article><article class="dashboard-stat"><span>Resources</span><strong><?= count($resources) ?></strong><?php if (has_permission('manage_content')): ?><a href="resources.php">Manage library →</a><?php endif; ?></article><article class="dashboard-stat"><span>New contact messages</span><strong><?= $newContacts ?></strong><?php if (has_permission('view_contacts')): ?><a href="contacts.php">Review messages →</a><?php endif; ?></article><article class="dashboard-stat"><span>Orders</span><strong><?= count($orders) ?></strong><?php if (has_permission('manage_orders')): ?><a href="orders.php">View orders →</a><?php endif; ?></article></div>
  <div class="admin-action-grid">
    <?php if (has_permission('manage_accounts')): ?><a class="admin-action-card" href="users.php"><span>01</span><div><h2>Accounts & permissions</h2><p>Assign memberships, roles, granular permissions, status, and optional 2FA resets.</p></div></a><?php endif; ?>
    <?php if (has_permission('manage_content')): ?><a class="admin-action-card" href="resources.php"><span>02</span><div><h2>Private resource library</h2><p>Upload books, games, guides, activities, invitations, and internal staff files.</p></div></a><?php endif; ?>
    <?php if (has_permission('view_contacts')): ?><a class="admin-action-card" href="contacts.php"><span>03</span><div><h2>Contact inbox</h2><p>Review messages received through the secured public contact form.</p></div></a><?php endif; ?>
    <?php if (has_permission('view_audit')): ?><a class="admin-action-card" href="security.php"><span>04</span><div><h2>Security audit</h2><p>Review account, permission, resource, authentication, and administrative activity.</p></div></a><?php endif; ?>
  </div>
</div></section>
<?php admin_footer(); ?>
