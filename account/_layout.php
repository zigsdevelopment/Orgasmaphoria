<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

function account_header(string $title, string $page = '', bool $minimal = false): void
{
    $user = current_user();
    $description = 'Secure Orgasmaphoria member account access.';
    ?>
<!doctype html>
<html lang="en" data-theme="midnight">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= e($description) ?>">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#09070d">
  <meta name="color-scheme" content="dark">
  <title><?= e($title) ?> | Orgasmaphoria</title>
  <link rel="icon" href="../assets/images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script>window.ORG_BASE = '../';</script>
  <script defer src="../assets/js/data.js"></script>
  <script defer src="../assets/js/app.js"></script>
  <script defer src="../assets/js/account-portal.js"></script>
</head>
<body data-page="<?= e($page ?: 'account-portal') ?>" class="account-portal-body">
<a class="skip-link" href="#main">Skip to content</a>
<header data-site-header></header>
<?php if (!$minimal && $user): ?>
<nav class="member-subnav" aria-label="Member account navigation">
  <div class="wrap member-subnav__inner">
    <a href="index.php"<?= $page === 'dashboard' ? ' aria-current="page"' : '' ?>>Overview</a>
    <a href="library.php"<?= $page === 'library' ? ' aria-current="page"' : '' ?>>Library</a>
    <a href="members.php"<?= $page === 'members' ? ' aria-current="page"' : '' ?>>Members</a>
    <a href="messages.php"<?= $page === 'messages' ? ' aria-current="page"' : '' ?>>Messages</a>
    <a href="settings.php"<?= $page === 'settings' ? ' aria-current="page"' : '' ?>>Settings</a>
    <a href="security.php"<?= $page === 'security' ? ' aria-current="page"' : '' ?>>Security</a>
    <?php if (is_staff()): ?><a href="../admin/index.php">Staff</a><?php endif; ?>
  </div>
</nav>
<?php endif; ?>
<main id="main" class="account-main">
<?php
}

function account_footer(): void
{
    ?>
</main>
<footer data-site-footer></footer>
</body>
</html>
<?php
}

function account_alert(?array $flash): void
{
    if (!$flash) return;
    $type = in_array(($flash['type'] ?? ''), ['success', 'error', 'warning'], true) ? $flash['type'] : 'info';
    echo '<div class="account-alert account-alert--' . e($type) . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($flash['message'] ?? '') . '</div>';
}

function account_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials !== '' ? $initials : 'O';
}
