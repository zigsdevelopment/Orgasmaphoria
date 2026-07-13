<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if ($token === '') {
        $error = 'This reset link is invalid.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        try {
            if (!consume_password_reset($token, $password)) {
                $error = 'This reset link is invalid, expired, or has already been used.';
            } else {
                sign_out_user();
                $success = 'Your password has been updated. You may now sign in.';
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'The password could not be updated.';
        }
    }
}
account_header('Reset Password', 'auth', true);
?>
<section class="auth-single-wrap">
  <article class="auth-panel auth-panel--single">
    <a class="auth-back-link" href="login.php">← Back to sign in</a>
    <p class="eyebrow">Secure recovery</p>
    <h1>Choose a new password.</h1>
    <p>Use at least 12 characters and avoid passwords used on other websites.</p>
    <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><a class="button button--primary button--wide" href="login.php">Continue to sign in</a><?php elseif ($token !== ''): ?>
    <form method="post" class="stack-form account-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <label><span>New password</span><span class="password-field"><input type="password" name="password" autocomplete="new-password" required minlength="12" data-password-input><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">Show</button></span></label>
      <label><span>Confirm new password</span><input type="password" name="confirm_password" autocomplete="new-password" required minlength="12"></label>
      <button class="button button--primary button--wide" type="submit">Update password</button>
    </form>
    <?php else: ?><div class="account-alert account-alert--error" role="alert">This reset link is missing its secure token.</div><?php endif; ?>
  </article>
</section>
<?php account_footer(); ?>
