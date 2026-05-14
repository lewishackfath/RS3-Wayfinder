<?php
declare(strict_types=1);

function boss_log_icon_url(?string $url, string $fallbackName = ''): string
{
    $url = trim((string)$url);
    if ($url !== '') {
        return $url;
    }
    return '/assets/default-avatar.svg';
}

function boss_log_bosses_for_profile(int $profileId, array $filters = []): array
{
    $where = ["boss.type = 'boss'", 'boss.is_active = 1'];
    $params = [$profileId, $profileId];

    if (!empty($filters['q'])) {
        $where[] = '(boss.name LIKE ? OR drop_item.name LIKE ? OR boss.category LIKE ?)';
        $q = '%' . trim((string)$filters['q']) . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($filters['category'])) {
        $where[] = 'boss.category = ?';
        $params[] = (string)$filters['category'];
    }

    $sql = "SELECT
            boss.id AS boss_id,
            boss.name AS boss_name,
            boss.category AS boss_category,
            boss.icon_url AS boss_icon_url,
            bds.id AS source_id,
            bds.rarity,
            bds.quantity,
            bds.notes AS source_notes,
            bds.sort_order,
            drop_item.id AS drop_id,
            drop_item.name AS drop_name,
            drop_item.icon_url AS drop_icon_url,
            pbdl.is_obtained,
            pbdl.obtained_at,
            COALESCE(pbk.kill_count, 0) AS kill_count
        FROM content_items boss
        LEFT JOIN boss_drop_sources bds ON bds.boss_content_item_id = boss.id
        LEFT JOIN content_items drop_item ON drop_item.id = bds.drop_content_item_id AND drop_item.is_active = 1
        LEFT JOIN player_boss_drop_log pbdl
            ON pbdl.profile_id = ?
            AND pbdl.boss_content_item_id = boss.id
            AND pbdl.drop_content_item_id = drop_item.id
        LEFT JOIN player_boss_killcounts pbk
            ON pbk.profile_id = ?
            AND pbk.boss_content_item_id = boss.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY boss.name ASC, bds.sort_order ASC, drop_item.name ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $bosses = [];
    foreach ($rows as $row) {
        $bossId = (int)$row['boss_id'];
        if (!isset($bosses[$bossId])) {
            $bosses[$bossId] = [
                'id' => $bossId,
                'name' => (string)$row['boss_name'],
                'category' => (string)($row['boss_category'] ?? ''),
                'icon_url' => (string)($row['boss_icon_url'] ?? ''),
                'drops' => [],
                'obtained_count' => 0,
                'drop_count' => 0,
                'kill_count' => (int)($row['kill_count'] ?? 0),
            ];
        }

        if (!empty($row['drop_id'])) {
            $isObtained = (int)($row['is_obtained'] ?? 0) === 1;
            $bosses[$bossId]['drops'][] = [
                'source_id' => (int)$row['source_id'],
                'id' => (int)$row['drop_id'],
                'name' => (string)$row['drop_name'],
                'icon_url' => (string)($row['drop_icon_url'] ?? ''),
                'rarity' => (string)($row['rarity'] ?? ''),
                'quantity' => (string)($row['quantity'] ?? ''),
                'notes' => (string)($row['source_notes'] ?? ''),
                'is_obtained' => $isObtained,
                'obtained_at' => $row['obtained_at'] ?? null,
            ];
            $bosses[$bossId]['drop_count']++;
            if ($isObtained) {
                $bosses[$bossId]['obtained_count']++;
            }
        }
    }

    $completion = (string)($filters['completion'] ?? '');
    if ($completion !== '') {
        $bosses = array_filter($bosses, function (array $boss) use ($completion): bool {
            if ((int)$boss['drop_count'] === 0) {
                return $completion === 'no_drops';
            }
            if ($completion === 'complete') {
                return (int)$boss['obtained_count'] === (int)$boss['drop_count'];
            }
            if ($completion === 'incomplete') {
                return (int)$boss['obtained_count'] < (int)$boss['drop_count'];
            }
            if ($completion === 'started') {
                return (int)$boss['obtained_count'] > 0 && (int)$boss['obtained_count'] < (int)$boss['drop_count'];
            }
            return true;
        });
    }

    return array_values($bosses);
}

