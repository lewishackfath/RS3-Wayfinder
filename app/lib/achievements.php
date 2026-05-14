<?php
declare(strict_types=1);

function achievement_icon_url(?string $url): string
{
    $url = trim((string)$url);
    return $url !== '' ? $url : '/assets/branding/icon.png';
}

function achievement_categories(): array
{
    $rows = db()->query("SELECT DISTINCT category FROM content_items WHERE type = 'achievement' AND is_active = 1 AND category IS NOT NULL AND category <> '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);
    return array_map('strval', $rows ?: []);
}

function profile_completed_achievement_ids(int $profileId): array
{
    $stmt = db()->prepare('SELECT achievement_content_item_id FROM profile_achievement_progress WHERE profile_id = ? AND is_completed = 1');
    $stmt->execute([$profileId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function set_profile_achievement_completed(int $profileId, int $userId, int $achievementContentItemId, bool $completed): void
{
    $profile = profile_for_user($profileId, $userId);
    if (!$profile) {
        throw new InvalidArgumentException('Profile not found.');
    }
    $achievement = content_item_by_id($achievementContentItemId);
    if (!$achievement || ($achievement['type'] ?? '') !== 'achievement') {
        throw new InvalidArgumentException('Achievement content item is invalid.');
    }

    if ($completed) {
        db()->prepare("INSERT INTO profile_achievement_progress (profile_id, achievement_content_item_id, is_completed, completed_at, source)
            VALUES (?, ?, 1, UTC_TIMESTAMP(), 'manual')
            ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = COALESCE(completed_at, UTC_TIMESTAMP()), source = 'manual', updated_at = UTC_TIMESTAMP()")
            ->execute([$profileId, $achievementContentItemId]);
    } else {
        db()->prepare("INSERT INTO profile_achievement_progress (profile_id, achievement_content_item_id, is_completed, completed_at, source)
            VALUES (?, ?, 0, NULL, 'manual')
            ON DUPLICATE KEY UPDATE is_completed = 0, completed_at = NULL, source = 'manual', updated_at = UTC_TIMESTAMP()")
            ->execute([$profileId, $achievementContentItemId]);
    }
}

function profile_skill_level_map(int $profileId): array
{
    $map = [];
    foreach (latest_skills_for_profile($profileId) as $skill) {
        $name = strtolower(trim((string)($skill['skill_name'] ?? '')));
        if ($name === '') continue;
        $display = rs3_display_level((string)$skill['skill_name'], $skill['level'] ?? null, $skill['xp'] ?? null);
        $map[$name] = (int)($display['display_level'] ?? ($skill['level'] ?? 0));
    }
    return $map;
}

function profile_completed_quest_title_map(int $profileId): array
{
    $map = [];
    foreach (quests_for_profile($profileId) as $quest) {
        $status = strtolower((string)($quest['status'] ?? ''));
        if (str_contains($status, 'complete')) {
            $map[strtolower(trim((string)$quest['quest_title']))] = true;
        }
    }
    return $map;
}

function achievement_requirement_state(int $profileId, int $achievementContentItemId): array
{
    $skillMap = profile_skill_level_map($profileId);
    $questMap = profile_completed_quest_title_map($profileId);
    $completedAchievements = array_flip(profile_completed_achievement_ids($profileId));

    $skillReqs = content_skill_requirements($achievementContentItemId);
    $questReqs = content_quest_requirements($achievementContentItemId);
    $achievementReqs = content_achievement_requirements($achievementContentItemId);

    $blockedBy = [];
    $metCount = 0;
    $totalCount = 0;

    foreach ($skillReqs as $req) {
        $totalCount++;
        $skillName = (string)$req['skill_name'];
        $required = (int)$req['required_level'];
        $current = $skillMap[strtolower(trim($skillName))] ?? 0;
        if ($current >= $required) {
            $metCount++;
        } else {
            $blockedBy[] = $skillName . ' ' . $required . ' (' . max(0, $current) . ')';
        }
    }

    foreach ($questReqs as $req) {
        $totalCount++;
        $name = (string)$req['required_name'];
        if (isset($questMap[strtolower(trim($name))])) {
            $metCount++;
        } else {
            $blockedBy[] = 'Quest: ' . $name;
        }
    }

    foreach ($achievementReqs as $req) {
        $totalCount++;
        $id = (int)$req['required_content_item_id'];
        $name = (string)$req['required_name'];
        if (isset($completedAchievements[$id])) {
            $metCount++;
        } else {
            $blockedBy[] = 'Achievement: ' . $name;
        }
    }

    return [
        'is_available' => $totalCount === 0 || $metCount === $totalCount,
        'met_count' => $metCount,
        'total_count' => $totalCount,
        'blocked_by' => $blockedBy,
        'skill_requirements' => $skillReqs,
        'quest_requirements' => $questReqs,
        'achievement_requirements' => $achievementReqs,
    ];
}

function achievements_for_profile(int $profileId, array $filters = []): array
{
    $where = ["ci.type = 'achievement'", 'ci.is_active = 1'];
    $params = [$profileId];

    if (!empty($filters['q'])) {
        $where[] = '(ci.name LIKE ? OR ci.description LIKE ? OR ci.category LIKE ?)';
        $q = '%' . trim((string)$filters['q']) . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    if (!empty($filters['category'])) {
        $where[] = 'ci.category = ?';
        $params[] = (string)$filters['category'];
    }

    $sql = "SELECT ci.*, pap.is_completed, pap.completed_at, pap.source
        FROM content_items ci
        LEFT JOIN profile_achievement_progress pap
            ON pap.profile_id = ?
            AND pap.achievement_content_item_id = ci.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ci.category ASC, ci.name ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $statusFilter = (string)($filters['status'] ?? '');
    $result = [];
    foreach ($rows as $row) {
        $state = achievement_requirement_state($profileId, (int)$row['id']);
        $completed = (int)($row['is_completed'] ?? 0) === 1;
        $status = $completed ? 'completed' : ($state['is_available'] ? 'available' : 'blocked');
        if ($statusFilter !== '' && $statusFilter !== $status) {
            continue;
        }
        $row['is_completed'] = $completed;
        $row['availability_status'] = $status;
        $row['requirements'] = $state;
        $row['metadata'] = content_metadata($row);
        $result[] = $row;
    }

    return $result;
}

function achievement_totals_for_profile(int $profileId): array
{
    $all = achievements_for_profile($profileId, []);
    $totals = ['total' => count($all), 'completed' => 0, 'available' => 0, 'blocked' => 0];
    foreach ($all as $achievement) {
        $totals[$achievement['availability_status']]++;
    }
    $totals['completion_pct'] = $totals['total'] > 0 ? round(($totals['completed'] / $totals['total']) * 100, 1) : 0;
    return $totals;
}
