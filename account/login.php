<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
if (isset($_GET['cancel'])) two_factor_clear_session_state();
if (current_user()) redirect('index.php');

$flash = take_flash();
$loginError = '';
$registerError = '';
$activePanel = (string)($_GET['view'] ?? 'login');
$return = safe_return_path((string)($_GET['return'] ?? ($_SESSION['return_after_login'] ?? '')), 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'login');
    $activePanel = $action === 'register' ? 'register' : 'login';

    if ($action === 'login') {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $remaining = rate_limit_remaining('account-login', $identifier, 5, 25);
        if ($remaining > 0) {
            $loginError = 'Too many unsuccessful attempts. Try again in a few minutes.';
        } else {
            $matched = find_user_by_identifier($identifier);
            if ($matched && password_verify($password, (string)($matched['passwordHash'] ?? ''))) {
                if (!account_is_approved($matched)) {
                    $loginError = account_status($matched) === 'disabled'
                        ? 'This account is disabled. Contact Orgasmaphoria support for help.'
                        : 'This account is not currently available.';
                } else {
                    clear_rate_failures('account-login', $identifier, 5, 25);
                    unset($_SESSION['return_after_login']);
                    if (password_needs_rehash((string)$matched['passwordHash'], PASSWORD_DEFAULT)) {
                        try {
                            $matched = update_user_record((string)$matched['id'], static function (array $user) use ($password): array {
                                $user['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
                                $user['updatedAt'] = date(DATE_ATOM);
                                return $user;
                            });
                        } catch (Throwable) {}
                    }
                    if (two_factor_is_enabled($matched)) {
                        two_factor_begin_challenge($matched, $return);
                        audit_event('password-verified-awaiting-two-factor', (string)$matched['id']);
                        redirect('two-factor-verify.php');
                    }
                    sign_in_user($matched);
                    audit_event('account-sign-in', (string)$matched['id']);
                    redirect($return);
                }
            } else {
                $locked = record_rate_failure('account-login', $identifier, 5, 25, 900);
                $loginError = $locked > 0
                    ? 'Too many unsuccessful attempts. Sign-in is paused for 15 minutes.'
                    : 'The email, username, or password was not recognized.';
            }
        }
    } elseif ($action === 'register') {
        $registerKey = normalize_email((string)($_POST['email'] ?? ''));
        $remaining = rate_limit_remaining('account-register', $registerKey, 4, 12);
        if ($remaining > 0) {
            $registerError = 'Too many account requests were submitted. Try again later.';
        } elseif (!empty($_POST['website'])) {
            $registerError = 'The account request could not be accepted.';
        } else {
            $displayName = trim((string)($_POST['display_name'] ?? ''));
            $username = normalize_username((string)($_POST['username'] ?? ''));
            $email = normalize_email((string)($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            if (empty($_POST['adult']) || empty($_POST['terms'])) {
                $registerError = 'Confirm that you are 18 or older and accept the Terms and Privacy Notice.';
            } elseif ($password !== $confirm) {
                $registerError = 'The passwords do not match.';
            } else {
                try {
                    $created = create_member_account($displayName, $username, $email, $password);
                    clear_rate_failures('account-register', $registerKey, 4, 12);
                    sign_in_user($created);
                    set_flash('success', 'Your account has been created. Welcome to Orgasmaphoria.');
                    redirect($return);
                } catch (Throwable $exception) {
                    record_rate_failure('account-register', $registerKey, 4, 12, 900);
                    $registerError = $exception->getMessage() ?: 'The account could not be created.';
                }
            }
        }
    }
}

account_header('Member Portal', 'auth', true);
?>
<section class="auth-portal">
  <div class="auth-portal__intro">
    <img class="auth-portal__logo" src="../assets/images/logo.webp" width="735" height="760" alt="Orgasmaphoria emblem">
    <div><p class="eyebrow">Member portal</p><h1>Your place inside Orgasmaphoria.</h1><p>Sign in or create a personal account for memberships, purchases, private resources, invitations, profiles, and messages.</p></div>
  </div>
  <?php account_alert($flash); ?>
  <div class="auth-panel-grid">
    <section class="auth-panel auth-panel--login" id="login">
      <div class="auth-panel__head"><span class="auth-step">01</span><div><p class="eyebrow">Welcome back</p><h2>Sign in</h2></div></div>
      <p>Continue to your dashboard, library, messages, and account settings.</p>
      <?php if ($loginError): ?><div class="account-alert account-alert--error" role="alert"><?= e($loginError) ?></div><?php endif; ?>
      <form method="post" class="stack-form account-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="login">
        <label><span>Email or username</span><input type="text" name="identifier" required autocomplete="username" value="<?= e((($_POST['action'] ?? '') === 'login') ? ($_POST['identifier'] ?? '') : '') ?>"></label>
        <label><span>Password</span><span class="password-field"><input type="password" name="password" required autocomplete="current-password" data-password-input><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></span></label>
        <div class="auth-form-row auth-form-row--end"><a class="forgot-link" href="forgot-password.php">Forgot password?</a></div>
        <button class="button button--primary button--wide" type="submit">Sign in securely</button>
      </form>
      <p class="security-note"><span aria-hidden="true">◆</span> Optional authenticator-app verification appears after your password when enabled.</p>
    </section>

    <section class="auth-panel auth-panel--register" id="register">
      <div class="auth-panel__head"><span class="auth-step">02</span><div><p class="eyebrow">New member</p><h2>Create an account</h2></div></div>
      <p>Begin with free Listener access. Paid memberships and purchases attach to this account.</p>
      <?php if ($registerError): ?><div class="account-alert account-alert--error" role="alert"><?= e($registerError) ?></div><?php endif; ?>
      <form method="post" class="stack-form account-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="register">
        <div class="hidden-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid">
          <label><span>Display name</span><input type="text" name="display_name" autocomplete="name" required maxlength="80" value="<?= e((($_POST['action'] ?? '') === 'register') ? ($_POST['display_name'] ?? '') : '') ?>"></label>
          <label><span>Username</span><input type="text" name="username" autocomplete="username" required minlength="3" maxlength="30" pattern="[A-Za-z0-9._-]+" value="<?= e((($_POST['action'] ?? '') === 'register') ? ($_POST['username'] ?? '') : '') ?>"><small>Letters, numbers, dots, dashes, and underscores.</small></label>
          <label class="form-grid__wide"><span>Email</span><input type="email" name="email" autocomplete="email" required maxlength="254" value="<?= e((($_POST['action'] ?? '') === 'register') ? ($_POST['email'] ?? '') : '') ?>"></label>
          <label><span>Password</span><span class="password-field"><input type="password" name="password" autocomplete="new-password" required minlength="12" data-password-input><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></span><small>Use at least 12 characters.</small></label>
          <label><span>Confirm password</span><input type="password" name="confirm_password" autocomplete="new-password" required minlength="12"></label>
        </div>
        <label class="check-row"><input type="checkbox" name="adult" value="1" required><span>I confirm that I am 18 or older.</span></label>
        <label class="check-row"><input type="checkbox" name="terms" value="1" required><span>I agree to the <a href="../terms.html" target="_blank" rel="noopener">Terms</a> and acknowledge the <a href="../privacy.html" target="_blank" rel="noopener">Privacy Notice</a>.</span></label>
        <button class="button button--primary button--wide" type="submit">Create my account</button>
      </form>
    </section>
  </div>
</section>
<?php account_footer(); ?>
