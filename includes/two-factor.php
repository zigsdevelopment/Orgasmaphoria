<?php
/**
 * ORGASMAPHORIA TWO-FACTOR AUTHENTICATION
 * ---------------------------------------
 * Implements RFC 6238-compatible time-based one-time passwords (TOTP) for
 * authenticator apps. Secrets are encrypted at rest with a site-specific key
 * stored in the private-data directory, and recovery codes are stored only as
 * one-way password hashes.
 *
 * Routine site maintainers should not edit this file.
 */
declare(strict_types=1);

/** Return true when an account has a complete two-factor enrollment. */
function two_factor_is_enabled(array $user): bool
{
    // Full stored records contain the encrypted secret; safe session records
    // intentionally contain only the boolean marker.
    return !empty($user['twoFactorEnabled'])
        || (!empty($user['twoFactorSecret']) && !empty($user['twoFactorEnabledAt']));
}

/** Two-factor authentication is optional for every account. */
function two_factor_is_required(array $user): bool
{
    return false;
}

/** Encode random bytes with the unpadded Base32 alphabet used by TOTP apps. */
function two_factor_base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (unpack('C*', $bytes) ?: [] as $byte) {
        $bits .= str_pad(decbin((int)$byte), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    for ($offset = 0, $length = strlen($bits); $offset < $length; $offset += 5) {
        $chunk = substr($bits, $offset, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

/** Decode an unpadded Base32 TOTP secret into raw bytes. */
function two_factor_base32_decode(string $encoded): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper((string)preg_replace('/[^A-Z2-7]/i', '', $encoded));
    if ($clean === '') return '';
    $bits = '';
    foreach (str_split($clean) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) return '';
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }
    $decoded = '';
    for ($offset = 0, $length = strlen($bits); $offset + 8 <= $length; $offset += 8) {
        $decoded .= chr(bindec(substr($bits, $offset, 8)));
    }
    return $decoded;
}

/** Generate a 160-bit TOTP secret suitable for SHA-1 authenticator apps. */
function two_factor_generate_secret(): string
{
    return two_factor_base32_encode(random_bytes(20));
}

/** Create or read the 32-byte encryption key stored with private site data. */
function two_factor_encryption_key(): string
{
    if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
        throw new RuntimeException('The server OpenSSL extension is required for two-factor authentication.');
    }
    if (is_file(TWO_FACTOR_KEY_FILE)) {
        $key = @file_get_contents(TWO_FACTOR_KEY_FILE);
        if (is_string($key) && strlen($key) === 32) return $key;
        throw new RuntimeException('The private two-factor encryption key is invalid.');
    }
    $directory = dirname(TWO_FACTOR_KEY_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
        throw new RuntimeException('The private two-factor key directory could not be created.');
    }
    @chmod($directory, 0700);
    $key = random_bytes(32);
    if (@file_put_contents(TWO_FACTOR_KEY_FILE, $key, LOCK_EX) === false) {
        throw new RuntimeException('The private two-factor encryption key could not be saved.');
    }
    @chmod(TWO_FACTOR_KEY_FILE, 0600);
    return $key;
}

/** Encrypt a TOTP secret with AES-256-GCM before saving it in users.json. */
function two_factor_encrypt_secret(string $secret): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $secret,
        'aes-256-gcm',
        two_factor_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        SITE_NAME,
        16
    );
    if (!is_string($ciphertext) || strlen($tag) !== 16) {
        throw new RuntimeException('The authenticator secret could not be encrypted.');
    }
    return 'v1.' . base64_encode($iv . $tag . $ciphertext);
}

/** Decrypt an enrolled TOTP secret for code verification. */
function two_factor_decrypt_secret(string $stored): string
{
    if (!str_starts_with($stored, 'v1.')) return '';
    $raw = base64_decode(substr($stored, 3), true);
    if (!is_string($raw) || strlen($raw) < 29) return '';
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        two_factor_encryption_key(),
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        SITE_NAME
    );
    return is_string($plaintext) ? $plaintext : '';
}

