<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_permission('manage_content');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'upload');
    if ($action === 'delete') {
        $id = trim((string)($_POST['resource_id'] ?? ''));
        $deleted = null;
        update_json_array(RESOURCES_FILE, static function (array $resources) use ($id, &$deleted): array {
            $remaining = [];
            foreach ($resources as $resource) {
                if ((string)($resource['id'] ?? '') === $id) { $deleted = $resource; continue; }
                $remaining[] = $resource;
            }
            return $remaining;
        });
        if ($deleted) {
            $file = RESOURCE_UPLOAD_DIR . '/' . basename((string)($deleted['fileName'] ?? ''));
            if (is_file($file)) @unlink($file);
            audit_event('resource-deleted', $id, ['title' => (string)($deleted['title'] ?? '')]);
            $success = 'The resource was deleted.';
        } else $error = 'The resource could not be found.';
    } elseif ($action === 'toggle') {
        $id = trim((string)($_POST['resource_id'] ?? ''));
        $newStatus = (string)($_POST['status'] ?? 'draft');
        if (!in_array($newStatus, ['draft', 'published'], true)) $newStatus = 'draft';
        try {
            update_json_array(RESOURCES_FILE, static function (array $resources) use ($id, $newStatus): array {
                $found = false;
                foreach ($resources as $index => $resource) {
                    if ((string)($resource['id'] ?? '') !== $id) continue;
                    $resources[$index]['status'] = $newStatus;
                    $resources[$index]['updatedAt'] = date(DATE_ATOM);
                    $found = true;
                    break;
                }
                if (!$found) throw new RuntimeException('The resource could not be found.');
                return $resources;
            });
            audit_event('resource-status-changed', $id, ['status' => $newStatus]);
            $success = 'The resource status was updated.';
        } catch (Throwable $exception) { $error = $exception->getMessage(); }
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $contentType = trim((string)($_POST['content_type'] ?? 'Document'));
        $accessLevel = trim((string)($_POST['access_level'] ?? 'listener'));
        $status = (string)($_POST['status'] ?? 'draft');
        $tags = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? '')))));
        $allowedAccess = ['public', 'listener', 'velvet-patron', 'inner-circle', 'staff'];
        foreach (product_catalog() as $slug => $product) if (($product['kind'] ?? '') === 'product') $allowedAccess[] = 'purchase:' . $slug;
        if ($title === '' || text_length($title) > 140) $error = 'Enter a title using 140 characters or fewer.';
        elseif (text_length($subtitle) > 180 || text_length($description) > 2000) $error = 'The subtitle or description is too long.';
        elseif (!in_array($accessLevel, $allowedAccess, true)) $error = 'Choose a valid access level.';
        elseif (!in_array($status, ['draft', 'published'], true)) $error = 'Choose a valid publication status.';
        else {
            $savedFile = '';
            try {
                $savedFile = save_private_resource_upload($_FILES['file'] ?? []);
                if ($savedFile === '') throw new RuntimeException('Choose a file to upload.');
                $extension = strtolower(pathinfo($savedFile, PATHINFO_EXTENSION));
                $resource = [
                    'id' => make_id('resource'), 'title' => $title, 'subtitle' => $subtitle, 'description' => $description,
                    'contentType' => $contentType !== '' ? $contentType : 'Document', 'format' => strtoupper($extension),
                    'accessLevel' => $accessLevel, 'status' => $status, 'tags' => array_slice($tags, 0, 15),
                    'fileName' => $savedFile, 'originalName' => basename((string)($_FILES['file']['name'] ?? ($title . '.' . $extension))),
                    'createdBy' => (string)(current_user()['id'] ?? ''), 'createdAt' => date(DATE_ATOM), 'updatedAt' => date(DATE_ATOM),
                ];
                update_json_array(RESOURCES_FILE, static function (array $resources) use ($resource): array { $resources[] = $resource; return $resources; });
                audit_event('resource-uploaded', (string)$resource['id'], ['title' => $title, 'access' => $accessLevel, 'status' => $status]);
                $success = 'The resource was uploaded securely.';
            } catch (Throwable $exception) {
                if ($savedFile !== '' && is_file(RESOURCE_UPLOAD_DIR . '/' . basename($savedFile))) @unlink(RESOURCE_UPLOAD_DIR . '/' . basename($savedFile));
                $error = $exception->getMessage() ?: 'The resource could not be uploaded.';
            }
        }
    }
}

