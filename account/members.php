<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$query = strtolower(trim((string)($_GET['q'] ?? '')));
$members = array_values(array_filter(read_json_array(USERS_FILE), static function (array $member) use ($user, $query): bool {
    if (!account_is_approved($member) || (string)($member['id'] ?? '') === (string)$user['id']) return false;
    if (($member['directoryVisibility'] ?? 'members') !== 'members') return false;
    if ($query !== '') {
        $haystack = strtolower(implode(' ', [(string)($member['displayName'] ?? ''), (string)($member['username'] ?? ''), (string)($member['bio'] ?? ''), implode(' ', is_array($member['interests'] ?? null) ? $member['interests'] : [])]));
        if (!str_contains($haystack, $query)) return false;
    }
    return true;
}));
usort($members, static fn(array $a, array $b): int => strcasecmp((string)($a['displayName'] ?? ''), (string)($b['displayName'] ?? '')));
account_header('Member Directory', 'members');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Member community</p><h1>Meet the community.</h1><p>Only signed-in members who choose to appear in the directory are shown.</p></div></section>
<section class="section"><div class="wrap">
  <form class="library-toolbar" method="get"><label><span class="sr-only">Search members</span><input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search names, usernames, or interests"></label><button class="button button--ghost" type="submit">Search</button><a class="text-link" href="members.php">Clear</a><span class="library-count"><?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?></span></form>
  <?php if ($members): ?><div class="member-grid"><?php foreach ($members as $member): ?><article class="member-card"><div class="avatar"><?= e(account_initials((string)($member['displayName'] ?? 'Member'))) ?></div><div><h2><?= e($member['displayName'] ?? 'Member') ?></h2><p class="member-handle">@<?= e($member['username'] ?? 'member') ?></p><p><?= e($member['bio'] ?: 'Orgasmaphoria community member.') ?></p><?php if (!empty($member['interests'])): ?><div class="tag-row"><?php foreach (array_slice((array)$member['interests'], 0, 5) as $interest): ?><span><?= e($interest) ?></span><?php endforeach; ?></div><?php endif; ?></div><div class="member-card__actions"><?php if (($member['allowMessages'] ?? 'members') === 'members'): ?><a class="button button--ghost" href="messages.php?to=<?= rawurlencode((string)$member['id']) ?>">Message</a><?php else: ?><span class="fine-print">Not accepting new messages</span><?php endif; ?></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No members match this search.</strong></div><?php endif; ?>
</div></section>
<?php account_footer(); ?>