/** Build the 6-digit TOTP code for one 30-second counter. */
function two_factor_totp_for_counter(string $secret, int $counter): string
{
    $key = two_factor_base32_decode($secret);
    if ($key === '') return '';
    $high = intdiv($counter, 4294967296);
    $low = $counter % 4294967296;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Return the matching counter within a one-step clock-skew window. */
function two_factor_matching_counter(string $secret, string $code, ?int $timestamp = null): ?int
{
    $clean = (string)preg_replace('/\D+/', '', $code);
    if (strlen($clean) !== 6) return null;
    $counter = intdiv($timestamp ?? time(), 30);
    foreach ([-1, 0, 1] as $offset) {
        $candidateCounter = $counter + $offset;
        if ($candidateCounter >= 0 && hash_equals(two_factor_totp_for_counter($secret, $candidateCounter), $clean)) {
            return $candidateCounter;
        }
    }
    return null;
}

/** Format a recovery code consistently before hashing or checking it. */
function two_factor_normalize_recovery_code(string $code): string
{
    return strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', $code));
}

/** Create one readable recovery code without ambiguous 0/O or 1/I symbols. */
function two_factor_generate_recovery_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $plain = '';
    for ($index = 0; $index < 16; $index++) {
        $plain .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return implode('-', str_split($plain, 4));
}

/** Generate one-time recovery codes and the password hashes saved on the account. */
function two_factor_generate_recovery_codes(int $count = 8): array
{
    $plain = [];
    $hashes = [];
    while (count($plain) < $count) {
        $code = two_factor_generate_recovery_code();
        if (in_array($code, $plain, true)) continue;
        $plain[] = $code;
        $hashes[] = password_hash(two_factor_normalize_recovery_code($code), PASSWORD_DEFAULT);
    }
    return ['plain' => $plain, 'hashes' => $hashes];
}

/** Build the local otpauth enrollment URI scanned by authenticator apps. */
function two_factor_otpauth_uri(array $user, string $secret): string
{
    $identifier = trim((string)($user['email'] ?? ''));
    if ($identifier === '') $identifier = trim((string)($user['username'] ?? 'Account'));
    $label = TWO_FACTOR_ISSUER . ':' . $identifier;
    return 'otpauth://totp/' . rawurlencode($label)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode(TWO_FACTOR_ISSUER)
        . '&algorithm=SHA1&digits=6&period=30';
}

/** Update one user record safely and return the saved account. */
function two_factor_update_user(string $userId, callable $updater): array
{
    return update_user_record($userId, $updater);
}

/** Verify a TOTP or recovery code and reject reuse of an already accepted TOTP. */
function two_factor_verify_user_code(array $user, string $code): array
{
    $userId = (string)($user['id'] ?? '');
    if ($userId === '' || !two_factor_is_enabled($user)) return ['ok' => false, 'recovery' => false, 'user' => $user];

    $normalizedRecovery = two_factor_normalize_recovery_code($code);
    if (strlen($normalizedRecovery) >= 12) {
        $hashes = is_array($user['twoFactorRecoveryCodes'] ?? null) ? $user['twoFactorRecoveryCodes'] : [];
        foreach ($hashes as $index => $hash) {
            if (!is_string($hash) || !password_verify($normalizedRecovery, $hash)) continue;
            $saved = two_factor_update_user($userId, static function (array $stored) use ($index): array {
                $codes = is_array($stored['twoFactorRecoveryCodes'] ?? null) ? array_values($stored['twoFactorRecoveryCodes']) : [];
                if (isset($codes[$index])) array_splice($codes, $index, 1);
                $stored['twoFactorRecoveryCodes'] = $codes;
                $stored['twoFactorLastUsedAt'] = date(DATE_ATOM);
                $stored['updatedAt'] = date(DATE_ATOM);
                return $stored;
            });
            audit_event('two-factor-recovery-code-used', $userId, ['remaining' => count($saved['twoFactorRecoveryCodes'] ?? [])]);
            return ['ok' => true, 'recovery' => true, 'user' => $saved];
        }
    }

    $secret = two_factor_decrypt_secret((string)($user['twoFactorSecret'] ?? ''));
    if ($secret === '') return ['ok' => false, 'recovery' => false, 'user' => $user];
    $counter = two_factor_matching_counter($secret, $code);
    $lastCounter = (int)($user['twoFactorLastCounter'] ?? -1);
    if ($counter === null || $counter <= $lastCounter) {
        return ['ok' => false, 'recovery' => false, 'user' => $user];
    }
    $saved = two_factor_update_user($userId, static function (array $stored) use ($counter): array {
        $storedLast = (int)($stored['twoFactorLastCounter'] ?? -1);
        if ($counter <= $storedLast) throw new RuntimeException('This authenticator code has already been used. Wait for a new code.');
        $stored['twoFactorLastCounter'] = $counter;
        $stored['twoFactorLastUsedAt'] = date(DATE_ATOM);
        $stored['updatedAt'] = date(DATE_ATOM);
        return $stored;
    });
    return ['ok' => true, 'recovery' => false, 'user' => $saved];
}

/** Start a short-lived second-factor challenge after password verification. */
function two_factor_begin_challenge(array $user, string $returnPath = ''): void
{
    $_SESSION['pending_two_factor'] = [
        'userId' => (string)($user['id'] ?? ''),
        'securityVersion' => max(1, (int)($user['securityVersion'] ?? 1)),
        'returnPath' => safe_return_path($returnPath),
        'expiresAt' => time() + 600,
        'challengeId' => bin2hex(random_bytes(16)),
    ];
}

/** Return the pending challenge and live user, or null when it has expired. */
function two_factor_pending_challenge(): ?array
{
    $pending = $_SESSION['pending_two_factor'] ?? null;
    if (!is_array($pending) || empty($pending['userId']) || (int)($pending['expiresAt'] ?? 0) < time()) {
        unset($_SESSION['pending_two_factor']);
        return null;
    }
    $user = find_user_by_id((string)$pending['userId']);
    if (!$user || !account_is_approved($user) || !two_factor_is_enabled($user)) {
        unset($_SESSION['pending_two_factor']);
        return null;
    }
    if (max(1, (int)($user['securityVersion'] ?? 1)) !== max(1, (int)($pending['securityVersion'] ?? 1))) {
        unset($_SESSION['pending_two_factor']);
        return null;
    }
    return ['pending' => $pending, 'user' => $user];
}

/** Prevent an externally supplied URL from being used as a login redirect. */
function two_factor_safe_return_path(string $path): string
{
    return safe_return_path($path);
}

/** Mark this browser session as having completed the second factor. */
function two_factor_mark_session_verified(array $user): void
{
    $_SESSION['two_factor_verified'] = [
        'userId' => (string)($user['id'] ?? ''),
        'securityVersion' => max(1, (int)($user['securityVersion'] ?? 1)),
        'verifiedAt' => time(),
    ];
}

/** Confirm that the current session completed 2FA for this exact account version. */
function two_factor_session_is_verified(array $user): bool
{
    if (!two_factor_is_enabled($user)) return false;
    $verified = $_SESSION['two_factor_verified'] ?? null;
    if (!is_array($verified)) return false;
    if (!hash_equals((string)($user['id'] ?? ''), (string)($verified['userId'] ?? ''))) return false;
    if (max(1, (int)($user['securityVersion'] ?? 1)) !== max(1, (int)($verified['securityVersion'] ?? 1))) return false;
    return (int)($verified['verifiedAt'] ?? 0) >= time() - TWO_FACTOR_SESSION_TTL;
}

/** Finish a successful pending challenge and return its safe destination. */
function two_factor_complete_challenge(array $user, array $pending): string
{
    sign_in_user($user);
    two_factor_mark_session_verified($user);
    unset($_SESSION['pending_two_factor']);
    session_regenerate_id(true);
    return two_factor_safe_return_path((string)($pending['returnPath'] ?? ''));
}

/** Clear challenge/session markers during sign-out or a cancelled verification. */
function two_factor_clear_session_state(): void
{
    unset($_SESSION['pending_two_factor'], $_SESSION['two_factor_verified'], $_SESSION['two_factor_setup']);
}

/** Create or refresh the temporary secret displayed during enrollment. */
function two_factor_setup_state(array $user, bool $replace = false): array
{
    $state = $_SESSION['two_factor_setup'] ?? null;
    if (!is_array($state)
        || (string)($state['userId'] ?? '') !== (string)($user['id'] ?? '')
        || (int)($state['expiresAt'] ?? 0) < time()
        || (bool)($state['replace'] ?? false) !== $replace
    ) {
        $state = [
            'userId' => (string)$user['id'],
            'secret' => two_factor_generate_secret(),
            'expiresAt' => time() + 900,
            'replace' => $replace,
        ];
        $_SESSION['two_factor_setup'] = $state;
    }
    return $state;
}

/** Enable or replace TOTP and return the one-time plaintext recovery codes. */
function two_factor_enroll_user(array $user, string $secret, int $counter): array
{
    $generated = two_factor_generate_recovery_codes(TWO_FACTOR_RECOVERY_CODE_COUNT);
    $saved = two_factor_update_user((string)$user['id'], static function (array $stored) use ($secret, $counter, $generated): array {
        $stored['twoFactorSecret'] = two_factor_encrypt_secret($secret);
        $stored['twoFactorRecoveryCodes'] = $generated['hashes'];
        $stored['twoFactorLastCounter'] = $counter;
        $stored['twoFactorEnabledAt'] = date(DATE_ATOM);
        $stored['twoFactorLastUsedAt'] = date(DATE_ATOM);
        $stored['securityVersion'] = max(1, (int)($stored['securityVersion'] ?? 1)) + 1;
        $stored['updatedAt'] = date(DATE_ATOM);
        return $stored;
    });
    unset($_SESSION['two_factor_setup']);
    audit_event('two-factor-enabled', (string)$saved['id'], ['role' => (string)($saved['role'] ?? '')]);
    return ['user' => $saved, 'codes' => $generated['plain']];
}

/** Replace recovery codes while retaining the current authenticator secret. */
function two_factor_replace_recovery_codes(array $user): array
{
    $generated = two_factor_generate_recovery_codes(TWO_FACTOR_RECOVERY_CODE_COUNT);
    $saved = two_factor_update_user((string)$user['id'], static function (array $stored) use ($generated): array {
        $stored['twoFactorRecoveryCodes'] = $generated['hashes'];
        $stored['updatedAt'] = date(DATE_ATOM);
        return $stored;
    });
    audit_event('two-factor-recovery-codes-regenerated', (string)$saved['id']);
    return ['user' => $saved, 'codes' => $generated['plain']];
}

/** Disable optional 2FA and invalidate existing sessions. */
function two_factor_disable_user(array $user): array
{
    $saved = two_factor_update_user((string)$user['id'], static function (array $stored): array {
        unset(
            $stored['twoFactorSecret'],
            $stored['twoFactorRecoveryCodes'],
            $stored['twoFactorLastCounter'],
            $stored['twoFactorEnabledAt'],
            $stored['twoFactorLastUsedAt']
        );
        $stored['securityVersion'] = max(1, (int)($stored['securityVersion'] ?? 1)) + 1;
        $stored['updatedAt'] = date(DATE_ATOM);
        return $stored;
    });
    audit_event('two-factor-disabled', (string)$saved['id']);
    return $saved;
}

/** Administrative reset for an ordinary account that lost both app and codes. */
function two_factor_admin_reset(array $target, array $actor): array
{
    if (is_protected_admin_account($target)) {
        throw new RuntimeException('The protected Administrator two-factor enrollment cannot be reset from the Accounts page.');
    }
    $saved = two_factor_update_user((string)$target['id'], static function (array $stored): array {
        unset(
            $stored['twoFactorSecret'],
            $stored['twoFactorRecoveryCodes'],
            $stored['twoFactorLastCounter'],
            $stored['twoFactorEnabledAt'],
            $stored['twoFactorLastUsedAt']
        );
        $stored['securityVersion'] = max(1, (int)($stored['securityVersion'] ?? 1)) + 1;
        $stored['updatedAt'] = date(DATE_ATOM);
        return $stored;
    });
    audit_event('two-factor-reset-by-staff', (string)$saved['id'], ['by' => (string)($actor['displayName'] ?? 'Staff User')]);
    return $saved;
}
