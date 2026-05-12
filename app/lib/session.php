<?php
declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name((string) env('SESSION_NAME', 'rs3_wayfinder_session'));
    session_set_cookie_params([
        'lifetime' => (int) env('SESSION_LIFETIME_SECONDS', 60 * 60 * 24 * 30),
        'path' => '/',
        'domain' => '',
        'secure' => (bool) env('SESSION_SECURE', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort_page(419, 'Invalid security token.');
    }
}

function verify_csrf_token(string $token): bool
{
    return $token !== '' && hash_equals(csrf_token(), $token);
}
