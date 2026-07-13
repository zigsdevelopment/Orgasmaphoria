<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_permission('manage_accounts');
$actor = current_user();
$actorStored = find_user_by_id((string)$actor['id']) ?? $actor;
$actorProtected = is_protected_admin_account($actorStored);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $targetId = trim((string)($_POST['target_id'] ?? ''));
    $action = (string)($_POST['action'] ?? 'save');
    $target = find_user_by_id($targetId);
    if (!$target) {
        $error = 'The selected account could not be found.';
    } elseif (is_protected_admin_account($target)) {
        $error = 'The protected administrator cannot be changed from account management.';
    } elseif (($target['role'] ?? '') === 'admin' && !$actorProtected) {
        $error = 'Only the protected administrator can change another administrator account.';
    } elseif ($action === 'reset_2fa') {
        try {
            two_factor_admin_reset($target, $actorStored);
            $success = 'Two-factor authentication was reset for the selected account.';
        } catch (Throwable $exception) {
            $error = $exception->getMessage() ?: 'Two-factor authentication could not be reset.';
        }
    } else {
        $tier = (string)($_POST['membership_tier'] ?? 'listener');
        $status = (string)($_POST['status'] ?? 'approved');
        $role = (string)($_POST['role'] ?? 'member');
        if (!array_key_exists($tier, membership_levels()) || $tier === 'staff') $tier = 'listener';
        if (!in_array($status, ['approved', 'disabled'], true)) $status = 'approved';
        if (!in_array($role, ['member', 'staff', 'admin'], true)) $role = 'member';
        if ($role === 'admin' && !$actorProtected) {
            $error = 'Only the protected administrator can grant administrator access.';
        } elseif ($targetId === (string)$actor['id'] && ($status === 'disabled' || $role !== ($target['role'] ?? ''))) {
            $error = 'You cannot disable or change the role of the account currently in use.';
        } else {
            $permissions = [];
            foreach (permission_catalog() as $key => $label) $permissions[$key] = !empty($_POST['permissions'][$key]);
            if (!has_permission('manage_permissions')) {
                $role = (string)($target['role'] ?? 'member');
                $permissions = is_array($target['permissions'] ?? null) ? $target['permissions'] : [];
            }
            try {
                $saved = update_user_record($targetId, static function (array $account) use ($tier, $status, $role, $permissions): array {
                    $securityChanged = $status !== ($account['status'] ?? 'approved') || $role !== ($account['role'] ?? 'member') || $permissions !== ($account['permissions'] ?? []);
                    $account['membershipTier'] = $tier;
                    $account['membershipStatus'] = 'active';
                    $account['status'] = $status;
                    $account['active'] = $status === 'approved';
                    $account['role'] = $role;
                    $account['permissions'] = $role === 'staff' ? $permissions : [];
                    if ($securityChanged) $account['securityVersion'] = max(1, (int)($account['securityVersion'] ?? 1)) + 1;
                    $account['updatedAt'] = date(DATE_ATOM);
                    return $account;
                });
                audit_event('account-administration-updated', $targetId, ['role' => $saved['role'], 'status' => $saved['status'], 'membership' => $saved['membershipTier']]);
                $success = 'Account access was updated.';
            } catch (Throwable $exception) {
                $error = $exception->getMessage() ?: 'The account could not be updated.';
            }
        }
    }
}

