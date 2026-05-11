<?php
declare(strict_types=1);

function normalise_rsn(string $rsn): string
{
    $rsn = trim($rsn);
    $rsn = preg_replace('/\s+/', ' ', $rsn) ?? $rsn;
    return strtolower(str_replace([' ', '_', '-'], '', $rsn));
}

function clean_rsn_display(string $rsn): string
{
    $rsn = trim($rsn);
    $rsn = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $rsn) ?? $rsn;
    $rsn = preg_replace('/[^0-9A-Za-z _-]/', '', $rsn) ?? $rsn;
    $rsn = preg_replace('/\s+/', ' ', $rsn) ?? $rsn;
    return substr(trim($rsn), 0, 12);
}

function account_type_options(): array
{
    return [
        'main' => 'Main',
        'ironman' => 'Ironman',
        'hardcore_ironman' => 'Hardcore Ironman',
        'group_ironman' => 'Group Ironman',
        'hardcore_group_ironman' => 'Hardcore Group Ironman',
        'skiller' => 'Skiller',
        'pure' => 'Pure',
        'other' => 'Other',
    ];
}

function visibility_options(): array
{
    return [
        'private' => 'Private',
        'unlisted' => 'Unlisted',
        'public' => 'Public',
    ];
}

function profiles_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM player_profiles WHERE user_id = ? ORDER BY is_primary DESC, rsn ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function profile_for_user(int $profileId, int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM player_profiles WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$profileId, $userId]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function profile_by_id(int $profileId): ?array
{
    $stmt = db()->prepare('SELECT pp.*, u.username, u.global_name, u.discord_id FROM player_profiles pp JOIN users u ON u.id = pp.user_id WHERE pp.id = ? LIMIT 1');
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function create_profile(int $userId, string $rsn, string $accountType, string $visibility): int
{
    $rsn = clean_rsn_display($rsn);
    if ($rsn === '') {
        throw new InvalidArgumentException('Please enter a valid RSN.');
    }

    $types = account_type_options();
    $visibilities = visibility_options();
    if (!isset($types[$accountType])) {
        $accountType = 'main';
    }
    if (!isset($visibilities[$visibility])) {
        $visibility = 'private';
    }

    $normalised = normalise_rsn($rsn);
    $pdo = db();

    $existing = $pdo->prepare('SELECT id FROM player_profiles WHERE user_id = ? AND rsn_normalised = ? LIMIT 1');
    $existing->execute([$userId, $normalised]);
    if ($existing->fetchColumn()) {
        throw new InvalidArgumentException('That RSN is already attached to your account.');
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM player_profiles WHERE user_id = ?');
    $countStmt->execute([$userId]);
    $isPrimary = ((int)$countStmt->fetchColumn() === 0) ? 1 : 0;

    $stmt = $pdo->prepare('INSERT INTO player_profiles (user_id, rsn, rsn_normalised, account_type, visibility, is_primary) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $rsn, $normalised, $accountType, $visibility, $isPrimary]);
    return (int)$pdo->lastInsertId();
}

function update_profile(int $profileId, int $userId, string $rsn, string $accountType, string $visibility, bool $isPrimary): void
{
    $rsn = clean_rsn_display($rsn);
    if ($rsn === '') {
        throw new InvalidArgumentException('Please enter a valid RSN.');
    }

    $types = account_type_options();
    $visibilities = visibility_options();
    if (!isset($types[$accountType])) {
        $accountType = 'main';
    }
    if (!isset($visibilities[$visibility])) {
        $visibility = 'private';
    }

    $normalised = normalise_rsn($rsn);
    $pdo = db();

    $existing = $pdo->prepare('SELECT id FROM player_profiles WHERE user_id = ? AND rsn_normalised = ? AND id <> ? LIMIT 1');
    $existing->execute([$userId, $normalised, $profileId]);
    if ($existing->fetchColumn()) {
        throw new InvalidArgumentException('That RSN is already attached to your account.');
    }

    $pdo->beginTransaction();
    try {
        if ($isPrimary) {
            $pdo->prepare('UPDATE player_profiles SET is_primary = 0 WHERE user_id = ?')->execute([$userId]);
        }
        $stmt = $pdo->prepare('UPDATE player_profiles SET rsn = ?, rsn_normalised = ?, account_type = ?, visibility = ?, is_primary = IF(?, 1, is_primary), updated_at = UTC_TIMESTAMP() WHERE id = ? AND user_id = ?');
        $stmt->execute([$rsn, $normalised, $accountType, $visibility, $isPrimary ? 1 : 0, $profileId, $userId]);
        ensure_user_has_primary_profile($userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_profile(int $profileId, int $userId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM player_profiles WHERE id = ? AND user_id = ?')->execute([$profileId, $userId]);
        ensure_user_has_primary_profile($userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function ensure_user_has_primary_profile(int $userId): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM player_profiles WHERE user_id = ? AND is_primary = 1');
    $stmt->execute([$userId]);
    if ((int)$stmt->fetchColumn() > 0) {
        return;
    }
    $first = $pdo->prepare('SELECT id FROM player_profiles WHERE user_id = ? ORDER BY created_at ASC LIMIT 1');
    $first->execute([$userId]);
    $profileId = $first->fetchColumn();
    if ($profileId) {
        $pdo->prepare('UPDATE player_profiles SET is_primary = 1 WHERE id = ?')->execute([(int)$profileId]);
    }
}

function all_profiles_admin(): array
{
    return db()->query("SELECT pp.*, u.username, u.global_name, u.discord_id
        FROM player_profiles pp
        JOIN users u ON u.id = pp.user_id
        ORDER BY pp.created_at DESC, pp.rsn ASC")->fetchAll();
}
