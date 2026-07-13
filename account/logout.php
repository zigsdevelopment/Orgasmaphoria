<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
verify_csrf();
$user = current_user();
if ($user) audit_event('account-sign-out', (string)$user['id']);
sign_out_user();
set_flash('success', 'You have been signed out.');
redirect('login.php');
