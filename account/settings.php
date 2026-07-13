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
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $username = normalize_username((string)($_POST['username'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $interests = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['interests'] ?? '')))));
    $directoryVisibility = (string)($_POST['directory_visibility'] ?? 'members');
    $allowMessages = (string)($_POST['allow_messages'] ?? 'members');
    $showOnline = !empty($_POST['show_online']);

    if ($displayName === '' || text_length($displayName) > 80) {
        $error = 'Enter a display name using 80 characters or fewer.';
    } elseif (!valid_username($username)) {
        $error = 'Use 3–30 letters, numbers, dots, dashes, or underscores for the username.';
    } elseif (!username_is_available($username, (string)$stored['id'])) {
        $error = 'That username is already in use.';
    } elseif (text_length($bio) > 800) {
        $error = 'The biography must be 800 characters or fewer.';
    } elseif (count($interests) > 12) {
        $error = 'List no more than 12 interests.';
    } elseif (!in_array($directoryVisibility, ['members', 'hidden'], true) || !in_array($allowMessages, ['members', 'nobody'], true)) {
        $error = 'One of the privacy choices was not recognized.';
    } else {
        try {
            $stored = update_user_record((string)$stored['id'], static function (array $account) use ($displayName, $username, $bio, $interests, $directoryVisibility, $allowMessages, $showOnline): array {
                $account['displayName'] = $displayName;
                $account['username'] = $username;
                $account['bio'] = $bio;
                $account['interests'] = array_slice($interests, 0, 12);
                $account['directoryVisibility'] = $directoryVisibility;
                $account['allowMessages'] = $allowMessages;
                $account['showOnline'] = $showOnline;
                $account['updatedAt'] = date(DATE_ATOM);
                return $account;
            });
            $_SESSION['site_user'] = public_user($stored);
            audit_event('profile-settings-updated', (string)$stored['id']);
            $success = 'Your profile and privacy settings were saved.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'The settings could not be saved.';
        }
    }
}

account_header('Profile & Privacy', 'settings');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Personal controls</p><h1>Profile and privacy.</h1><p>Choose what other signed-in members can see and how they may contact you.</p></div></section>
<section class="section"><div class="wrap settings-grid">
  <form method="post" class="form-card account-form">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <p class="eyebrow">Profile</p><h2>How you appear</h2>
    <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>
    <div class="form-grid">
      <label><span>Display name</span><input type="text" name="display_name" required maxlength="80" autocomplete="name" value="<?= e($stored['displayName'] ?? '') ?>"></label>
      <label><span>Username</span><input type="text" name="username" required maxlength="30" pattern="[A-Za-z0-9._-]+" autocomplete="username" value="<?= e($stored['username'] ?? '') ?>"></label>
      <label class="form-grid__wide"><span>Biography</span><textarea name="bio" rows="6" maxlength="800"><?= e($stored['bio'] ?? '') ?></textarea></label>
      <label class="form-grid__wide"><span>Interests</span><input type="text" name="interests" maxlength="300" value="<?= e(implode(', ', is_array($stored['interests'] ?? null) ? $stored['interests'] : [])) ?>" placeholder="Music, books, podcasts, events"><small>Separate interests with commas.</small></label>
    </div>
    <p class="eyebrow form-section-label">Privacy</p><h2>Member visibility</h2>
    <div class="form-grid">
      <label><span>Member directory</span><select name="directory_visibility"><option value="members"<?= ($stored['directoryVisibility'] ?? 'members') === 'members' ? ' selected' : '' ?>>Visible to signed-in members</option><option value="hidden"<?= ($stored['directoryVisibility'] ?? '') === 'hidden' ? ' selected' : '' ?>>Hidden from the directory</option></select></label>
      <label><span>Direct messages</span><select name="allow_messages"><option value="members"<?= ($stored['allowMessages'] ?? 'members') === 'members' ? ' selected' : '' ?>>Allow from members</option><option value="nobody"<?= ($stored['allowMessages'] ?? '') === 'nobody' ? ' selected' : '' ?>>Do not allow new messages</option></select></label>
    </div>
    <label class="check-row"><input type="checkbox" name="show_online" value="1"<?= !empty($stored['showOnline']) ? ' checked' : '' ?>><span>Allow members to see when I am recently active.</span></label>
    <button class="button button--primary" type="submit">Save profile and privacy</button>
  </form>
  <div class="settings-stack">
    <section class="form-card"><p class="eyebrow">Account email</p><h2><?= e($stored['email'] ?? '') ?></h2><p>This address is used for sign-in, receipts, and account recovery.</p><a class="text-link" href="../contact.php?topic=Account%20support">Request an email change →</a></section>
    <section class="form-card"><p class="eyebrow">Security</p><h2>Password and two-factor authentication</h2><p>Change your password, enable optional authenticator-app verification, and manage recovery codes.</p><a class="button button--ghost" href="security.php">Open security settings</a></section>
    <section class="form-card"><p class="eyebrow">Display</p><h2>Accessibility preferences</h2><p>Use the floating <strong>Aa</strong> button to adjust text size, contrast, motion, font, spacing, and appearance.</p><a class="text-link" href="../accessibility.html">Accessibility statement →</a></section>
    <section class="form-card form-card--danger"><p class="eyebrow">Session</p><h2>Sign out</h2><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button--ghost" type="submit">Sign out of this device</button></form></section>
  </div>
</div></section>
<?php account_footer(); ?>
