<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
if (current_user()) redirect('security.php');
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalize_email((string)($_POST['email'] ?? ''));
    $remaining = rate_limit_remaining('password-reset', $email, 3, 10);
    if ($remaining > 0) {
        $error = 'Too many reset requests were submitted. Try again later.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        create_password_reset($email);
        record_rate_failure('password-reset', $email, 3, 10, 900);
        $success = 'If an account uses that email address, password reset instructions have been sent.';
    }
}
account_header('Forgot Password', 'auth', true);
?>
<section class="auth-single-wrap">
  <article class="auth-panel auth-panel--single">
    <a class="auth-back-link" href="login.php">← Back to sign in</a>
    <p class="eyebrow">Account recovery</p>
    <h1>Reset your password.</h1>
    <p>Enter the email connected to your account. Reset links expire after one hour and can be used only once.</p>
    <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>
    <form method="post" class="stack-form account-form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label><span>Email</span><input type="email" name="email" autocomplete="email" required maxlength="254" value="<?= e($_POST['email'] ?? '') ?>"></label>
      <button class="button button--primary button--wide" type="submit">Send reset link</button>
    </form>
  </article>
</section>
<?php account_footer(); ?>
