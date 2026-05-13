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
    $sql = 'SELECT j.*, u.username AS creator_username, u.global_name AS creator_global_name
        FROM journeys j
        LEFT JOIN users u ON u.id = j.created_by_user_id';
    if ($publishedOnly) {
        $sql .= ' WHERE j.is_published = 1';
    }
    $sql .= ' ORDER BY j.sort_order ASC, j.name ASC';
    return db()->query($sql)->fetchAll();
}

function journey_by_id(int $journeyId): ?array
{
    $stmt = db()->prepare('SELECT j.*, u.username AS creator_username, u.global_name AS creator_global_name
        FROM journeys j
        LEFT JOIN users u ON u.id = j.created_by_user_id
        WHERE j.id = ? LIMIT 1');
    $stmt->execute([$journeyId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function journey_creator_label(array $journey): string
{
    $name = trim((string)($journey['creator_global_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($journey['creator_username'] ?? ''));
    }
    return $name !== '' ? $name : 'Unknown / legacy journey';
}

function journey_can_edit(array $journey): bool
{
    if (!current_user_can('journeys.manage')) {
        return false;
    }
    if (current_user_can('journeys.edit.all')) {
        return true;
    }
    $creatorId = (int)($journey['created_by_user_id'] ?? 0);
    $user = current_user();
    return $creatorId <= 0 || ($user && $creatorId === (int)$user['id']);
}

function journey_can_delete_item(array $journey): bool
{
    if (current_user_can('journeys.delete.all')) {
        return true;
    }
    if (!current_user_can('journeys.delete')) {
        return false;
    }
    $creatorId = (int)($journey['created_by_user_id'] ?? 0);
    $user = current_user();
    return $creatorId <= 0 || ($user && $creatorId === (int)$user['id']);
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
    $stmt = db()->prepare('SELECT js.*, jc.title AS chapter_title, jc.sort_order AS chapter_sort_order, ci.name AS content_name, ci.type AS content_type
        FROM journey_steps js
        JOIN journey_chapters jc ON jc.id = js.chapter_id
        LEFT JOIN content_items ci ON ci.id = js.content_item_id
        WHERE jc.journey_id = ?
        ORDER BY jc.sort_order ASC, jc.id ASC, js.sort_order ASC, js.id ASC');
    $stmt->execute([$journeyId]);
    return $stmt->fetchAll();
}

function step_by_id(int $stepId): ?array
{
    $stmt = db()->prepare('SELECT js.*, jc.journey_id, jc.title AS chapter_title, j.name AS journey_name, ci.name AS content_name, ci.type AS content_type
        FROM journey_steps js
        JOIN journey_chapters jc ON jc.id = js.chapter_id
        JOIN journeys j ON j.id = jc.journey_id
        LEFT JOIN content_items ci ON ci.id = js.content_item_id
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
    $creatorId = current_user()['id'] ?? null;
    try {
        $stmt = db()->prepare('INSERT INTO journeys (name, slug, description, icon, is_published, sort_order, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $slug, trim($description), trim($icon), $isPublished ? 1 : 0, $sortOrder, $creatorId]);
    } catch (Throwable $e) {
        $stmt = db()->prepare('INSERT INTO journeys (name, slug, description, icon, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $slug, trim($description), trim($icon), $isPublished ? 1 : 0, $sortOrder]);
    }
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


function apply_content_defaults_to_step_values(?int $contentItemId, string $title, string $description, string $completionMode, string $ruleType, ?string $ruleQuestTitle): array
{
    $contentItemId = (int)($contentItemId ?? 0);
    if ($contentItemId <= 0) {
        return [$title, $description, $completionMode, $ruleType, $ruleQuestTitle, null];
    }

    $content = content_item_by_id($contentItemId);
    if (!$content) {
        return [$title, $description, $completionMode, $ruleType, $ruleQuestTitle, null];
    }

    if (trim($title) === '') {
        $title = (string)$content['name'];
    }

    if (trim($description) === '' && !empty($content['description'])) {
        $description = (string)$content['description'];
    }

    if ($content['type'] === 'quest') {
        if ($completionMode === '' || $completionMode === 'manual_only') {
            $completionMode = 'auto_or_manual';
        }
        if ($ruleType === '') {
            $ruleType = 'quest_complete';
        }
        if (!$ruleQuestTitle) {
            $ruleQuestTitle = (string)$content['name'];
        }
    } elseif (in_array($content['type'], ['achievement', 'task', 'boss', 'drop', 'unlock', 'item'], true)) {
        if ($completionMode === '') {
            $completionMode = 'manual_only';
        }
    }

    return [$title, $description, $completionMode, $ruleType, $ruleQuestTitle, $contentItemId];
}


function create_step(int $chapterId, string $title, string $description, string $completionMode, string $ruleType, ?string $ruleSkillName, ?int $ruleLevel, ?string $ruleQuestTitle, int $sortOrder, bool $isOptional = false, ?int $requiresStepId = null, ?int $contentItemId = null): int
{
    [$title, $description, $completionMode, $ruleType, $ruleQuestTitle, $contentItemId] = apply_content_defaults_to_step_values($contentItemId, $title, $description, $completionMode, $ruleType, $ruleQuestTitle);
    [$title, $description, $completionMode, $ruleType, $ruleQuestTitle, $contentItemId] = apply_content_defaults_to_step_values($contentItemId, $title, $description, $completionMode, $ruleType, $ruleQuestTitle);
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Step title is required.');
    }
    $completionMode = normalise_completion_mode($completionMode);
    $ruleType = normalise_rule_type($ruleType, $completionMode);
    validate_step_rule($completionMode, $ruleType, $ruleSkillName, $ruleLevel, $ruleQuestTitle);

    $requiresStepId = valid_requires_step_id($requiresStepId, $chapterId);
    $stmt = db()->prepare('INSERT INTO journey_steps
        (chapter_id, title, description, completion_mode, auto_rule_type, rule_skill_name, rule_level, rule_quest_title, sort_order, is_optional, requires_step_id, content_item_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$chapterId, $title, trim($description), $completionMode, $ruleType ?: null, clean_nullable($ruleSkillName), $ruleLevel, clean_nullable($ruleQuestTitle), $sortOrder, $isOptional ? 1 : 0, $requiresStepId, $contentItemId]);
    return (int)db()->lastInsertId();
}

function update_step(int $stepId, string $title, string $description, string $completionMode, string $ruleType, ?string $ruleSkillName, ?int $ruleLevel, ?string $ruleQuestTitle, int $sortOrder, bool $isOptional = false, ?int $requiresStepId = null, ?int $contentItemId = null): void
{
    $title = trim($title);
    if ($title === '') {
        throw new InvalidArgumentException('Step title is required.');
    }
    $completionMode = normalise_completion_mode($completionMode);
    $ruleType = normalise_rule_type($ruleType, $completionMode);
    validate_step_rule($completionMode, $ruleType, $ruleSkillName, $ruleLevel, $ruleQuestTitle);

    $step = step_by_id($stepId);
    $requiresStepId = valid_requires_step_id($requiresStepId, (int)($step['chapter_id'] ?? 0), $stepId);
    db()->prepare('UPDATE journey_steps
        SET title = ?, description = ?, completion_mode = ?, auto_rule_type = ?, rule_skill_name = ?, rule_level = ?, rule_quest_title = ?, sort_order = ?, is_optional = ?, requires_step_id = ?, content_item_id = ?, updated_at = UTC_TIMESTAMP()
        WHERE id = ?')
        ->execute([$title, trim($description), $completionMode, $ruleType ?: null, clean_nullable($ruleSkillName), $ruleLevel, clean_nullable($ruleQuestTitle), $sortOrder, $isOptional ? 1 : 0, $requiresStepId, $contentItemId, $stepId]);
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

function valid_requires_step_id(?int $requiresStepId, int $chapterId, ?int $currentStepId = null): ?int
{
    $requiresStepId = (int)($requiresStepId ?? 0);
    if ($requiresStepId <= 0) {
        return null;
    }

    $current = $currentStepId ? step_by_id($currentStepId) : null;
    $journeyId = null;
    if ($current && isset($current['journey_id'])) {
        $journeyId = (int)$current['journey_id'];
    } elseif ($chapterId > 0) {
        $chapter = chapter_by_id($chapterId);
        $journeyId = $chapter ? (int)$chapter['journey_id'] : null;
    }

    if (!$journeyId) {
        return null;
    }

    $candidate = step_by_id($requiresStepId);
    if (!$candidate || (int)$candidate['journey_id'] !== $journeyId || ($currentStepId && (int)$candidate['id'] === $currentStepId)) {
        return null;
    }

    return $requiresStepId;
}

function prerequisite_options_for_journey(int $journeyId, ?int $excludeStepId = null): array
{
    $steps = steps_for_journey($journeyId);
    return array_values(array_filter($steps, function (array $step) use ($excludeStepId): bool {
        return !$excludeStepId || (int)$step['id'] !== $excludeStepId;
    }));
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
    $requiredTotal = 0;
    $requiredCompleted = 0;
    $progressByStep = [];
    $recommended = [];

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
            $row['completed_at'] = $row['completed_at'] ?? gmdate('Y-m-d H:i:s');
        }

        $isOptional = !empty($step['is_optional']);
        $requiresStepId = isset($step['requires_step_id']) ? (int)$step['requires_step_id'] : 0;
        $isLocked = false;
        $lockReason = null;

        if ($requiresStepId > 0) {
            $requiredProgress = $progressByStep[$requiresStepId] ?? null;
            if (!$requiredProgress || empty($requiredProgress['is_completed'])) {
                $isLocked = true;
                $requiredStep = step_by_id($requiresStepId);
                $lockReason = $requiredStep ? 'Requires: ' . $requiredStep['title'] : 'Requires another step first';
            }
        }

        if ($isCompleted) {
            $completed++;
        }
        if (!$isOptional) {
            $requiredTotal++;
            if ($isCompleted) {
                $requiredCompleted++;
            }
        }

        $step['progress'] = $row;
        $step['is_completed'] = $isCompleted;
        $step['auto_complete'] = $autoComplete;
        $step['is_optional'] = $isOptional;
        $step['is_locked'] = $isLocked;
        $step['lock_reason'] = $lockReason;
        $step['can_complete_manually'] = (!$isLocked && in_array($step['completion_mode'], ['manual_only', 'auto_or_manual'], true));
        $step['is_available'] = (!$isCompleted && !$isLocked);

        $evaluated[] = $step;
        $progressByStep[$stepId] = [
            'is_completed' => $isCompleted,
            'is_locked' => $isLocked,
        ];

        if (!$isCompleted && !$isLocked && count($recommended) < 5) {
            $recommended[] = $step;
        }
    }

    return [
        'steps' => $evaluated,
        'total' => count($steps),
        'completed' => $completed,
        'required_total' => $requiredTotal,
        'required_completed' => $requiredCompleted,
        'percent' => $requiredTotal ? round(($requiredCompleted / $requiredTotal) * 100, 1) : (count($steps) ? round(($completed / count($steps)) * 100, 1) : 0),
        'recommended' => $recommended,
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



function normalise_sort_orders_for_chapters(int $journeyId): void
{
    $chapters = chapters_for_journey($journeyId);
    $sort = 10;
    $stmt = db()->prepare('UPDATE journey_chapters SET sort_order = ? WHERE id = ?');
    foreach ($chapters as $chapter) {
        $stmt->execute([$sort, (int)$chapter['id']]);
        $sort += 10;
    }
}

function normalise_sort_orders_for_steps(int $chapterId): void
{
    $steps = steps_for_chapter($chapterId);
    $sort = 10;
    $stmt = db()->prepare('UPDATE journey_steps SET sort_order = ? WHERE id = ?');
    foreach ($steps as $step) {
        $stmt->execute([$sort, (int)$step['id']]);
        $sort += 10;
    }
}

function move_chapter(int $chapterId, string $direction): void
{
    $chapter = chapter_by_id($chapterId);
    if (!$chapter) {
        throw new InvalidArgumentException('Chapter not found.');
    }

    normalise_sort_orders_for_chapters((int)$chapter['journey_id']);
    $chapters = chapters_for_journey((int)$chapter['journey_id']);
    $index = null;
    foreach ($chapters as $i => $row) {
        if ((int)$row['id'] === $chapterId) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }

    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($chapters[$swapIndex])) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $a = $chapters[$index];
        $b = $chapters[$swapIndex];
        $pdo->prepare('UPDATE journey_chapters SET sort_order = ? WHERE id = ?')->execute([(int)$b['sort_order'], (int)$a['id']]);
        $pdo->prepare('UPDATE journey_chapters SET sort_order = ? WHERE id = ?')->execute([(int)$a['sort_order'], (int)$b['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function move_step(int $stepId, string $direction): void
{
    $step = step_by_id($stepId);
    if (!$step) {
        throw new InvalidArgumentException('Step not found.');
    }

    normalise_sort_orders_for_steps((int)$step['chapter_id']);
    $steps = steps_for_chapter((int)$step['chapter_id']);
    $index = null;
    foreach ($steps as $i => $row) {
        if ((int)$row['id'] === $stepId) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }

    $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($steps[$swapIndex])) {
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $a = $steps[$index];
        $b = $steps[$swapIndex];
        $pdo->prepare('UPDATE journey_steps SET sort_order = ? WHERE id = ?')->execute([(int)$b['sort_order'], (int)$a['id']]);
        $pdo->prepare('UPDATE journey_steps SET sort_order = ? WHERE id = ?')->execute([(int)$a['sort_order'], (int)$b['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicate_journey(int $journeyId): int
{
    $journey = journey_by_id($journeyId);
    if (!$journey) {
        throw new InvalidArgumentException('Journey not found.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newJourneyId = create_journey(
            (string)$journey['name'] . ' Copy',
            '',
            (string)($journey['description'] ?? ''),
            (string)($journey['icon'] ?? '🧭'),
            false,
            (int)$journey['sort_order'] + 10
        );
        set_journey_tags($newJourneyId, journey_tag_ids_for_journey($journeyId));

        $stepMap = [];
        foreach (chapters_for_journey($journeyId) as $chapter) {
            $newChapterId = create_chapter(
                $newJourneyId,
                (string)$chapter['title'],
                (string)($chapter['description'] ?? ''),
                (int)$chapter['sort_order']
            );

            foreach (steps_for_chapter((int)$chapter['id']) as $step) {
                $newStepId = create_step(
                    $newChapterId,
                    (string)$step['title'],
                    (string)($step['description'] ?? ''),
                    (string)$step['completion_mode'],
                    (string)($step['auto_rule_type'] ?? ''),
                    $step['rule_skill_name'] ?? null,
                    isset($step['rule_level']) ? (int)$step['rule_level'] : null,
                    $step['rule_quest_title'] ?? null,
                    (int)$step['sort_order'],
                    !empty($step['is_optional']),
                    null
                );
                $stepMap[(int)$step['id']] = $newStepId;
            }
        }

        foreach (chapters_for_journey($journeyId) as $chapter) {
            foreach (steps_for_chapter((int)$chapter['id']) as $step) {
                $oldRequires = (int)($step['requires_step_id'] ?? 0);
                if ($oldRequires && isset($stepMap[$oldRequires], $stepMap[(int)$step['id']])) {
                    db()->prepare('UPDATE journey_steps SET requires_step_id = ? WHERE id = ?')->execute([$stepMap[$oldRequires], $stepMap[(int)$step['id']]]);
                }
            }
        }

        $pdo->commit();
        return $newJourneyId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicate_chapter(int $chapterId): int
{
    $chapter = chapter_by_id($chapterId);
    if (!$chapter) {
        throw new InvalidArgumentException('Chapter not found.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $newChapterId = create_chapter(
            (int)$chapter['journey_id'],
            (string)$chapter['title'] . ' Copy',
            (string)($chapter['description'] ?? ''),
            (int)$chapter['sort_order'] + 10
        );

        $stepMap = [];
        foreach (steps_for_chapter($chapterId) as $step) {
            $newStepId = create_step(
                $newChapterId,
                (string)$step['title'],
                (string)($step['description'] ?? ''),
                (string)$step['completion_mode'],
                (string)($step['auto_rule_type'] ?? ''),
                $step['rule_skill_name'] ?? null,
                isset($step['rule_level']) ? (int)$step['rule_level'] : null,
                $step['rule_quest_title'] ?? null,
                (int)$step['sort_order'],
                !empty($step['is_optional']),
                null
            );
            $stepMap[(int)$step['id']] = $newStepId;
        }

        foreach (steps_for_chapter($chapterId) as $step) {
            $oldRequires = (int)($step['requires_step_id'] ?? 0);
            if ($oldRequires && isset($stepMap[$oldRequires], $stepMap[(int)$step['id']])) {
                db()->prepare('UPDATE journey_steps SET requires_step_id = ? WHERE id = ?')->execute([$stepMap[$oldRequires], $stepMap[(int)$step['id']]]);
            }
        }

        $pdo->commit();
        return $newChapterId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function duplicate_step(int $stepId): int
{
    $step = step_by_id($stepId);
    if (!$step) {
        throw new InvalidArgumentException('Step not found.');
    }

    return create_step(
        (int)$step['chapter_id'],
        (string)$step['title'] . ' Copy',
        (string)($step['description'] ?? ''),
        (string)$step['completion_mode'],
        (string)($step['auto_rule_type'] ?? ''),
        $step['rule_skill_name'] ?? null,
        isset($step['rule_level']) ? (int)$step['rule_level'] : null,
        $step['rule_quest_title'] ?? null,
        (int)$step['sort_order'] + 10,
        !empty($step['is_optional']),
        (int)($step['requires_step_id'] ?? 0) ?: null
    );
}

function apply_step_template_values(string $template, array $current = []): array
{
    $values = $current;

    if ($template === 'skill_level') {
        $values['completion_mode'] = $values['completion_mode'] ?? 'auto_only';
        $values['auto_rule_type'] = 'skill_level';
        $values['title'] = $values['title'] ?: 'Reach level X in Skill';
    } elseif ($template === 'quest_complete') {
        $values['completion_mode'] = $values['completion_mode'] ?? 'auto_or_manual';
        $values['auto_rule_type'] = 'quest_complete';
        $values['title'] = $values['title'] ?: 'Complete Quest Name';
    } elseif ($template === 'manual_unlock') {
        $values['completion_mode'] = 'manual_only';
        $values['auto_rule_type'] = '';
        $values['title'] = $values['title'] ?: 'Unlock something useful';
    } elseif ($template === 'optional_goal') {
        $values['completion_mode'] = 'manual_only';
        $values['auto_rule_type'] = '';
        $values['is_optional'] = 1;
        $values['title'] = $values['title'] ?: 'Optional goal';
    }

    return $values;
}



function journey_tag_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'tag';
}

function all_journey_tags(): array
{
    return db()->query('SELECT * FROM journey_tags ORDER BY sort_order ASC, name ASC')->fetchAll();
}

function journey_tags_for_journey(int $journeyId): array
{
    $stmt = db()->prepare('SELECT jt.* FROM journey_tags jt JOIN journey_tag_map jtm ON jtm.tag_id = jt.id WHERE jtm.journey_id = ? ORDER BY jt.sort_order ASC, jt.name ASC');
    $stmt->execute([$journeyId]);
    return $stmt->fetchAll();
}

function journey_tag_ids_for_journey(int $journeyId): array
{
    return array_map(fn($row) => (int)$row['id'], journey_tags_for_journey($journeyId));
}

function set_journey_tags(int $journeyId, array $tagIds): void
{
    $pdo = db();
    $pdo->prepare('DELETE FROM journey_tag_map WHERE journey_id = ?')->execute([$journeyId]);
    $insert = $pdo->prepare('INSERT IGNORE INTO journey_tag_map (journey_id, tag_id) VALUES (?, ?)');
    foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
        if ($tagId > 0) {
            $insert->execute([$journeyId, $tagId]);
        }
    }
}

function create_journey_tag(string $name, string $description = '', int $sortOrder = 0): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Tag name is required.');
    }
    $slug = journey_tag_slugify($name);
    $base = $slug;
    $i = 2;
    $pdo = db();
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM journey_tags WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            break;
        }
        $slug = $base . '-' . $i++;
    }
    $stmt = $pdo->prepare('INSERT INTO journey_tags (slug, name, description, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([$slug, $name, trim($description), $sortOrder]);
    return (int)$pdo->lastInsertId();
}

function journeys_with_tags(bool $publishedOnly = false): array
{
    $journeys = all_journeys($publishedOnly);
    foreach ($journeys as &$journey) {
        $journey['tags'] = journey_tags_for_journey((int)$journey['id']);
    }
    unset($journey);
    return $journeys;
}


function journey_step_for_content(int $journeyId, int $contentItemId): ?array
{
    $stmt = db()->prepare('SELECT js.* FROM journey_steps js JOIN journey_chapters jc ON jc.id = js.chapter_id WHERE jc.journey_id = ? AND js.content_item_id = ? LIMIT 1');
    $stmt->execute([$journeyId, $contentItemId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function add_content_prerequisite_steps_to_chapter(int $chapterId, int $contentItemId, ?int $parentStepId = null, array &$seen = []): array
{
    $chapter = chapter_by_id($chapterId);
    if (!$chapter) {
        throw new InvalidArgumentException('Chapter not found.');
    }

    $journeyId = (int)$chapter['journey_id'];
    $content = content_item_by_id($contentItemId);
    if (!$content) {
        throw new InvalidArgumentException('Content item not found.');
    }

    if (isset($seen[$contentItemId])) {
        return [];
    }
    $seen[$contentItemId] = true;

    $created = [];
    $questPrereqStepIds = [];
    $skillPrereqStepIds = [];

    foreach (content_quest_requirements($contentItemId) as $req) {
        $reqContentId = (int)$req['required_content_item_id'];

        $nestedCreated = add_content_prerequisite_steps_to_chapter($chapterId, $reqContentId, null, $seen);
        $created = array_merge($created, $nestedCreated);

        $existing = journey_step_for_content($journeyId, $reqContentId);
        if ($existing) {
            $reqStepId = (int)$existing['id'];
        } else {
            $reqContent = content_item_by_id($reqContentId);
            if (!$reqContent) {
                continue;
            }

            $reqStepId = create_step(
                $chapterId,
                'Complete ' . (string)$reqContent['name'],
                (string)($reqContent['description'] ?? ''),
                'auto_or_manual',
                'quest_complete',
                null,
                null,
                (string)$reqContent['name'],
                next_step_sort_order($chapterId),
                false,
                null,
                $reqContentId
            );
            $created[] = $reqStepId;
        }

        $questPrereqStepIds[] = $reqStepId;
    }

    foreach (content_skill_requirements($contentItemId) as $skillReq) {
        $skillName = (string)$skillReq['skill_name'];
        $requiredLevel = (int)$skillReq['required_level'];
        $skillTitle = 'Reach ' . $requiredLevel . ' ' . $skillName;
        $existingSkill = find_step_by_title_in_journey($journeyId, $skillTitle);

        $lowerSkillStep = find_highest_lower_skill_step_in_journey($journeyId, $skillName, $requiredLevel);

        if ($existingSkill) {
            $skillStepId = (int)$existingSkill['id'];
            if ($lowerSkillStep) {
                db()->prepare('UPDATE journey_steps SET requires_step_id = ? WHERE id = ? AND (requires_step_id IS NULL OR requires_step_id = 0)')
                    ->execute([(int)$lowerSkillStep['id'], $skillStepId]);
            }
        } else {
            $skillStepId = create_step(
                $chapterId,
                $skillTitle,
                (string)($skillReq['notes'] ?? ''),
                'auto_only',
                'skill_level',
                $skillName,
                $requiredLevel,
                null,
                next_step_sort_order($chapterId),
                false,
                $lowerSkillStep ? (int)$lowerSkillStep['id'] : null,
                null
            );
            $created[] = $skillStepId;
        }

        $skillPrereqStepIds[] = $skillStepId;
    }

    $questPrereqStepIds = array_values(array_unique(array_filter(array_map('intval', $questPrereqStepIds))));
    for ($i = 1; $i < count($questPrereqStepIds); $i++) {
        db()->prepare('UPDATE journey_steps SET requires_step_id = COALESCE(requires_step_id, ?) WHERE id = ?')
            ->execute([$questPrereqStepIds[$i - 1], $questPrereqStepIds[$i]]);
    }

    // The target step can only store one lock. Prefer quest locks because skills are independently visible/checkable.
    if ($parentStepId && $questPrereqStepIds) {
        $lastQuestPrereqId = end($questPrereqStepIds);
        db()->prepare('UPDATE journey_steps SET requires_step_id = ? WHERE id = ?')
            ->execute([(int)$lastQuestPrereqId, $parentStepId]);
    }

    $orderedPrereqs = array_merge($questPrereqStepIds, $skillPrereqStepIds);
    reorder_chapter_steps_with_prerequisites_first($chapterId, $parentStepId ?: null, $orderedPrereqs);

    return array_values(array_unique($created));
}

function reorder_chapter_steps_with_prerequisites_first(int $chapterId, ?int $targetStepId, array $prereqStepIds): void
{
    $prereqStepIds = array_values(array_unique(array_filter(array_map('intval', $prereqStepIds))));
    if (!$targetStepId || !$prereqStepIds) {
        normalise_sort_orders_for_steps($chapterId);
        return;
    }

    $steps = steps_for_chapter($chapterId);
    $target = null;
    $prereqs = [];
    $others = [];

    foreach ($steps as $step) {
        $stepId = (int)$step['id'];
        if ($stepId === $targetStepId) {
            $target = $step;
            continue;
        }
        if (in_array($stepId, $prereqStepIds, true)) {
            $prereqs[$stepId] = $step;
            continue;
        }
        $others[] = $step;
    }

    if (!$target) {
        normalise_sort_orders_for_steps($chapterId);
        return;
    }

    $orderedPrereqs = [];
    foreach ($prereqStepIds as $id) {
        if (isset($prereqs[$id])) {
            $orderedPrereqs[] = $prereqs[$id];
        }
    }

    $ordered = array_merge($orderedPrereqs, [$target], $others);

    $sort = 10;
    $stmt = db()->prepare('UPDATE journey_steps SET sort_order = ? WHERE id = ?');
    foreach ($ordered as $step) {
        $stmt->execute([$sort, (int)$step['id']]);
        $sort += 10;
    }
}

function find_highest_lower_skill_step_in_journey(int $journeyId, string $skillName, int $requiredLevel): ?array
{
    $stmt = db()->prepare("SELECT js.*
        FROM journey_steps js
        JOIN journey_chapters jc ON jc.id = js.chapter_id
        WHERE jc.journey_id = ?
          AND js.auto_rule_type = 'skill_level'
          AND LOWER(js.rule_skill_name) = LOWER(?)
          AND js.rule_level < ?
        ORDER BY js.rule_level DESC
        LIMIT 1");
    $stmt->execute([$journeyId, $skillName, $requiredLevel]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function next_step_sort_order(int $chapterId): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM journey_steps WHERE chapter_id = ?');
    $stmt->execute([$chapterId]);
    return (int)$stmt->fetchColumn();
}

function find_step_by_title_in_journey(int $journeyId, string $title): ?array
{
    $stmt = db()->prepare('SELECT js.* FROM journey_steps js JOIN journey_chapters jc ON jc.id = js.chapter_id WHERE jc.journey_id = ? AND LOWER(js.title) = LOWER(?) LIMIT 1');
    $stmt->execute([$journeyId, $title]);
    $row = $stmt->fetch();
    return $row ?: null;
}

