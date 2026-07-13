<?php
declare(strict_types=1);
require_once __DIR__ . '/../account/_layout.php';

function admin_header(string $title, string $page = ''): void
{
    account_header($title, 'admin', true);
    ?>
<nav class="member-subnav admin-subnav" aria-label="Staff administration navigation"><div class="wrap member-subnav__inner">
  <a href="index.php"<?= $page === 'overview' ? ' aria-current="page"' : '' ?>>Overview</a>
  <?php if (has_permission('manage_accounts')): ?><a href="users.php"<?= $page === 'users' ? ' aria-current="page"' : '' ?>>Accounts</a><?php endif; ?>
  <?php if (has_permission('manage_content')): ?><a href="resources.php"<?= $page === 'resources' ? ' aria-current="page"' : '' ?>>Resources</a><?php endif; ?>
  <?php if (has_permission('view_contacts')): ?><a href="contacts.php"<?= $page === 'contacts' ? ' aria-current="page"' : '' ?>>Contact</a><?php endif; ?>
  <?php if (has_permission('manage_orders')): ?><a href="orders.php"<?= $page === 'orders' ? ' aria-current="page"' : '' ?>>Orders</a><?php endif; ?>
  <?php if (has_permission('view_audit')): ?><a href="security.php"<?= $page === 'security' ? ' aria-current="page"' : '' ?>>Security log</a><?php endif; ?>
  <a href="../account/index.php">Member view</a>
</div></nav>
<?php
}

function admin_footer(): void
{
    account_footer();
}
