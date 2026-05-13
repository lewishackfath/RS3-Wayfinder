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

function request_current_user_account_deletion(int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM user_remember_tokens WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('UPDATE users SET is_active = 0, deletion_requested_at = COALESCE(deletion_requested_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() WHERE id = ? AND deleted_at IS NULL')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Backwards-compatible wrapper for older call sites.
 * Account deletion is intentionally queued because full cascading deletes can be slow.
 */
function delete_current_user_account(int $userId): void
{
    request_current_user_account_deletion($userId);
}

function purge_queued_deleted_accounts(int $limit = 25, int $graceMinutes = 0): int
{
    $limit = max(1, min(200, $limit));
    $graceMinutes = max(0, $graceMinutes);
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM users WHERE deletion_requested_at IS NOT NULL AND deleted_at IS NULL AND deletion_requested_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? MINUTE) ORDER BY deletion_requested_at ASC LIMIT {$limit}");
    $stmt->execute([$graceMinutes]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $count = 0;
    foreach ($ids as $id) {
        $pdo->beginTransaction();
        try {
            // Mark first for audit safety, then delete the user. Existing FK cascades remove profiles/progress.
            $pdo->prepare('UPDATE users SET deleted_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $count++;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    return $count;
}
