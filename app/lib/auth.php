<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cached = null;
    if (is_array($cached) && (int)$cached['id'] === (int)$_SESSION['user_id']) {
        return $cached;
    }
    $stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
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

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
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
