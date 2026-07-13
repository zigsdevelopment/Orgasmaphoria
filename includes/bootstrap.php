<?php
/**
 * ORGASMAPHORIA SHARED APPLICATION BOOTSTRAP
 * -------------------------------------------
 * Server-side sessions, accounts, permissions, private JSON storage,
 * CSRF protection, rate limiting, password recovery, audit logging,
 * membership access, contact storage, and security helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
date_default_timezone_set(SITE_TIMEZONE);

define('SITE_ROOT', dirname(__DIR__));
$externalStorage = trim((string)(getenv('ORG_PRIVATE_STORAGE') ?: ''));
define('PRIVATE_STORAGE_DIR', $externalStorage !== '' ? rtrim($externalStorage, '/\\') : SITE_ROOT . '/storage-private');
define('USERS_FILE', PRIVATE_STORAGE_DIR . '/users.json');
define('MESSAGES_FILE', PRIVATE_STORAGE_DIR . '/messages.json');
define('CONTACTS_FILE', PRIVATE_STORAGE_DIR . '/contacts.json');
define('RESOURCES_FILE', PRIVATE_STORAGE_DIR . '/resources.json');
define('RESOURCE_UPLOAD_DIR', PRIVATE_STORAGE_DIR . '/resources');
define('LOGIN_ATTEMPTS_FILE', PRIVATE_STORAGE_DIR . '/login-attempts.json');
define('AUDIT_LOG_FILE', PRIVATE_STORAGE_DIR . '/audit-log.json');
define('PASSWORD_RESETS_FILE', PRIVATE_STORAGE_DIR . '/password-resets.json');
define('ORDERS_FILE', PRIVATE_STORAGE_DIR . '/orders.json');
define('SETUP_LOCK_FILE', PRIVATE_STORAGE_DIR . '/setup.lock');
define('TWO_FACTOR_KEY_FILE', PRIVATE_STORAGE_DIR . '/two-factor.key');

foreach ([PRIVATE_STORAGE_DIR, RESOURCE_UPLOAD_DIR] as $directory) {
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    @chmod($directory, 0700);
}
foreach ([USERS_FILE, MESSAGES_FILE, CONTACTS_FILE, RESOURCES_FILE, LOGIN_ATTEMPTS_FILE, AUDIT_LOG_FILE, PASSWORD_RESETS_FILE, ORDERS_FILE] as $jsonFile) {
    if (!is_file($jsonFile)) @file_put_contents($jsonFile, "[]\n", LOCK_EX);
    @chmod($jsonFile, 0600);
}

$usingHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://open.spotify.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: blob:; frame-src https://open.spotify.com https://js.stripe.com; connect-src 'self' https://api.stripe.com; form-action 'self' https://checkout.stripe.com; frame-ancestors 'self'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests");
    if ($usingHttps) header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $usingHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** Escape text for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_path(string $path = ''): string
{
    $base = rtrim(SITE_BASE_PATH, '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function safe_return_path(string $path, string $fallback = ''): string
{
    $path = trim($path);
    if ($path === '' || str_contains($path, "\r") || str_contains($path, "\n")) return $fallback;
    $parts = parse_url($path);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) return $fallback;
    if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z0-9._\/-]+(?:\?[A-Za-z0-9._~%&=+\/-]*)?(?:#[A-Za-z0-9._~-]*)?$/', $path)) return $fallback;
    return $path;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function make_id(string $prefix): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function read_json_array(string $path): array
{
    $contents = @file_get_contents($path);
    if ($contents === false || trim($contents) === '') return [];
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? array_values($decoded) : [];
}

function write_json_array(string $path, array $value): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('The private storage directory could not be created.');
    }
    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false) throw new RuntimeException('The server could not lock private storage.');
    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('The server could not lock private storage.');
        $temporary = tempnam($directory, '.org_');
        if ($temporary === false) throw new RuntimeException('The server could not create a temporary file.');
        $json = json_encode(array_values($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($temporary, $json . "\n", LOCK_EX) === false) {
            @unlink($temporary);
            throw new RuntimeException('The server could not save the update.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The server could not replace the private data file.');
        }
        @chmod($path, 0600);
        flock($lock, LOCK_UN);
    } finally {
        fclose($lock);
        @chmod($lockPath, 0600);
    }
}

/** Safely update a JSON array while holding an exclusive lock. */
function update_json_array(string $path, callable $updater): array
{
    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c+');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('The server could not lock private storage.');
    }
    try {
        $records = read_json_array($path);
        $updated = $updater($records);
        if (!is_array($updated)) throw new RuntimeException('The update returned invalid data.');
        $temporary = tempnam(dirname($path), '.org_');
        if ($temporary === false) throw new RuntimeException('The server could not create a temporary file.');
        $json = json_encode(array_values($updated), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($temporary, $json . "\n", LOCK_EX) === false) {
            @unlink($temporary);
            throw new RuntimeException('The server could not save the update.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The server could not replace the private data file.');
        }
        @chmod($path, 0600);
        return array_values($updated);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @chmod($lockPath, 0600);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('The form expired or failed its security check. Return to the page and try again.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function normalize_username(string $username): string
{
    return strtolower(trim($username));
}

function valid_username(string $username): bool
{
    return (bool)preg_match('/^[a-z0-9._-]{3,30}$/', $username);
}

function password_is_strong(string $password): bool
{
    if (strlen($password) < 12 || strlen($password) > 4096) return false;
    $common = ['password1234', '123456789012', 'qwertyuiop12', 'letmein123456', 'orgasmaphoria'];
    return !in_array(strtolower($password), $common, true);
}

function client_ip_address(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function request_user_agent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
}

function rate_bucket_definitions(string $scope, string $identifier, int $identifierLimit = 5, int $ipLimit = 25): array
{
    $normalized = strtolower(trim($identifier));
    return [
        ['key' => hash('sha256', $scope . '|identifier|' . $normalized), 'limit' => $identifierLimit],
        ['key' => hash('sha256', $scope . '|ip|' . client_ip_address()), 'limit' => $ipLimit],
    ];
}

function rate_limit_remaining(string $scope, string $identifier, int $identifierLimit = 5, int $ipLimit = 25): int
{
    $now = time();
    $keys = array_column(rate_bucket_definitions($scope, $identifier, $identifierLimit, $ipLimit), 'key');
    $remaining = 0;
    $records = read_json_array(LOGIN_ATTEMPTS_FILE);
    $clean = [];
    foreach ($records as $record) {
        $last = (int)($record['lastAttempt'] ?? 0);
        $lockUntil = (int)($record['lockUntil'] ?? 0);
        if ($last > 0 && $last < $now - 172800 && $lockUntil < $now) continue;
        $clean[] = $record;
        if (in_array((string)($record['key'] ?? ''), $keys, true) && $lockUntil > $now) {
            $remaining = max($remaining, $lockUntil - $now);
        }
    }
    if (count($clean) !== count($records)) {
        try { write_json_array(LOGIN_ATTEMPTS_FILE, $clean); } catch (Throwable) {}
    }
    return $remaining;
}

function record_rate_failure(string $scope, string $identifier, int $identifierLimit = 5, int $ipLimit = 25, int $lockSeconds = 900): int
{
    $now = time();
    $definitions = rate_bucket_definitions($scope, $identifier, $identifierLimit, $ipLimit);
    update_json_array(LOGIN_ATTEMPTS_FILE, static function (array $records) use ($definitions, $now, $lockSeconds): array {
        $byKey = [];
        foreach ($records as $index => $record) $byKey[(string)($record['key'] ?? '')] = $index;
        foreach ($definitions as $definition) {
            $key = (string)$definition['key'];
            $limit = (int)$definition['limit'];
            $index = $byKey[$key] ?? null;
            $record = $index === null ? ['key' => $key, 'failures' => 0, 'firstAttempt' => $now] : $records[$index];
            if ((int)($record['firstAttempt'] ?? 0) < $now - 900) {
                $record['failures'] = 0;
                $record['firstAttempt'] = $now;
                $record['lockUntil'] = 0;
            }
            $record['failures'] = (int)($record['failures'] ?? 0) + 1;
            $record['lastAttempt'] = $now;
            if ($record['failures'] >= $limit) {
                $record['lockUntil'] = $now + $lockSeconds;
                $record['failures'] = 0;
                $record['firstAttempt'] = $now;
            }
            if ($index === null) $records[] = $record; else $records[$index] = $record;
        }
        return $records;
    });
    return rate_limit_remaining($scope, $identifier, $identifierLimit, $ipLimit);
}

function clear_rate_failures(string $scope, string $identifier, int $identifierLimit = 5, int $ipLimit = 25): void
{
    $keys = array_column(rate_bucket_definitions($scope, $identifier, $identifierLimit, $ipLimit), 'key');
    try {
        update_json_array(LOGIN_ATTEMPTS_FILE, static fn(array $records): array => array_values(array_filter(
            $records,
            static fn(array $record): bool => !in_array((string)($record['key'] ?? ''), $keys, true)
        )));
    } catch (Throwable) {}
}

function audit_event(string $action, string $targetId = '', array $details = []): void
{
    try {
        $actor = $_SESSION['site_user'] ?? [];
        $record = [
            'id' => make_id('audit'),
            'at' => date(DATE_ATOM),
            'action' => $action,
            'actorId' => (string)($actor['id'] ?? ''),
            'actorName' => (string)($actor['displayName'] ?? 'System'),
            'targetId' => $targetId,
            'ipHash' => hash('sha256', client_ip_address()),
            'details' => $details,
        ];
        update_json_array(AUDIT_LOG_FILE, static function (array $records) use ($record): array {
            $records[] = $record;
            return count($records) > 2000 ? array_slice($records, -2000) : $records;
        });
    } catch (Throwable) {}
}

function setup_is_locked(): bool
{
    return is_file(SETUP_LOCK_FILE);
}

function lock_first_time_setup(): void
{
    if (!is_file(SETUP_LOCK_FILE)) {
        @file_put_contents(SETUP_LOCK_FILE, "Orgasmaphoria setup completed.\n", LOCK_EX);
        @chmod(SETUP_LOCK_FILE, 0600);
    }
}

function account_status(array $user): string
{
    return (string)($user['status'] ?? 'approved');
}

function account_is_approved(array $user): bool
{
    return account_status($user) === 'approved' && ($user['active'] ?? true) !== false;
}

function find_user_by_id(string $id): ?array
{
    foreach (read_json_array(USERS_FILE) as $user) {
        if (hash_equals((string)($user['id'] ?? ''), $id)) return $user;
    }
    return null;
}

function find_user_by_identifier(string $identifier): ?array
{
    $needle = strtolower(trim($identifier));
    foreach (read_json_array(USERS_FILE) as $user) {
        $email = strtolower((string)($user['email'] ?? ''));
        $username = strtolower((string)($user['username'] ?? ''));
        if (($email !== '' && hash_equals($email, $needle)) || ($username !== '' && hash_equals($username, $needle))) return $user;
    }
    return null;
}

function email_is_available(string $email, string $exceptUserId = ''): bool
{
    $needle = normalize_email($email);
    foreach (read_json_array(USERS_FILE) as $user) {
        if (($user['id'] ?? '') === $exceptUserId) continue;
        if ($needle !== '' && hash_equals(normalize_email((string)($user['email'] ?? '')), $needle)) return false;
    }
    return true;
}

function username_is_available(string $username, string $exceptUserId = ''): bool
{
    $needle = normalize_username($username);
    foreach (read_json_array(USERS_FILE) as $user) {
        if (($user['id'] ?? '') === $exceptUserId) continue;
        if ($needle !== '' && hash_equals(normalize_username((string)($user['username'] ?? '')), $needle)) return false;
    }
    return true;
}

function public_user(array $stored): array
{
    return [
        'id' => (string)$stored['id'],
        'username' => (string)($stored['username'] ?? ''),
        'email' => (string)($stored['email'] ?? ''),
        'displayName' => (string)($stored['displayName'] ?? 'Member'),
        'role' => (string)($stored['role'] ?? 'member'),
        'membershipTier' => (string)($stored['membershipTier'] ?? 'listener'),
        'permissions' => is_array($stored['permissions'] ?? null) ? $stored['permissions'] : [],
        'protectedAdmin' => !empty($stored['protectedAdmin']),
        'securityVersion' => max(1, (int)($stored['securityVersion'] ?? 1)),
        'twoFactorEnabled' => two_factor_is_enabled($stored),
    ];
}

function current_user(): ?array
{
    $sessionUser = $_SESSION['site_user'] ?? null;
    if (!is_array($sessionUser) || empty($sessionUser['id'])) return null;
    $stored = find_user_by_id((string)$sessionUser['id']);
    if (!$stored || !account_is_approved($stored)) {
        unset($_SESSION['site_user']);
        return null;
    }
    $storedVersion = max(1, (int)($stored['securityVersion'] ?? 1));
    $sessionVersion = max(1, (int)($sessionUser['securityVersion'] ?? 1));
    if ($storedVersion !== $sessionVersion) {
        unset($_SESSION['site_user']);
        return null;
    }
    $safe = public_user($stored);
    $_SESSION['site_user'] = $safe;
    return $safe;
}

function sign_in_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['site_user'] = public_user($user);
    $_SESSION['signed_in_at'] = time();
}

function sign_out_user(): void
{
    unset($_SESSION['site_user'], $_SESSION['signed_in_at']);
    two_factor_clear_session_state();
    session_regenerate_id(true);
}

function is_protected_admin_account(array $user): bool
{
    return !empty($user['protectedAdmin']) || (($user['role'] ?? '') === 'admin' && normalize_username((string)($user['username'] ?? '')) === 'administrator');
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function is_staff(): bool
{
    return in_array((string)(current_user()['role'] ?? ''), ['staff', 'admin'], true);
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if (!$user) return false;
    if (($user['role'] ?? '') === 'admin' || !empty($user['protectedAdmin'])) return true;
    if (($user['role'] ?? '') !== 'staff') return false;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return !empty($permissions[$permission]);
}

function require_login(string $loginPath = 'login.php'): void
{
    $user = current_user();
    if (!$user) {
        $_SESSION['return_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect($loginPath);
    }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $twoFactorPages = ['two-factor.php', 'two-factor-verify.php', 'two-factor-recovery.php', 'logout.php'];
    if (two_factor_is_enabled($user) && !two_factor_session_is_verified($user) && !in_array($script, $twoFactorPages, true)) {
        two_factor_begin_challenge($user, (string)($_SERVER['REQUEST_URI'] ?? 'index.php'));
        $verificationPath = str_contains($loginPath, '../account/') ? '../account/two-factor-verify.php' : 'two-factor-verify.php';
        redirect($verificationPath);
    }
}

function require_staff(string $loginPath = '../account/login.php'): void
{
    require_login($loginPath);
    if (!is_staff()) {
        http_response_code(403);
        exit('Staff access is required.');
    }
}

function require_permission(string $permission, string $loginPath = '../account/login.php'): void
{
    require_staff($loginPath);
    if (!has_permission($permission)) {
        http_response_code(403);
        exit('Your account does not have permission to use this feature.');
    }
}

function membership_level(string $tier): int
{
    return (int)(membership_levels()[$tier] ?? 1);
}

function user_can_access_resource(array $user, array $resource): bool
{
    if (in_array((string)($user['role'] ?? ''), ['staff', 'admin'], true)) return true;
    $access = (string)($resource['accessLevel'] ?? 'listener');
    if ($access === 'public' || $access === 'listener') return true;
    if ($access === 'staff') return false;
    if (str_starts_with($access, 'purchase:')) {
        $slug = substr($access, 9);
        return in_array($slug, is_array($user['entitlements'] ?? null) ? $user['entitlements'] : [], true);
    }
    return membership_level((string)($user['membershipTier'] ?? 'listener')) >= membership_level($access);
}

function update_user_record(string $userId, callable $updater): array
{
    $updatedUser = null;
    update_json_array(USERS_FILE, static function (array $users) use ($userId, $updater, &$updatedUser): array {
        foreach ($users as $index => $user) {
            if ((string)($user['id'] ?? '') !== $userId) continue;
            $candidate = $updater($user);
            if (!is_array($candidate)) throw new RuntimeException('The account update was invalid.');
            $users[$index] = $candidate;
            $updatedUser = $candidate;
            break;
        }
        if ($updatedUser === null) throw new RuntimeException('The account could not be found.');
        return $users;
    });
    return $updatedUser;
}

function create_member_account(string $displayName, string $username, string $email, string $password): array
{
    $displayName = trim($displayName);
    $username = normalize_username($username);
    $email = normalize_email($email);
    if ($displayName === '' || text_length($displayName) > 80) throw new InvalidArgumentException('Enter a display name using 80 characters or fewer.');
    if (!valid_username($username)) throw new InvalidArgumentException('Use 3–30 lowercase letters, numbers, dots, dashes, or underscores for the username.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new InvalidArgumentException('Enter a valid email address.');
    if (!email_is_available($email)) throw new InvalidArgumentException('An account already uses that email address.');
    if (!username_is_available($username)) throw new InvalidArgumentException('That username is already in use.');
    if (!password_is_strong($password)) throw new InvalidArgumentException('Use a password with at least 12 characters that is not commonly used.');

    $user = [
        'id' => make_id('user'),
        'username' => $username,
        'email' => $email,
        'displayName' => $displayName,
        'bio' => '',
        'interests' => [],
        'directoryVisibility' => 'members',
        'allowMessages' => 'members',
        'showOnline' => true,
        'role' => 'member',
        'permissions' => [],
        'membershipTier' => 'listener',
        'membershipStatus' => 'active',
        'membershipExpiresAt' => null,
        'entitlements' => [],
        'status' => 'approved',
        'active' => true,
        'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
        'securityVersion' => 1,
        'emailVerified' => false,
        'createdAt' => date(DATE_ATOM),
        'updatedAt' => date(DATE_ATOM),
    ];
    update_json_array(USERS_FILE, static function (array $users) use ($user): array {
        foreach ($users as $existing) {
            if (normalize_email((string)($existing['email'] ?? '')) === $user['email']) throw new RuntimeException('An account already uses that email address.');
            if (normalize_username((string)($existing['username'] ?? '')) === $user['username']) throw new RuntimeException('That username is already in use.');
        }
        $users[] = $user;
        return $users;
    });
    audit_event('account-created', (string)$user['id']);
    return $user;
}

function send_site_mail(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = trim((string)(getenv('ORG_CONTACT_FROM') ?: CONTACT_FROM));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . SITE_NAME . ' <' . $from . '>',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function create_password_reset(string $email): void
{
    $user = find_user_by_identifier($email);
    if (!$user || normalize_email((string)($user['email'] ?? '')) !== normalize_email($email)) return;
    $token = bin2hex(random_bytes(32));
    $record = [
        'id' => make_id('reset'),
        'userId' => (string)$user['id'],
        'tokenHash' => hash('sha256', $token),
        'expiresAt' => time() + PASSWORD_RESET_TTL,
        'usedAt' => null,
        'createdAt' => date(DATE_ATOM),
    ];
    update_json_array(PASSWORD_RESETS_FILE, static function (array $records) use ($record): array {
        $now = time();
        $records = array_values(array_filter($records, static fn(array $item): bool => (int)($item['expiresAt'] ?? 0) > $now && empty($item['usedAt'])));
        $records[] = $record;
        return $records;
    });
    $baseUrl = rtrim((string)(getenv('ORG_SITE_URL') ?: ''), '/');
    if ($baseUrl === '') {
        $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $link = $baseUrl . site_path('account/reset-password.php?token=' . rawurlencode($token));
    send_site_mail((string)$user['email'], 'Reset your Orgasmaphoria password', "A password reset was requested for your Orgasmaphoria account.\n\nUse this link within one hour:\n{$link}\n\nIf you did not request this, no action is needed.");
    audit_event('password-reset-requested', (string)$user['id']);
}

function consume_password_reset(string $token, string $password): bool
{
    if (!password_is_strong($password)) throw new InvalidArgumentException('Use a password with at least 12 characters that is not commonly used.');
    $hash = hash('sha256', trim($token));
    $matched = null;
    update_json_array(PASSWORD_RESETS_FILE, static function (array $records) use ($hash, &$matched): array {
        foreach ($records as $index => $record) {
            if (!empty($record['usedAt']) || (int)($record['expiresAt'] ?? 0) < time()) continue;
            if (hash_equals((string)($record['tokenHash'] ?? ''), $hash)) {
                $records[$index]['usedAt'] = date(DATE_ATOM);
                $matched = $record;
                break;
            }
        }
        return $records;
    });
    if (!$matched) return false;
    update_user_record((string)$matched['userId'], static function (array $user) use ($password): array {
        $user['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
        $user['securityVersion'] = max(1, (int)($user['securityVersion'] ?? 1)) + 1;
        $user['updatedAt'] = date(DATE_ATOM);
        return $user;
    });
    audit_event('password-reset-completed', (string)$matched['userId']);
    return true;
}

function save_contact_submission(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $email = normalize_email((string)($input['email'] ?? ''));
    $topic = trim((string)($input['topic'] ?? 'General inquiry'));
    $subject = trim((string)($input['subject'] ?? ''));
    $message = trim((string)($input['message'] ?? ''));
    if ($name === '' || text_length($name) > 100) throw new InvalidArgumentException('Enter your name using 100 characters or fewer.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
    if ($subject === '' || text_length($subject) > 150) throw new InvalidArgumentException('Enter a subject using 150 characters or fewer.');
    if (text_length($message) < 10 || text_length($message) > 5000) throw new InvalidArgumentException('Enter a message between 10 and 5,000 characters.');
    $allowedTopics = ['General inquiry','Membership','Account support','Order support','Accessibility request','Privacy question','Press or media','Licensing','Collaboration or guest feature','Events','Website problem'];
    if (!in_array($topic, $allowedTopics, true)) $topic = 'General inquiry';
    $record = [
        'id' => make_id('contact'),
        'name' => $name,
        'email' => $email,
        'topic' => $topic,
        'subject' => $subject,
        'message' => $message,
        'status' => 'new',
        'createdAt' => date(DATE_ATOM),
        'ipHash' => hash('sha256', client_ip_address()),
        'userAgent' => request_user_agent(),
    ];
    update_json_array(CONTACTS_FILE, static function (array $records) use ($record): array {
        $records[] = $record;
        return count($records) > 5000 ? array_slice($records, -5000) : $records;
    });
    $recipient = configured_contact_recipient();
    if ($recipient !== '') {
        $body = "Name: {$name}\nEmail: {$email}\nTopic: {$topic}\nSubject: {$subject}\n\n{$message}\n";
        send_site_mail($recipient, '[' . SITE_NAME . '] ' . $subject, $body, $email);
    }
    audit_event('contact-submitted', (string)$record['id'], ['topic' => $topic]);
    return $record;
}

function save_private_resource_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('The file upload failed.');
    if ((int)($file['size'] ?? 0) > MAX_RESOURCE_SIZE) throw new RuntimeException('The file exceeds the 25 MB upload limit.');
    $tmp = (string)($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new RuntimeException('The upload could not be verified.');
    $allowed = [
        'application/pdf' => 'pdf',
        'application/epub+zip' => 'epub',
        'application/zip' => 'zip',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    if (!isset($allowed[$mime])) throw new RuntimeException('That file type is not permitted.');
    $name = make_id('resource') . '.' . $allowed[$mime];
    $destination = RESOURCE_UPLOAD_DIR . '/' . $name;
    if (!move_uploaded_file($tmp, $destination)) throw new RuntimeException('The uploaded file could not be stored.');
    @chmod($destination, 0600);
    return $name;
}

require_once __DIR__ . '/two-factor.php';
