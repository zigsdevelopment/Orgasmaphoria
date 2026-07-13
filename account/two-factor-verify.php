<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
$challenge = two_factor_pending_challenge();
if (!$challenge) {
    set_flash('error', 'Your verification session expired. Sign in again.');
    redirect('login.php');
}
$pending = $challenge['pending'];
$user = $challenge['user'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'verify');
    if ($action === 'cancel') {
        two_factor_clear_session_state();
        redirect('login.php?cancel=1');
    }
    $remaining = rate_limit_remaining('two-factor', (string)$user['id'], 5, 20);
    if ($remaining > 0) {
        $error = 'Too many incorrect codes. Verification is paused for 15 minutes.';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        try {
            $result = two_factor_verify_user_code($user, $code);
            if (!$result['ok']) {
                $locked = record_rate_failure('two-factor', (string)$user['id'], 5, 20, 900);
                $error = $locked > 0
                    ? 'Too many incorrect codes. Verification is paused for 15 minutes.'
                    : 'That authenticator or recovery code was not accepted. Wait for a new code and try again.';
            } else {
                clear_rate_failures('two-factor', (string)$user['id'], 5, 20);
                $destination = two_factor_complete_challenge($result['user'], $pending);
                audit_event('two-factor-sign-in-completed', (string)$user['id'], ['recoveryCode' => (bool)$result['recovery']]);
                redirect($destination !== '' ? $destination : 'index.php');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'Verification could not be completed.';
        }
    }
}
account_header('Verify Sign In', 'auth', true);
?>
<section class="auth-single-wrap">
  <article class="auth-panel auth-panel--single auth-panel--verify">
    <div class="verify-emblem"><img src="../assets/images/logo.webp" width="735" height="760" alt=""></div>
    <p class="eyebrow">Second step</p><h1>Verify your sign in.</h1>
    <p>Enter the current six-digit code from your authenticator app. A saved one-time recovery code also works.</p>
    <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack-form account-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="verify">
      <label><span>Authenticator or recovery code</span><input class="two-factor-code-input" type="text" name="code" required autocomplete="one-time-code" maxlength="24" inputmode="numeric" autofocus placeholder="123456"></label>
      <button class="button button--primary button--wide" type="submit">Verify and sign in</button>
    </form>
    <form method="post" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel"><button class="text-button" type="submit">Cancel and return to sign in</button></form>
  </article>
</section>
<?php account_footer(); ?>
