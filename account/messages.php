<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$error = '';
$success = '';
$selectedId = trim((string)($_GET['to'] ?? $_GET['with'] ?? $_POST['recipient_id'] ?? ''));
$selected = $selectedId !== '' ? find_user_by_id($selectedId) : null;
if ($selected && (!account_is_approved($selected) || (string)$selected['id'] === (string)$stored['id'])) $selected = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $body = trim((string)($_POST['message'] ?? ''));
    if (!$selected) {
        $error = 'Choose a member before sending a message.';
    } elseif (($selected['allowMessages'] ?? 'members') !== 'members') {
        $error = 'This member is not accepting new messages.';
    } elseif ($body === '' || text_length($body) > 2000) {
        $error = 'Enter a message using 2,000 characters or fewer.';
    } else {
        $recent = 0;
        $cutoff = time() - 60;
        foreach (read_json_array(MESSAGES_FILE) as $message) {
            if ((string)($message['senderId'] ?? '') === (string)$stored['id'] && strtotime((string)($message['createdAt'] ?? '1970-01-01')) >= $cutoff) $recent++;
        }
        if ($recent >= 10) {
            $error = 'Please wait a moment before sending more messages.';
        } else {
            $record = ['id' => make_id('message'), 'senderId' => (string)$stored['id'], 'recipientId' => (string)$selected['id'], 'body' => $body, 'createdAt' => date(DATE_ATOM), 'readAt' => null];
            update_json_array(MESSAGES_FILE, static function (array $messages) use ($record): array { $messages[] = $record; return count($messages) > 20000 ? array_slice($messages, -20000) : $messages; });
            audit_event('direct-message-sent', (string)$selected['id']);
            $success = 'Message sent.';
        }
    }
}

$allMessages = read_json_array(MESSAGES_FILE);
$partnerIds = [];
foreach ($allMessages as $message) {
    $sender = (string)($message['senderId'] ?? '');
    $recipient = (string)($message['recipientId'] ?? '');
    if ($sender === (string)$stored['id'] && $recipient !== '') $partnerIds[$recipient] = max($partnerIds[$recipient] ?? 0, strtotime((string)($message['createdAt'] ?? '')) ?: 0);
    if ($recipient === (string)$stored['id'] && $sender !== '') $partnerIds[$sender] = max($partnerIds[$sender] ?? 0, strtotime((string)($message['createdAt'] ?? '')) ?: 0);
}
arsort($partnerIds);
$partners = [];
foreach (array_keys($partnerIds) as $id) { $member = find_user_by_id((string)$id); if ($member) $partners[] = $member; }
if (!$selected && $partners) { $selected = $partners[0]; $selectedId = (string)$selected['id']; }

$thread = [];
if ($selected) {
    update_json_array(MESSAGES_FILE, static function (array $messages) use ($stored, $selected, &$thread): array {
        foreach ($messages as $index => $message) {
            $sender = (string)($message['senderId'] ?? '');
            $recipient = (string)($message['recipientId'] ?? '');
            $matches = ($sender === (string)$stored['id'] && $recipient === (string)$selected['id']) || ($sender === (string)$selected['id'] && $recipient === (string)$stored['id']);
            if (!$matches) continue;
            if ($recipient === (string)$stored['id'] && empty($message['readAt'])) $messages[$index]['readAt'] = date(DATE_ATOM);
            $thread[] = $messages[$index];
        }
        return $messages;
    });
    usort($thread, static fn(array $a, array $b): int => strcmp((string)($a['createdAt'] ?? ''), (string)($b['createdAt'] ?? '')));
}

account_header('Messages', 'messages');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Private member messages</p><h1>Conversations.</h1><p>Connect with members who have chosen to accept direct messages.</p></div></section>
<section class="section"><div class="wrap messages-layout">
  <aside class="conversation-list"><div class="conversation-list__head"><h2>Messages</h2><a class="icon-button" href="members.php" aria-label="Start a new message">+</a></div><?php if ($partners): ?><?php foreach ($partners as $partner): ?><a class="conversation-link<?= $selected && (string)$selected['id'] === (string)$partner['id'] ? ' is-active' : '' ?>" href="messages.php?with=<?= rawurlencode((string)$partner['id']) ?>"><span class="avatar"><?= e(account_initials((string)($partner['displayName'] ?? 'Member'))) ?></span><span><strong><?= e($partner['displayName'] ?? 'Member') ?></strong><small>@<?= e($partner['username'] ?? 'member') ?></small></span></a><?php endforeach; ?><?php else: ?><div class="empty-state"><strong>No conversations yet.</strong><p>Visit the member directory to start one.</p><a class="button button--ghost" href="members.php">Browse members</a></div><?php endif; ?></aside>
  <section class="message-thread-panel">
    <?php if ($selected): ?><div class="message-thread-head"><span class="avatar"><?= e(account_initials((string)($selected['displayName'] ?? 'Member'))) ?></span><div><h2><?= e($selected['displayName'] ?? 'Member') ?></h2><p>@<?= e($selected['username'] ?? 'member') ?></p></div></div>
      <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?>
      <div class="message-thread"><?php if ($thread): ?><?php foreach ($thread as $message): ?><article class="message-bubble<?= (string)($message['senderId'] ?? '') === (string)$stored['id'] ? ' is-mine' : '' ?>"><p><?= nl2br(e($message['body'] ?? '')) ?></p><time datetime="<?= e($message['createdAt'] ?? '') ?>"><?= e(date('M j, g:i a', strtotime((string)($message['createdAt'] ?? 'now')))) ?></time></article><?php endforeach; ?><?php else: ?><div class="empty-state"><strong>Start the conversation.</strong><p>Messages are private between the participating accounts and authorized technical administrators when required for safety or legal reasons.</p></div><?php endif; ?></div>
      <?php if (($selected['allowMessages'] ?? 'members') === 'members'): ?><form method="post" class="message-compose"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="recipient_id" value="<?= e($selected['id']) ?>"><label><span class="sr-only">Message</span><textarea name="message" rows="3" maxlength="2000" required placeholder="Write a message…"></textarea></label><button class="button button--primary" type="submit">Send</button></form><?php else: ?><div class="account-alert account-alert--warning">This member is not accepting new messages.</div><?php endif; ?>
    <?php else: ?><div class="empty-state empty-state--wide"><strong>Select a conversation or find a member.</strong><a class="button button--primary" href="members.php">Open member directory</a></div><?php endif; ?>
  </section>
</div></section>
<?php account_footer(); ?>
