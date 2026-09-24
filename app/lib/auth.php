<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if ($cached === null) {
        $cached = db_one('SELECT id, email, name, role FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }
    return $cached ?: null;
}

/**
 * How many failures are allowed before sign-in is refused, and for how long.
 * Counted per email and per address, so one person cannot lock out another by
 * hammering their email, and an attacker cannot spread attempts across emails.
 */
function sign_in_max_attempts(): int
{
    return 8;
}

function sign_in_window_seconds(): int
{
    return 900;   // fifteen minutes
}

function sign_in_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = $ip !== '' ? @inet_pton($ip) : false;
    return $packed === false ? null : $packed;
}

/** Seconds to wait before another attempt is allowed. Zero means go ahead. */
function sign_in_locked_for(string $email): int
{
    try {
        $row = db_one(
            'SELECT COUNT(*) AS n, MAX(at) AS last FROM sign_in_attempts
              WHERE at > DATE_SUB(NOW(), INTERVAL ? SECOND) AND (email = ? OR ip = ?)',
            [sign_in_window_seconds(), strtolower(trim($email)), sign_in_client_ip()]
        );
    } catch (Throwable $e) {
        // The table may not exist yet. Never lock people out because of that,
        // but do say so, since it means throttling is not in effect.
        error_log('sign-in throttling unavailable: ' . $e->getMessage());
        return 0;
    }

    if ((int) ($row['n'] ?? 0) < sign_in_max_attempts()) {
        return 0;
    }
    $since = time() - (int) strtotime((string) $row['last']);
    return max(0, sign_in_window_seconds() - $since);
}

function sign_in_record_failure(string $email): void
{
    try {
        db_run('INSERT INTO sign_in_attempts (email, ip) VALUES (?, ?)',
            [strtolower(trim($email)), sign_in_client_ip()]);
        // Opportunistic tidy-up, so the table cannot grow without limit.
        if (random_int(1, 50) === 1) {
            db_run('DELETE FROM sign_in_attempts WHERE at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        }
    } catch (Throwable $e) {
        error_log('sign-in attempt not recorded: ' . $e->getMessage());
    }
}

function sign_in_clear(string $email): void
{
    try {
        db_run('DELETE FROM sign_in_attempts WHERE email = ? OR ip = ?',
            [strtolower(trim($email)), sign_in_client_ip()]);
    } catch (Throwable $e) {
        // Not worth failing a successful sign-in over.
        error_log('could not clear sign-in attempts: ' . $e->getMessage());
    }
}

function attempt_sign_in(string $email, string $password): bool
{
    $user = db_one('SELECT id, password_hash FROM users WHERE email = ?', [strtolower(trim($email))]);

    // Always spend roughly the same time whether or not the account exists, so
    // the response cannot be used to work out which emails are registered.
    $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringfoobarbazqux0000000000000000000000000000';
    $ok = password_verify($password, $hash) && $user;

    if (!$ok) {
        sign_in_record_failure($email);
        return false;
    }

    sign_in_clear($email);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function sign_out(): void
{
    $_SESSION = [];
    session_destroy();
}

function can_edit(): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], ['admin', 'editor'], true);
}

function require_editor(): void
{
    if (!can_edit()) {
        redirect('/admin/sign-in');
    }
}

function require_admin(): void
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have access to this area.');
    }
}
