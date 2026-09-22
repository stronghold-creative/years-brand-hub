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

function attempt_sign_in(string $email, string $password): bool
{
    $user = db_one('SELECT id, password_hash FROM users WHERE email = ?', [strtolower(trim($email))]);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
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
