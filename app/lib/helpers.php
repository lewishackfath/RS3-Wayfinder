<?php
declare(strict_types=1);

function app_url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : app_url($path)));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_debug(): bool
{
    return (bool) env('APP_DEBUG', false);
}

function abort_page(int $status, string $message): never
{
    http_response_code($status);
    echo '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
    exit;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        abort_page(405, 'Method not allowed.');
    }
}
