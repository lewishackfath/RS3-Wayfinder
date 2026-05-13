<?php
declare(strict_types=1);

function user_display_name(array $user): string
{
    $nickname = trim((string)($user['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }
    $global = trim((string)($user['global_name'] ?? ''));
    if ($global !== '') {
        return $global;
    }
    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? $username : 'User #' . (int)($user['id'] ?? 0);
}

function discord_avatar_url_for_user(array $user): string
{
    $discordId = (string)($user['discord_id'] ?? '');
    $avatarHash = (string)($user['avatar_hash'] ?? '');
    if ($discordId !== '' && $avatarHash !== '') {
        $ext = str_starts_with($avatarHash, 'a_') ? 'gif' : 'png';
        return 'https://cdn.discordapp.com/avatars/' . rawurlencode($discordId) . '/' . rawurlencode($avatarHash) . '.' . $ext . '?size=128';
    }
    return '/assets/default-avatar.svg';
}

function update_account_nickname(int $userId, string $nickname): void
{
    $nickname = trim(preg_replace('/\s+/', ' ', $nickname) ?? $nickname);
    if (mb_strlen($nickname) > 100) {
        $nickname = mb_substr($nickname, 0, 100);
    }
    db()->prepare('UPDATE users SET nickname = NULLIF(?, \'\'), updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$nickname, $userId]);
}

function delete_current_user_account(int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
