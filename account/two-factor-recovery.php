<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$codes = $_SESSION['new_two_factor_recovery_codes'] ?? null;
if (!is_array($codes) || !$codes) redirect('security.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    unset($_SESSION['new_two_factor_recovery_codes']);
    redirect('security.php');
}
account_header('Save Recovery Codes', 'security');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">One-time display</p><h1>Save your recovery codes.</h1><p>Each code can replace your authenticator once. Store them privately and separately from your phone.</p></div></section>
<section class="section"><div class="wrap recovery-wrap">
  <div class="account-alert account-alert--warning"><strong>These codes will not be shown again.</strong> Save them in a trusted password manager or print and secure them.</div>
  <section class="form-card recovery-code-panel">
    <div class="panel-heading"><div><p class="eyebrow">Emergency access</p><h2>Recovery codes</h2></div><div class="button-row"><button class="button button--ghost button--small" type="button" data-copy-recovery>Copy all</button><button class="button button--ghost button--small" type="button" onclick="window.print()">Print</button></div></div>
    <div class="recovery-code-grid" data-recovery-codes><?php foreach ($codes as $code): ?><code><?= e($code) ?></code><?php endforeach; ?></div>
    <p class="form-note" data-copy-status aria-live="polite"></p>
    <form method="post" class="stack-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="check-row"><input type="checkbox" required><span>I have saved these recovery codes somewhere secure.</span></label><button class="button button--primary" type="submit">Continue to security settings</button></form>
  </section>
</div></section>
<?php account_footer(); ?>