$query = strtolower(trim((string)($_GET['q'] ?? '')));
$users = array_values(array_filter(read_json_array(USERS_FILE), static function (array $user) use ($query): bool {
    if ($query === '') return true;
    return str_contains(strtolower(implode(' ', [(string)($user['displayName'] ?? ''), (string)($user['username'] ?? ''), (string)($user['email'] ?? ''), (string)($user['role'] ?? ''), (string)($user['membershipTier'] ?? '')])), $query);
}));
usort($users, static function (array $a, array $b): int {
    if (!empty($a['protectedAdmin'])) return -1;
    if (!empty($b['protectedAdmin'])) return 1;
    return strcasecmp((string)($a['displayName'] ?? ''), (string)($b['displayName'] ?? ''));
});
admin_header('Accounts & Permissions', 'users');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Account administration</p><h1>Members, roles, and access.</h1><p>Memberships and permissions are enforced on the server. Protected administrator controls cannot be delegated or removed from this page.</p></div></section>
<section class="section"><div class="wrap">
  <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>
  <form class="library-toolbar" method="get"><label><span class="sr-only">Search accounts</span><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search name, email, username, role, or membership"></label><button class="button button--ghost" type="submit">Search</button><a class="text-link" href="users.php">Clear</a><span class="library-count"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></span></form>
  <div class="account-admin-list"><?php foreach ($users as $managed): $protected = is_protected_admin_account($managed); ?>
    <article class="account-admin-card<?= $protected ? ' is-protected' : '' ?>">
      <div class="account-admin-card__identity"><span class="avatar"><?= e(account_initials((string)($managed['displayName'] ?? 'Member'))) ?></span><div><h2><?= e($managed['displayName'] ?? 'Member') ?><?= $protected ? ' <span class="protected-pill">Protected</span>' : '' ?></h2><p>@<?= e($managed['username'] ?? 'member') ?> · <?= e($managed['email'] ?? 'No email') ?></p><div class="tag-row"><span><?= e(ucfirst((string)($managed['role'] ?? 'member'))) ?></span><span><?= e(ucwords(str_replace('-', ' ', (string)($managed['membershipTier'] ?? 'listener')))) ?></span><?php if (two_factor_is_enabled($managed)): ?><span>2FA enabled</span><?php endif; ?></div></div></div>
      <?php if ($protected): ?><div class="account-alert account-alert--info">This system account cannot be demoted, disabled, deleted, or edited through the staff dashboard.</div><?php else: ?>
      <form method="post" class="account-admin-form account-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="target_id" value="<?= e($managed['id']) ?>"><input type="hidden" name="action" value="save"><div class="form-grid"><label><span>Membership</span><select name="membership_tier"><option value="listener"<?= ($managed['membershipTier'] ?? 'listener') === 'listener' ? ' selected' : '' ?>>Listener</option><option value="velvet-patron"<?= ($managed['membershipTier'] ?? '') === 'velvet-patron' ? ' selected' : '' ?>>Velvet Patron</option><option value="inner-circle"<?= ($managed['membershipTier'] ?? '') === 'inner-circle' ? ' selected' : '' ?>>Inner Circle</option></select></label><label><span>Account status</span><select name="status"><option value="approved"<?= account_status($managed) === 'approved' ? ' selected' : '' ?>>Active</option><option value="disabled"<?= account_status($managed) === 'disabled' ? ' selected' : '' ?>>Disabled</option></select></label><label><span>Role</span><select name="role"<?= !has_permission('manage_permissions') ? ' disabled' : '' ?>><option value="member"<?= ($managed['role'] ?? 'member') === 'member' ? ' selected' : '' ?>>Member</option><option value="staff"<?= ($managed['role'] ?? '') === 'staff' ? ' selected' : '' ?>>Staff</option><?php if ($actorProtected): ?><option value="admin"<?= ($managed['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Administrator</option><?php endif; ?></select></label><div class="account-created"><span>Created</span><strong><?= e(date('M j, Y', strtotime((string)($managed['createdAt'] ?? 'now')))) ?></strong></div></div>
        <?php if (has_permission('manage_permissions')): ?><details class="permission-details"><summary>Granular staff permissions</summary><div class="permission-grid"><?php foreach (permission_catalog() as $key => $label): ?><label class="check-row"><input type="checkbox" name="permissions[<?= e($key) ?>]" value="1"<?= !empty($managed['permissions'][$key]) ? ' checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div></details><?php endif; ?>
        <div class="button-row"><button class="button button--primary" type="submit">Save account access</button></div>
      </form>
      <?php if (two_factor_is_enabled($managed)): ?><form method="post" class="inline-action-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="target_id" value="<?= e($managed['id']) ?>"><input type="hidden" name="action" value="reset_2fa"><button class="text-button" type="submit" data-confirm="Reset two-factor authentication for this account after confirming the account holder’s identity?">Reset lost 2FA access</button></form><?php endif; ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?></div>
</div></section>
<?php admin_footer(); ?>
