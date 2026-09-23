<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_in(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $sent)) {
        http_response_code(419);
        exit('Your session expired. Reload the page and try again.');
    }
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'page';
}

function not_found(): never
{
    http_response_code(404);
    view('404', ['title' => 'Page not found']);
    exit;
}

/** Truncate safely whether or not mbstring is available on the host. */
function str_trim_to(string $text, int $limit): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit);
    }
    return substr($text, 0, $limit);
}

/**
 * A URL for a file in public_html, carrying its modification time, so browsers
 * fetch the new copy the moment it is deployed rather than serving a cached one.
 */
function asset_url(string $path): string
{
    $file = ROOT_DIR . '/public_html' . $path;
    $stamp = is_file($file) ? filemtime($file) : time();
    return $path . '?v=' . $stamp;
}

/** First character of a name, uppercased, without needing mbstring. */
function str_initial(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '?';
    }
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($text, 0, 1));
    }
    return strtoupper(substr($text, 0, 1));
}
