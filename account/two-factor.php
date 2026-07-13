<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$enabled = two_factor_is_enabled($stored);
$replace = isset($_GET['replace']);
$error = '';
$success = '';
$setup = (!$enabled || $replace) ? two_factor_setup_state($stored, $replace) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');
    if (!password_verify($currentPassword, (string)($stored['passwordHash'] ?? ''))) {
        $error = 'The current password was not accepted.';
    } elseif ($action === 'enroll') {
        $setup = two_factor_setup_state($stored, !empty($_POST['replace']));
        $secret = (string)($setup['secret'] ?? '');
        $counter = two_factor_matching_counter($secret, (string)($_POST['code'] ?? ''));
        if ($counter === null) {
            $error = 'The authenticator code was not accepted. Wait for a new six-digit code and try again.';
        } else {
            try {
                $result = two_factor_enroll_user($stored, $secret, $counter);
                sign_in_user($result['user']);
                two_factor_mark_session_verified($result['user']);
                $_SESSION['new_two_factor_recovery_codes'] = $result['codes'];
                redirect('two-factor-recovery.php');
            } catch (Throwable $exception) {
                $error = $exception->getMessage() ?: 'Two-factor authentication could not be enabled.';
            }
        }
    } elseif (in_array($action, ['disable', 'regenerate'], true)) {
        try {
            $verified = two_factor_verify_user_code($stored, (string)($_POST['existing_code'] ?? ''));
            if (!$verified['ok']) {
                $error = 'The authenticator or recovery code was not accepted.';
            } elseif ($action === 'disable') {
                $saved = two_factor_disable_user($verified['user']);
                sign_in_user($saved);
                two_factor_clear_session_state();
                set_flash('success', 'Two-factor authentication was disabled.');
                redirect('security.php');
            } else {
                $result = two_factor_replace_recovery_codes($verified['user']);
                sign_in_user($result['user']);
                two_factor_mark_session_verified($result['user']);
                $_SESSION['new_two_factor_recovery_codes'] = $result['codes'];
                redirect('two-factor-recovery.php');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'The security change could not be completed.';
        }
    }
}

$enabled = two_factor_is_enabled($stored);
$recoveryCount = count(is_array($stored['twoFactorRecoveryCodes'] ?? null) ? $stored['twoFactorRecoveryCodes'] : []);
account_header('Two-Factor Authentication', 'security');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Optional account protection</p><h1>Two-factor authentication.</h1><p>Add an authenticator-app code after your password, with one-time recovery codes as a backup.</p></div></section>
<section class="section"><div class="wrap">
  <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>

  <?php if (!$enabled || $replace): ?>
  <?php $secret = (string)($setup['secret'] ?? ''); $uri = two_factor_otpauth_uri($stored, $secret); ?>
  <div class="two-factor-setup-grid">
    <section class="form-card two-factor-instructions"><p class="eyebrow"><?= $replace ? 'Replace authenticator' : 'Set up authenticator' ?></p><h2>Connect your app</h2><ol class="numbered-steps"><li>Open a TOTP authenticator app on your phone.</li><li>Scan the QR code or enter the setup key manually.</li><li>Enter the current six-digit code to confirm setup.</li><li>Save the recovery codes shown after confirmation.</li></ol><div class="manual-secret"><span>Manual setup key</span><code><?= e($secret) ?></code></div></section>
    <section class="form-card qr-card"><div class="totp-qr" data-totp-qr="<?= e($uri) ?>" aria-label="Authenticator setup QR code"></div><p>Account: <strong><?= e($stored['email'] ?? $stored['username'] ?? 'Member') ?></strong></p></section>
    <form method="post" class="form-card account-form two-factor-confirm-card">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="enroll"><input type="hidden" name="replace" value="<?= $replace ? '1' : '0' ?>">
      <p class="eyebrow">Confirm setup</p><h2>Verify the new code</h2>
      <label><span>Current website password</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      <label><span>Six-digit authenticator code</span><input class="two-factor-code-input" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required placeholder="123456"></label>
      <button class="button button--primary" type="submit"><?= $replace ? 'Replace authenticator' : 'Enable two-factor authentication' ?></button>
      <?php if ($replace): ?><a class="text-link" href="two-factor.php">Cancel replacement</a><?php endif; ?>
    </form>
  </div>
  <script src="../assets/js/qrcode.min.js"></script>
  <?php else: ?>
  <div class="security-grid">
    <section class="form-card security-primary-card"><div class="panel-heading"><div><p class="eyebrow">Status</p><h2>Authenticator protection is enabled</h2></div><span class="security-badge is-on">Enabled</span></div><p>Your password and a second factor are required whenever a new sign-in begins.</p><dl class="security-detail-list"><div><dt>Enabled</dt><dd><?= e(date('F j, Y', strtotime((string)($stored['twoFactorEnabledAt'] ?? 'now')))) ?></dd></div><div><dt>Recovery codes remaining</dt><dd><?= $recoveryCount ?></dd></div></dl><a class="button button--ghost" href="two-factor.php?replace=1">Replace authenticator</a></section>

    <form method="post" class="form-card account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="regenerate"><p class="eyebrow">Recovery</p><h2>Create new recovery codes</h2><p>Generating a new set immediately invalidates every previous recovery code.</p><label><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label><label><span>Authenticator or recovery code</span><input type="text" name="existing_code" required autocomplete="one-time-code" maxlength="24"></label><button class="button button--ghost" type="submit">Generate new recovery codes</button></form>

    <form method="post" class="form-card account-form form-card--danger"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="disable"><p class="eyebrow">Disable</p><h2>Turn off two-factor authentication</h2><p>Your account will return to password-only sign-in.</p><label><span>Current password</span><input type="password" name="current_password" required autocomplete="current-password"></label><label><span>Authenticator or recovery code</span><input type="text" name="existing_code" required autocomplete="one-time-code" maxlength="24"></label><button class="button button--ghost" type="submit" data-confirm="Turn off two-factor authentication for this account?">Disable two-factor authentication</button></form>
  </div>
  <?php endif; ?>
</div></section>
<?php account_footer(); ?>
