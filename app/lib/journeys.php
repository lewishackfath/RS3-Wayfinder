<?php
declare(strict_types=1);

function journey_completion_modes(): array
{
    return [
        'auto_only' => 'Automatic only',
        'manual_only' => 'Manual only',
        'auto_or_manual' => 'Automatic or manual',
    ];
}

function journey_auto_rule_types(): array
{
    return [
        '' => 'No automatic rule',
        'skill_level' => 'Skill level',
        'quest_complete' => 'Quest complete',
    ];
}

function journey_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'journey';
}

function journey_unique_slug(string $base, ?int $ignoreJourneyId = null): string
{
    $base = journey_slugify($base);
    $slug = $base;
    $i = 2;
    $pdo = db();

    while (true) {
        $sql = 'SELECT id FROM journeys WHERE slug = ?';
        $params = [$slug];
        if ($ignoreJourneyId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreJourneyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function all_journeys(bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM journeys';
    if ($publishedOnly) {
        $sql .= ' WHERE is_published = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    return db()->query($sql)->fetchAll();
}

function journey_by_id(int $journeyId): ?array
{
    $stmt = db()->prepare('SELECT * FROM journeys WHERE id = ? LIMIT 1');
    $stmt->execute([$journeyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function journey_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM journeys WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function chapters_for_journey(int $journeyId): array
{
    $stmt = db()->prepare('SELECT * FROM journey_chapters WHERE journey_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$journeyId]);
    return $stmt->fetchAll();
}

function chapter_by_id(int $chapterId): ?array
{
    $stmt = db()->prepare('SELECT jc.*, j.name AS journey_name FROM journey_chapters jc JOIN journeys j ON j.id = jc.journey_id WHERE jc.id = ? LIMIT 1');
    $stmt->execute([$chapterId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function steps_for_chapter(int $chapterId): array
{
    $stmt = db()->prepare('SELECT * FROM journey_steps WHERE chapter_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$chapterId]);
    return $stmt->fetchAll();
}

function steps_for_journey(int $journeyId): array
{
    $stmt = db()->prepare('SELECT js.*, jc.title AS chapter_title, jc.sort_order AS chapter_sort_order
        FROM journey_steps js
        JOIN journey_chapters jc ON jc.id = js.chapter_id
        WHERE jc.journey_id = ?
        ORDER BY jc.sort_order ASC, jc.id ASC, js.sort_order ASC, js.id ASC');
    $stmt->execute([$journeyId]);
    return $stmt->fetchAll();
}

function step_by_id(int $stepId): ?array
{
    $stmt = db()->prepare('SELECT js.*, jc.journey_id, jc.title AS chapter_title, j.name AS journey_name
        FROM journey_steps js
        JOIN journey_chapters jc ON jc.id = js.chapter_id
        JOIN journeys j ON j.id = jc.journey_id
        WHERE js.id = ? LIMIT 1');
    $stmt->execute([$stepId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_journey(string $name, string $slug, string $description, string $icon, bool $isPublished, int $sortOrder): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Journey name is required.');
    }
    $slug = journey_unique_slug($slug !== '' ? $slug : $name);
    $stmt = db()->prepare('INSERT INTO journeys (name, slug, description, icon, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $slug, trim($description), trim($icon), $isPublished ? 1 : 0, $sortOrder]);
    return (int)db()->lastInsertId();
}

function update_journey(int $journeyId, string $name, string $slug, string $description, string $icon, bool $isPublished, int $sortOrder): void
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Journey name is required.');
    }
    $slug = journey_unique_slug($slug !== '' ? $slug : $name, $journeyId);
    $stmt = db()->prepare('UPDATE journeys SET name = ?, slug = ?, description = ?, icon = ?, is_published = ?, sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?');
    $stmt->execute([$name, $slug, trim($description), trim($icon), $isPublished ? 1 : 0, $sortOrder, $journeyId]);
}

function delete_journey(int $journeyId): void
{
    db()->prepare('DELETE FROM journeys WHERE id = ?')->execute([$journeyId]);
}

function create_chapter(int $journeyId, string $title, string $description, int $sortOrder): int
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Chapter title is required.');
    }
    $stmt = db()->prepare('INSERT INTO journey_chapters (journey_id, title, description, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([$journeyId, $title, trim($description), $sortOrder]);
    return (int)db()->lastInsertId();
}

function update_chapter(int $chapterId, string $title, string $description, int $sortOrder): void
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Chapter title is required.');
    }
    db()->prepare('UPDATE journey_chapters SET title = ?, description = ?, sort_order = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([$title, trim($description), $sortOrder, $chapterId]);
}

function delete_chapter(int $chapterId): void
{
    db()->prepare('DELETE FROM journey_chapters WHERE id = ?')->execute([$chapterId]);
}

function create_step(int $chapterId, string $title, string $description, string $completionMode, string $ruleType, ?string $ruleSkillName, ?int $ruleLevel, ?string $ruleQuestTitle, int $sortOrder): int
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Step title is required.');
    }
    $completionMode = normalise_completion_mode($completionMode);
    $ruleType = normalise_rule_type($ruleType, $completionMode);
    validate_step_rule($completionMode, $ruleType, $ruleSkillName, $ruleLevel, $ruleQuestTitle);

    $stmt = db()->prepare('INSERT INTO journey_steps
        (chapter_id, title, description, completion_mode, auto_rule_type, rule_skill_name, rule_level, rule_quest_title, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$chapterId, $title, trim($description), $completionMode, $ruleType ?: null, clean_nullable($ruleSkillName), $ruleLevel, clean_nullable($ruleQuestTitle), $sortOrder]);
    return (int)db()->lastInsertId();
}

function update_step(int $stepId, string $title, string $description, string $completionMode, string $ruleType, ?string $ruleSkillName, ?int $ruleLevel, ?string $ruleQuestTitle, int $sortOrder): void
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Step title is required.');
    }
    $completionMode = normalise_completion_mode($completionMode);
    $ruleType = normalise_rule_type($ruleType, $completionMode);
    validate_step_rule($completionMode, $ruleType, $ruleSkillName, $ruleLevel, $ruleQuestTitle);

    db()->prepare('UPDATE journey_steps
        SET title = ?, description = ?, completion_mode = ?, auto_rule_type = ?, rule_skill_name = ?, rule_level = ?, rule_quest_title = ?, sort_order = ?, updated_at = UTC_TIMESTAMP()
        WHERE id = ?')
        ->execute([$title, trim($description), $completionMode, $ruleType ?: null, clean_nullable($ruleSkillName), $ruleLevel, clean_nullable($ruleQuestTitle), $sortOrder, $stepId]);
}

function delete_step(int $stepId): void
{
    db()->prepare('DELETE FROM journey_steps WHERE id = ?')->execute([$stepId]);
}

function clean_nullable(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function normalise_completion_mode(string $mode): string
{
    return array_key_exists($mode, journey_completion_modes()) ? $mode : 'manual_only';
}

function normalise_rule_type(string $ruleType, string $completionMode): string
{
    $ruleType = trim($ruleType);
    if ($completionMode === 'manual_only') {
        return '';
    }
    return array_key_exists($ruleType, journey_auto_rule_types()) ? $ruleType : '';
}

function validate_step_rule(string $completionMode, string $ruleType, ?string $ruleSkillName, ?int $ruleLevel, ?string $ruleQuestTitle): void
{
    if ($completionMode === 'auto_only' && $ruleType === '') {
        throw new InvalidArgumentException('Automatic only steps need an automatic rule.');
    }
    if ($ruleType === 'skill_level') {
        if (trim((string)$ruleSkillName) === '' || !$ruleLevel || $ruleLevel < 1) {
            throw new InvalidArgumentException('Skill level rules need a skill name and level.');
        }
    }
    if ($ruleType === 'quest_complete' && trim((string)$ruleQuestTitle) === '') {
        throw new InvalidArgumentException('Quest rules need a quest title.');
    }
}

function start_journey_for_profile(int $profileId, int $journeyId): void
{
    db()->prepare('INSERT IGNORE INTO player_journeys (profile_id, journey_id, started_at) VALUES (?, ?, UTC_TIMESTAMP())')
        ->execute([$profileId, $journeyId]);
}

function stop_journey_for_profile(int $profileId, int $journeyId): void
{
    db()->prepare('DELETE FROM player_journeys WHERE profile_id = ? AND journey_id = ?')->execute([$profileId, $journeyId]);
}

function player_journey(int $profileId, int $journeyId): ?array
{
    $stmt = db()->prepare('SELECT * FROM player_journeys WHERE profile_id = ? AND journey_id = ? LIMIT 1');
    $stmt->execute([$profileId, $journeyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function journeys_for_profile(int $profileId): array
{
    $stmt = db()->prepare('SELECT j.*, pj.started_at, pj.completed_at
        FROM player_journeys pj
        JOIN journeys j ON j.id = pj.journey_id
        WHERE pj.profile_id = ?
        ORDER BY pj.started_at DESC');
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function progress_for_profile_steps(int $profileId, array $stepIds): array
{
    if (!$stepIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($stepIds), '?'));
    $params = array_merge([$profileId], array_map('intval', $stepIds));
    $stmt = db()->prepare("SELECT * FROM player_step_progress WHERE profile_id = ? AND step_id IN ($placeholders)");
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int)$row['step_id']] = $row;
    }
    return $rows;
}

function evaluate_journey_progress(int $profileId, int $journeyId): array
{
    $steps = steps_for_journey($journeyId);
    $progress = progress_for_profile_steps($profileId, array_map(fn($s) => (int)$s['id'], $steps));
    $evaluated = [];
    $completed = 0;

    foreach ($steps as $step) {
        $stepId = (int)$step['id'];
        $row = $progress[$stepId] ?? null;
        $autoComplete = step_auto_complete($profileId, $step);
        $isCompleted = $autoComplete || (!empty($row['is_completed']));
        if ($autoComplete) {
            upsert_step_progress($profileId, $stepId, true, 'automatic');
            $row = $progress[$stepId] ?? [];
            $row['is_completed'] = 1;
            $row['completion_source'] = 'automatic';
        }
        if ($isCompleted) {
            $completed++;
        }
        $step['progress'] = $row;
        $step['is_completed'] = $isCompleted;
        $step['auto_complete'] = $autoComplete;
        $step['can_complete_manually'] = in_array($step['completion_mode'], ['manual_only', 'auto_or_manual'], true);
        $evaluated[] = $step;
    }

    return [
        'steps' => $evaluated,
        'total' => count($steps),
        'completed' => $completed,
        'percent' => count($steps) ? round(($completed / count($steps)) * 100, 1) : 0,
    ];
}

function upsert_step_progress(int $profileId, int $stepId, bool $completed, string $source): void
{
    db()->prepare('INSERT INTO player_step_progress (profile_id, step_id, is_completed, completion_source, completed_at)
        VALUES (?, ?, ?, ?, IF(?, UTC_TIMESTAMP(), NULL))
        ON DUPLICATE KEY UPDATE
            is_completed = VALUES(is_completed),
            completion_source = VALUES(completion_source),
            completed_at = IF(VALUES(is_completed) = 1, COALESCE(player_step_progress.completed_at, UTC_TIMESTAMP()), NULL),
            updated_at = UTC_TIMESTAMP()')
        ->execute([$profileId, $stepId, $completed ? 1 : 0, $source, $completed ? 1 : 0]);
}

function manually_set_step_progress(int $profileId, int $stepId, bool $completed): void
{
    $step = step_by_id($stepId);
    if (!$step) {
        throw new InvalidArgumentException('Step not found.');
    }
    if (!in_array($step['completion_mode'], ['manual_only', 'auto_or_manual'], true)) {
        throw new InvalidArgumentException('This step is completed automatically and cannot be manually checked.');
    }
    upsert_step_progress($profileId, $stepId, $completed, 'manual');
}

function step_auto_complete(int $profileId, array $step): bool
{
    $ruleType = (string)($step['auto_rule_type'] ?? '');
    if ($ruleType === '') {
        return false;
    }

    if ($ruleType === 'skill_level') {
        $skillName = trim((string)($step['rule_skill_name'] ?? ''));
        $targetLevel = (int)($step['rule_level'] ?? 0);
        if ($skillName === '' || $targetLevel <= 0) {
            return false;
        }
        $stmt = db()->prepare('SELECT level, xp FROM player_latest_skills WHERE profile_id = ? AND LOWER(skill_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$profileId, $skillName]);
        $skill = $stmt->fetch();
        if (!$skill) {
            return false;
        }
        $display = rs3_display_level($skillName, $skill['level'] ?? null, $skill['xp'] ?? null);
        return (int)$display['display_level'] >= $targetLevel;
    }

    if ($ruleType === 'quest_complete') {
        $questTitle = trim((string)($step['rule_quest_title'] ?? ''));
        if ($questTitle === '') {
            return false;
        }
        $stmt = db()->prepare('SELECT status FROM player_quest_statuses WHERE profile_id = ? AND LOWER(quest_title) = LOWER(?) LIMIT 1');
        $stmt->execute([$profileId, $questTitle]);
        $status = strtolower((string)($stmt->fetchColumn() ?: ''));
        return $status !== '' && str_contains($status, 'complete');
    }

    return false;
}

function completion_mode_label(string $mode): string
{
    return journey_completion_modes()[$mode] ?? $mode;
}

function rule_summary(array $step): string
{
    $type = (string)($step['auto_rule_type'] ?? '');
    if ($type === 'skill_level') {
        return 'Reach level ' . (int)($step['rule_level'] ?? 0) . ' ' . (string)($step['rule_skill_name'] ?? '');
    }
    if ($type === 'quest_complete') {
        return 'Complete quest: ' . (string)($step['rule_quest_title'] ?? '');
    }
    return 'Manual progress';
}
