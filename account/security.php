<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $remaining = rate_limit_remaining('security-change-password', (string)$stored['id'], 5, 20);
        if ($remaining > 0) {
            $error = 'Too many unsuccessful attempts. Try again later.';
        } elseif (!password_verify($currentPassword, (string)($stored['passwordHash'] ?? ''))) {
            record_rate_failure('security-change-password', (string)$stored['id'], 5, 20, 900);
            $error = 'The current password was not accepted.';
        } elseif ($password !== $confirm) {
            $error = 'The new passwords do not match.';
        } elseif (!password_is_strong($password)) {
            $error = 'Use a new password with at least 12 characters that is not commonly used.';
        } else {
            $stored = update_user_record((string)$stored['id'], static function (array $account) use ($password): array {
                $account['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
                $account['securityVersion'] = max(1, (int)($account['securityVersion'] ?? 1)) + 1;
                $account['updatedAt'] = date(DATE_ATOM);
                return $account;
            });
            clear_rate_failures('security-change-password', (string)$stored['id'], 5, 20);
            sign_in_user($stored);
            if (two_factor_is_enabled($stored)) two_factor_mark_session_verified($stored);
            audit_event('password-changed', (string)$stored['id']);
            $success = 'Your password was changed and other signed-in sessions were invalidated.';
        }
    } elseif ($action === 'sign_out_all') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        if (!password_verify($currentPassword, (string)($stored['passwordHash'] ?? ''))) {
            $error = 'The current password was not accepted.';
        } else {
            update_user_record((string)$stored['id'], static function (array $account): array {
                $account['securityVersion'] = max(1, (int)($account['securityVersion'] ?? 1)) + 1;
                $account['updatedAt'] = date(DATE_ATOM);
                return $account;
            });
            audit_event('all-sessions-revoked', (string)$stored['id']);
            sign_out_user();
            set_flash('success', 'All sessions were signed out.');
            redirect('login.php');
        }
    }
}

$recoveryCount = count(is_array($stored['twoFactorRecoveryCodes'] ?? null) ? $stored['twoFactorRecoveryCodes'] : []);
account_header('Security Settings', 'security');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Account protection</p><h1>Security settings.</h1><p>Manage your password, optional two-factor authentication, recovery codes, and active sessions.</p></div></section>
<section class="section"><div class="wrap">
  <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>
  <div class="security-grid">
    <section class="form-card security-primary-card">
      <div class="panel-heading"><div><p class="eyebrow">Two-factor authentication</p><h2><?= two_factor_is_enabled($stored) ? 'Authenticator protection is enabled' : 'Add an optional second step' ?></h2></div><span class="security-badge <?= two_factor_is_enabled($stored) ? 'is-on' : '' ?>"><?= two_factor_is_enabled($stored) ? 'Enabled' : 'Optional' ?></span></div>
      <p><?= two_factor_is_enabled($stored) ? 'After your password, sign-in requires a current authenticator code or one-time recovery code.' : 'Use any TOTP authenticator app to protect your account even if someone learns your password.' ?></p>
      <?php if (two_factor_is_enabled($stored)): ?>
        <dl class="security-detail-list"><div><dt>Enabled</dt><dd><?= e(date('F j, Y', strtotime((string)($stored['twoFactorEnabledAt'] ?? 'now')))) ?></dd></div><div><dt>Unused recovery codes</dt><dd><?= $recoveryCount ?></dd></div><div><dt>Last verified</dt><dd><?= !empty($stored['twoFactorLastUsedAt']) ? e(date('F j, Y, g:i a', strtotime((string)$stored['twoFactorLastUsedAt']))) : 'Not recorded' ?></dd></div></dl>
        <a class="button button--primary" href="two-factor.php">Manage two-factor authentication</a>
      <?php else: ?>
        <ul class="check-list"><li>Works with standard authenticator apps</li><li>Includes eight one-time recovery codes</li><li>Can be disabled later after password and code verification</li></ul>
        <a class="button button--primary" href="two-factor.php">Set up two-factor authentication</a>
      <?php endif; ?>
    </section>

    <form method="post" class="form-card account-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="change_password">
      <p class="eyebrow">Password</p><h2>Change password</h2>
      <label><span>Current password</span><span class="password-field"><input type="password" name="current_password" autocomplete="current-password" required data-password-input><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></span></label>
      <label><span>New password</span><input type="password" name="password" autocomplete="new-password" required minlength="12"><small>Use at least 12 characters and do not reuse another site’s password.</small></label>
      <label><span>Confirm new password</span><input type="password" name="confirm_password" autocomplete="new-password" required minlength="12"></label>
      <button class="button button--primary" type="submit">Change password</button>
    </form>

    <section class="form-card"><p class="eyebrow">Session security</p><h2>Sign out everywhere</h2><p>This invalidates every current browser session, including this one. Your password and two-factor enrollment remain unchanged.</p><form method="post" class="account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="sign_out_all"><label><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label><button class="button button--ghost" type="submit" data-confirm="Sign out every active session?">Sign out all sessions</button></form></section>

    <section class="form-card"><p class="eyebrow">Security practices</p><h2>Protect your account</h2><ul class="check-list"><li>Orgasmaphoria will never ask for your password or authenticator code by message.</li><li>Use a unique password stored in a trusted password manager.</li><li>Keep recovery codes separate from the device running your authenticator app.</li><li>Contact support if you notice activity you do not recognize.</li></ul></section>
  </div>
</div></section>
<?php account_footer(); ?>