$resources = read_json_array(RESOURCES_FILE);
usort($resources, static fn(array $a, array $b): int => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$accessLabels = ['public' => 'Public', 'listener' => 'All signed-in members', 'velvet-patron' => 'Velvet Patron and Inner Circle', 'inner-circle' => 'Inner Circle only', 'staff' => 'Staff only'];
foreach (product_catalog() as $slug => $product) if (($product['kind'] ?? '') === 'product') $accessLabels['purchase:' . $slug] = 'Purchase: ' . $product['title'];
admin_header('Private Resources', 'resources');
?>
<section class="member-page-head"><div class="wrap"><p class="eyebrow">Content administration</p><h1>Private resource library.</h1><p>Files remain outside direct public access and are delivered only after server-side account and permission checks.</p></div></section>
<section class="section"><div class="wrap admin-resource-layout">
  <form method="post" enctype="multipart/form-data" class="form-card account-form admin-upload-card"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="upload"><p class="eyebrow">New resource</p><h2>Upload content</h2><?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?><?php if ($success): ?><div class="account-alert account-alert--success" role="status"><?= e($success) ?></div><?php endif; ?><label><span>Title</span><input type="text" name="title" required maxlength="140"></label><label><span>Subtitle</span><input type="text" name="subtitle" maxlength="180"></label><label><span>Description</span><textarea name="description" rows="5" maxlength="2000"></textarea></label><div class="form-grid"><label><span>Content type</span><select name="content_type"><option>Book</option><option>Guide</option><option>Game</option><option>Activity</option><option>Invitation</option><option>Workbook</option><option>Document</option><option>Artwork</option></select></label><label><span>Access</span><select name="access_level"><?php foreach ($accessLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label><span>Status</span><select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label><label><span>Tags</span><input type="text" name="tags" maxlength="300" placeholder="music, workbook, event"></label></div><label><span>File</span><input type="file" name="file" required accept=".pdf,.epub,.zip,.jpg,.jpeg,.png,.webp,.txt"><small>PDF, EPUB, ZIP, JPG, PNG, WebP, or TXT; maximum 25 MB.</small></label><button class="button button--primary" type="submit">Upload resource</button></form>

  <section class="admin-resource-list"><div class="panel-heading"><div><p class="eyebrow">Inventory</p><h2><?= count($resources) ?> stored resource<?= count($resources) === 1 ? '' : 's' ?></h2></div></div><?php if ($resources): ?><?php foreach ($resources as $resource): ?><article class="resource-admin-card"><div><div class="tag-row"><span><?= e($resource['contentType'] ?? 'Resource') ?></span><span><?= e($resource['format'] ?? 'FILE') ?></span><span><?= e($resource['status'] ?? 'draft') ?></span></div><h3><?= e($resource['title'] ?? 'Resource') ?></h3><p><?= e($resource['subtitle'] ?? '') ?></p><small><?= e($accessLabels[$resource['accessLevel'] ?? 'listener'] ?? ($resource['accessLevel'] ?? 'Member access')) ?> · Added <?= e(date('M j, Y', strtotime((string)($resource['createdAt'] ?? 'now')))) ?></small></div><div class="resource-admin-card__actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="resource_id" value="<?= e($resource['id']) ?>"><input type="hidden" name="status" value="<?= ($resource['status'] ?? 'draft') === 'published' ? 'draft' : 'published' ?>"><button class="button button--ghost button--small" type="submit"><?= ($resource['status'] ?? 'draft') === 'published' ? 'Move to draft' : 'Publish' ?></button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="resource_id" value="<?= e($resource['id']) ?>"><button class="text-button text-button--danger" type="submit" data-confirm="Permanently delete this resource and its private file?">Delete</button></form></div></article><?php endforeach; ?><?php else: ?><div class="empty-state"><strong>No resources have been uploaded.</strong></div><?php endif; ?></section>
</div></section>
<?php admin_footer(); ?>
