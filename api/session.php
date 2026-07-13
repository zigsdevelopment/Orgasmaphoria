<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$user = current_user();
if (!$user) {
    echo json_encode(['authenticated' => false, 'csrfToken' => csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
}
echo json_encode([
    'authenticated' => true,
    'csrfToken' => csrf_token(),
    'user' => [
        'id' => $user['id'],
        'displayName' => $user['displayName'],
        'username' => $user['username'],
        'role' => $user['role'],
        'membershipTier' => $user['membershipTier'],
        'twoFactorEnabled' => $user['twoFactorEnabled'],
    ],
    'role' => $user['role'],
    'permissions' => $user['permissions'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
