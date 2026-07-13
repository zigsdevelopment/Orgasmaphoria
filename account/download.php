<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_login('login.php');
$user = current_user();
$stored = find_user_by_id((string)$user['id']) ?? $user;
$id = trim((string)($_GET['id'] ?? ''));
$resource = null;
foreach (read_json_array(RESOURCES_FILE) as $candidate) {
    if ((string)($candidate['id'] ?? '') === $id) { $resource = $candidate; break; }
}
if (!$resource || ($resource['status'] ?? '') !== 'published' || !user_can_access_resource($stored, $resource)) {
    http_response_code(404);
    exit('The requested resource is not available.');
}
$fileName = basename((string)($resource['fileName'] ?? ''));
$path = RESOURCE_UPLOAD_DIR . '/' . $fileName;
if ($fileName === '' || !is_file($path)) {
    http_response_code(404);
    exit('The resource file could not be found.');
}
$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)($resource['originalName'] ?? $resource['title'] ?? 'resource')) ?: 'resource';
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
header('Cache-Control: private, no-store, max-age=0');
audit_event('resource-opened', $id);
readfile($path);
exit;
