<?php
declare(strict_types=1);
require_once __DIR__ . '/../account/_layout.php';
if (setup_is_locked()) redirect('../account/login.php');
foreach (read_json_array(USERS_FILE) as $existing) {
    if (is_protected_admin_account($existing)) { lock_first_time_setup(); redirect('../account/login.php'); }
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requiredKey = trim((string)(getenv('ORG_SETUP_KEY') ?: ''));
    $submittedKey = trim((string)($_POST['setup_key'] ?? ''));
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $email = normalize_email((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if ($requiredKey !== '' && !hash_equals($requiredKey, $submittedKey)) {
        $error = 'The private setup key was not accepted.';
    } elseif ($displayName === '' || text_length($displayName) > 80) {
        $error = 'Enter an administrator display name.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid administrator email address.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } elseif (!password_is_strong($password)) {
        $error = 'Use an administrator password with at least 12 characters that is not commonly used.';
    } else {
        $permissions = array_fill_keys(array_keys(permission_catalog()), true);
        $admin = [
            'id' => make_id('user'), 'username' => 'administrator', 'email' => $email, 'displayName' => $displayName,
            'bio' => '', 'interests' => [], 'directoryVisibility' => 'hidden', 'allowMessages' => 'nobody', 'showOnline' => false,
            'role' => 'admin', 'permissions' => $permissions, 'protectedAdmin' => true,
            'membershipTier' => 'inner-circle', 'membershipStatus' => 'active', 'membershipExpiresAt' => null, 'entitlements' => [],
            'status' => 'approved', 'active' => true, 'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
            'securityVersion' => 1, 'emailVerified' => true, 'createdAt' => date(DATE_ATOM), 'updatedAt' => date(DATE_ATOM),
        ];
        try {
            update_json_array(USERS_FILE, static function (array $users) use ($admin): array {
                foreach ($users as $existing) {
                    if (is_protected_admin_account($existing)) throw new RuntimeException('A protected administrator already exists.');
                    if (normalize_email((string)($existing['email'] ?? '')) === $admin['email']) throw new RuntimeException('An account already uses that email address.');
                    if (normalize_username((string)($existing['username'] ?? '')) === $admin['username']) throw new RuntimeException('The administrator username is already in use.');
                }
                $users[] = $admin;
                return $users;
            });
            lock_first_time_setup();
            sign_in_user($admin);
            audit_event('protected-administrator-created', (string)$admin['id']);
            redirect('index.php');
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'The protected administrator could not be created.';
        }
    }
}
account_header('Initial Administration Setup', 'auth', true);
?>
<section class="auth-single-wrap"><article class="auth-panel auth-panel--single"><p class="eyebrow">One-time setup</p><h1>Create the protected administrator.</h1><p>This account controls staff access, permissions, memberships, resources, orders, contact messages, and security records. Setup permanently closes after creation.</p><?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack-form account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><?php if (trim((string)(getenv('ORG_SETUP_KEY') ?: '')) !== ''): ?><label><span>Private setup key</span><input type="password" name="setup_key" required autocomplete="off"></label><?php endif; ?><label><span>Administrator name</span><input type="text" name="display_name" required maxlength="80" autocomplete="name"></label><label><span>Administrator email</span><input type="email" name="email" required maxlength="254" autocomplete="email"></label><label><span>Password</span><span class="password-field"><input type="password" name="password" required minlength="12" autocomplete="new-password" data-password-input><button type="button" class="password-toggle" data-password-toggle>Show</button></span></label><label><span>Confirm password</span><input type="password" name="confirm_password" required minlength="12" autocomplete="new-password"></label><button class="button button--primary button--wide" type="submit">Create protected administrator</button></form></article></section>
<?php account_footer(); ?>
