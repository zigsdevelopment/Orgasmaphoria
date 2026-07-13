<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_permission('view_contacts');
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = trim((string)($_POST['contact_id'] ?? ''));
    $status = (string)($_POST['status'] ?? 'reviewed');
    if (!in_array($status, ['new', 'reviewed', 'closed'], true)) $status = 'reviewed';
    try {
        update_json_array(CONTACTS_FILE, static function (array $records) use ($id, $status): array {
            $found = false;
            foreach ($records as $index => $record) {
                if ((string)($record['id'] ?? '') !== $id) continue;
                $records[$index]['status'] = $status;
                $records[$index]['updatedAt'] = date(DATE_ATOM);
                $found = true;
                break;
            }
            if (!$found) throw new RuntimeException('The contact message could not be found.');
            return $records;
        });
        audit_event('contact-status-updated', $id, ['status' => $status]);
        $success = 'The contact message status was updated.';
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
$statusFilter = (string)($_GET['status'] ?? '');
$contacts = array_values(array_filter(read_json_array(CONTACTS_FILE), static fn(array $record): bool => $statusFilter === '' || ($record['status'] ?? 'new') === $statusFilter));
usort($contacts, static fn(array $a, array $b): int => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
admin_header('Contact Inbox', 'contacts');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Secure contact inbox</p><h1>Public inquiries.</h1><p>Messages are stored server-side and may also be emailed when a recipient is configured privately.</p></div></section>
<section class="section"><div class="wrap"><?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?><div class="filter-pills"><a href="contacts.php"<?= $statusFilter === '' ? ' aria-current="page"' : '' ?>>All</a><a href="contacts.php?status=new"<?= $statusFilter === 'new' ? ' aria-current="page"' : '' ?>>New</a><a href="contacts.php?status=reviewed"<?= $statusFilter === 'reviewed' ? ' aria-current="page"' : '' ?>>Reviewed</a><a href="contacts.php?status=closed"<?= $statusFilter === 'closed' ? ' aria-current="page"' : '' ?>>Closed</a></div><?php if ($contacts): ?><div class="contact-admin-list"><?php foreach ($contacts as $contact): ?><article class="contact-admin-card"><div class="contact-admin-card__head"><div><div class="tag-row"><span><?= e($contact['topic'] ?? 'General inquiry') ?></span><span><?= e(ucfirst((string)($contact['status'] ?? 'new'))) ?></span></div><h2><?= e($contact['subject'] ?? 'Message') ?></h2><p>From <strong><?= e($contact['name'] ?? '') ?></strong> · <a href="mailto:<?= e($contact['email'] ?? '') ?>"><?= e($contact['email'] ?? '') ?></a> · <?= e(date('M j, Y, g:i a', strtotime((string)($contact['createdAt'] ?? 'now')))) ?></p></div></div><div class="contact-message-body"><?= nl2br(e($contact['message'] ?? '')) ?></div><div class="button-row"><a class="button button--ghost button--small" href="mailto:<?= e($contact['email'] ?? '') ?>?subject=Re:%20<?= rawurlencode((string)($contact['subject'] ?? 'Orgasmaphoria inquiry')) ?>">Reply by email</a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="contact_id" value="<?= e($contact['id']) ?>"><select name="status" aria-label="Message status"><option value="new"<?= ($contact['status'] ?? 'new') === 'new' ? ' selected' : '' ?>>New</option><option value="reviewed"<?= ($contact['status'] ?? '') === 'reviewed' ? ' selected' : '' ?>>Reviewed</option><option value="closed"<?= ($contact['status'] ?? '') === 'closed' ? ' selected' : '' ?>>Closed</option></select><button class="button button--ghost button--small" type="submit">Save status</button></form></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No contact messages match this view.</strong></div><?php endif; ?></div></section>
<?php admin_footer(); ?>
