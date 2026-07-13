<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_permission('view_audit');
$logs = array_reverse(read_json_array(AUDIT_LOG_FILE));
$logs = array_slice($logs, 0, 500);
admin_header('Security Audit', 'security');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Security and accountability</p><h1>Audit log.</h1><p>Recent authentication, account, permission, contact, resource, order, and administrative actions.</p></div></section>
<section class="section"><div class="wrap"><?php if ($logs): ?><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Action</th><th>Actor</th><th>Target</th><th>Details</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= e(date('M j, Y, g:i a', strtotime((string)($log['at'] ?? 'now')))) ?></td><td><?= e($log['action'] ?? '') ?></td><td><?= e($log['actorName'] ?? 'System') ?></td><td><?= e($log['targetId'] ?? '') ?></td><td><code><?= e(json_encode($log['details'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state empty-state--wide"><strong>No audit records have been created.</strong></div><?php endif; ?></div></section>
<?php admin_footer(); ?>