function boss_log_categories(): array
{
    $rows = db()->query("SELECT DISTINCT category FROM content_items WHERE type = 'boss' AND is_active = 1 AND category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
}

function boss_drop_source_exists(int $bossContentItemId, int $dropContentItemId): bool
{
    $stmt = db()->prepare('SELECT id FROM boss_drop_sources WHERE boss_content_item_id = ? AND drop_content_item_id = ? LIMIT 1');
    $stmt->execute([$bossContentItemId, $dropContentItemId]);
    return (bool)$stmt->fetchColumn();
}

function set_profile_boss_drop_obtained(int $profileId, int $userId, int $bossContentItemId, int $dropContentItemId, bool $obtained): void
{
    $profile = profile_for_user($profileId, $userId);
    if (!$profile) {
        throw new InvalidArgumentException('Profile not found.');
    }
    if (!boss_drop_source_exists($bossContentItemId, $dropContentItemId)) {
        throw new InvalidArgumentException('That drop is not linked to this boss.');
    }

    if ($obtained) {
        db()->prepare('INSERT INTO player_boss_drop_log (profile_id, boss_content_item_id, drop_content_item_id, is_obtained, obtained_at)
            VALUES (?, ?, ?, 1, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE is_obtained = 1, obtained_at = COALESCE(obtained_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP()')
            ->execute([$profileId, $bossContentItemId, $dropContentItemId]);
    } else {
        db()->prepare('UPDATE player_boss_drop_log SET is_obtained = 0, obtained_at = NULL, updated_at = UTC_TIMESTAMP()
            WHERE profile_id = ? AND boss_content_item_id = ? AND drop_content_item_id = ?')
            ->execute([$profileId, $bossContentItemId, $dropContentItemId]);
    }
}

function boss_log_totals_for_profile(int $profileId): array
{
    $stmt = db()->prepare("SELECT
            COUNT(DISTINCT boss.id) AS boss_count,
            COUNT(drop_item.id) AS drop_count,
            SUM(CASE WHEN pbdl.is_obtained = 1 THEN 1 ELSE 0 END) AS obtained_count,
            COALESCE((
                SELECT SUM(pbk2.kill_count)
                FROM player_boss_killcounts pbk2
                INNER JOIN content_items boss2 ON boss2.id = pbk2.boss_content_item_id
                WHERE pbk2.profile_id = ?
                  AND boss2.type = 'boss'
                  AND boss2.is_active = 1
            ), 0) AS total_kill_count
        FROM content_items boss
        LEFT JOIN boss_drop_sources bds ON bds.boss_content_item_id = boss.id
        LEFT JOIN content_items drop_item ON drop_item.id = bds.drop_content_item_id AND drop_item.is_active = 1
        LEFT JOIN player_boss_drop_log pbdl
            ON pbdl.profile_id = ?
            AND pbdl.boss_content_item_id = boss.id
            AND pbdl.drop_content_item_id = drop_item.id
        WHERE boss.type = 'boss' AND boss.is_active = 1");
    $stmt->execute([$profileId, $profileId]);
    $row = $stmt->fetch() ?: [];
    $dropCount = (int)($row['drop_count'] ?? 0);
    $obtained = (int)($row['obtained_count'] ?? 0);
    return [
        'boss_count' => (int)($row['boss_count'] ?? 0),
        'drop_count' => $dropCount,
        'obtained_count' => $obtained,
        'completion_pct' => $dropCount > 0 ? round(($obtained / $dropCount) * 100, 1) : 0,
        'total_kill_count' => (int)($row['total_kill_count'] ?? 0),
    ];
}


function set_profile_boss_killcount(int $profileId, int $userId, int $bossContentItemId, int $killCount): void
{
    $profile = profile_for_user($profileId, $userId);
    if (!$profile) {
        throw new InvalidArgumentException('Profile not found.');
    }
    if ($killCount < 0) {
        throw new InvalidArgumentException('Kill count cannot be negative.');
    }
    $boss = content_item_by_id($bossContentItemId);
    if (!$boss || ($boss['type'] ?? '') !== 'boss') {
        throw new InvalidArgumentException('Boss content item is invalid.');
    }

    db()->prepare('INSERT INTO player_boss_killcounts (profile_id, boss_content_item_id, kill_count)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE kill_count = VALUES(kill_count), updated_at = UTC_TIMESTAMP()')
        ->execute([$profileId, $bossContentItemId, $killCount]);
}

function profile_boss_killcount(int $profileId, int $bossContentItemId): int
{
    $stmt = db()->prepare('SELECT kill_count FROM player_boss_killcounts WHERE profile_id = ? AND boss_content_item_id = ? LIMIT 1');
    $stmt->execute([$profileId, $bossContentItemId]);
    return (int)($stmt->fetchColumn() ?: 0);
}
