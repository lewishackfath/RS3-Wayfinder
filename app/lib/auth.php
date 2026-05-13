<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        restore_user_from_remember_cookie();
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if (is_array($cached) && (int)$cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }
    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 AND is_banned = 0 LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        // Older installs may not have the is_banned migration until setup/check.php or the next OAuth callback runs.
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([(int)$_SESSION['user_id']]);
    }
    $user = $stmt->fetch();
    $cached = $user ?: null;
    return $cached;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/auth/login.php');
    }
}

function login_user(int $userId, bool $remember = true): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    if ($remember) {
        issue_remember_cookie($userId);
    }
}

function logout_user(): void
{
    clear_remember_cookie();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}


function remember_cookie_name(): string
{
    return (string) env('REMEMBER_COOKIE_NAME', 'rs3_wayfinder_remember');
}

function remember_cookie_lifetime_seconds(): int
{
    return (int) env('REMEMBER_COOKIE_LIFETIME_SECONDS', 60 * 60 * 24 * 30);
}

function remember_cookie_options(?int $expires = null): array
{
    return [
        'expires' => $expires ?? (time() + remember_cookie_lifetime_seconds()),
        'path' => '/',
        'domain' => '',
        'secure' => (bool) env('SESSION_SECURE', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function issue_remember_cookie(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $expiresTs = time() + remember_cookie_lifetime_seconds();
    $expiresAt = gmdate('Y-m-d H:i:s', $expiresTs);

    db()->prepare('DELETE FROM user_remember_tokens WHERE user_id = ? AND expires_at < UTC_TIMESTAMP()')->execute([$userId]);
    db()->prepare('INSERT INTO user_remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, $hash, $expiresAt]);

    setcookie(remember_cookie_name(), $selector . ':' . $validator, remember_cookie_options($expiresTs));
}

function restore_user_from_remember_cookie(): void
{
    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) {
        return;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        clear_remember_cookie();
        return;
    }

    try {
        $stmt = db()->prepare("SELECT rt.*, u.is_active, u.is_banned
            FROM user_remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = ? AND rt.expires_at > UTC_TIMESTAMP()
            LIMIT 1");
        $stmt->execute([$selector]);
    } catch (Throwable $e) {
        $stmt = db()->prepare("SELECT rt.*, u.is_active
            FROM user_remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.selector = ? AND rt.expires_at > UTC_TIMESTAMP()
            LIMIT 1");
        $stmt->execute([$selector]);
    }
    $token = $stmt->fetch();

    if (!$token || (int)$token['is_active'] !== 1 || (int)($token['is_banned'] ?? 0) === 1) {
        clear_remember_cookie();
        return;
    }

    if (!hash_equals((string)$token['token_hash'], hash('sha256', $validator))) {
        db()->prepare('DELETE FROM user_remember_tokens WHERE selector = ?')->execute([$selector]);
        clear_remember_cookie();
        return;
    }

    $_SESSION['user_id'] = (int)$token['user_id'];
    db()->prepare('UPDATE user_remember_tokens SET last_used_at = UTC_TIMESTAMP() WHERE id = ?')->execute([(int)$token['id']]);

    // Rotate token after successful restore.
    db()->prepare('DELETE FROM user_remember_tokens WHERE id = ?')->execute([(int)$token['id']]);
    issue_remember_cookie((int)$token['user_id']);
}

function clear_remember_cookie(): void
{
    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (is_string($cookie) && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            try {
                db()->prepare('DELETE FROM user_remember_tokens WHERE selector = ?')->execute([$selector]);
            } catch (Throwable $ignored) {}
        }
    }

    setcookie(remember_cookie_name(), '', remember_cookie_options(time() - 3600));
    unset($_COOKIE[remember_cookie_name()]);
}


function upsert_discord_user(array $discordUser): int
{
    $discordId = (string)($discordUser['id'] ?? '');
    if ($discordId === '') {
        throw new RuntimeException('Discord did not return a user ID.');
    }

    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO users
        (discord_id, username, global_name, discriminator, avatar_hash, email, email_verified, last_login_at)
        VALUES (:discord_id, :username, :global_name, :discriminator, :avatar_hash, :email, :email_verified, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            global_name = VALUES(global_name),
            discriminator = VALUES(discriminator),
            avatar_hash = VALUES(avatar_hash),
            email = VALUES(email),
            email_verified = VALUES(email_verified),
            last_login_at = UTC_TIMESTAMP(),
            updated_at = UTC_TIMESTAMP()");
    $stmt->execute([
        ':discord_id' => $discordId,
        ':username' => (string)($discordUser['username'] ?? 'Unknown'),
        ':global_name' => $discordUser['global_name'] ?? null,
        ':discriminator' => $discordUser['discriminator'] ?? null,
        ':avatar_hash' => $discordUser['avatar'] ?? null,
        ':email' => $discordUser['email'] ?? null,
        ':email_verified' => !empty($discordUser['verified']) ? 1 : 0,
    ]);

    $select = $pdo->prepare("SELECT id FROM users WHERE discord_id = ? LIMIT 1");
    $select->execute([$discordId]);
    $userId = (int)$select->fetchColumn();

    ensure_default_roles($userId, $discordId);
    log_auth_event($userId, $discordId, true, null);
    return $userId;
}

function ensure_default_roles(int $userId, string $discordId): void
{
    $pdo = db();
    $adminIds = array_filter(array_map('trim', explode(',', (string) env('ADMIN_DISCORD_IDS', ''))));
    $roleSlug = in_array($discordId, $adminIds, true) ? 'owner' : 'member';

    $stmt = $pdo->prepare("SELECT r.id FROM roles r LEFT JOIN user_roles ur ON ur.role_id = r.id AND ur.user_id = ? WHERE r.slug = ? AND ur.user_id IS NULL LIMIT 1");
    $stmt->execute([$userId, $roleSlug]);
    $roleId = $stmt->fetchColumn();
    if ($roleId) {
        $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, (int)$roleId]);
    }
}

function log_auth_event(?int $userId, ?string $discordId, bool $success, ?string $reason): void
{
    $stmt = db()->prepare("INSERT INTO auth_login_events (user_id, discord_id, ip_address, user_agent, was_successful, failure_reason) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $discordId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $success ? 1 : 0,
        $reason,
    ]);
}
