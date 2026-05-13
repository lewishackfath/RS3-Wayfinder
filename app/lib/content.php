<?php
declare(strict_types=1);

function content_types(): array
{
    return [
        'quest' => 'Quest',
        'achievement' => 'Achievement',
        'task' => 'Task',
        'boss' => 'Boss',
        'unlock' => 'Unlock',
        'item' => 'Item',
    ];
}

function content_relationship_types(): array
{
    return [
        'requires' => 'Requires',
        'unlocks' => 'Unlocks',
        'related_to' => 'Related to',
        'contains' => 'Contains',
        'part_of' => 'Part of',
    ];
}

function content_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    return trim($slug, '-') ?: 'content';
}

function content_unique_slug(string $name, ?int $ignoreId = null): string
{
    $base = content_slugify($name);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM content_items WHERE slug = ?';
        $params = [$slug];
        if ($ignoreId) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function content_items(array $filters = []): array
{
    $where = [];
    $params = [];
    if (!empty($filters['type'])) {
        $where[] = 'type = ?';
        $params[] = $filters['type'];
    }
    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $where[] = 'is_active = ?';
        $params[] = (int)$filters['is_active'];
    }
    if (!empty($filters['q'])) {
        $where[] = '(name LIKE ? OR category LIKE ? OR description LIKE ?)';
        $q = '%' . $filters['q'] . '%';
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
    }

    $sql = 'SELECT * FROM content_items';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY FIELD(type, "quest","achievement","task","boss","item","unlock"), name ASC LIMIT 500';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function content_item_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM content_items WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function content_items_for_select(?string $type = null): array
{
    return content_items(['type' => $type ?: '', 'is_active' => 1]);
}

function create_content_item(string $type, string $name, string $description, string $category, string $sourceUrl, string $iconUrl, bool $isActive): int
{
    if (!isset(content_types()[$type])) {
        throw new InvalidArgumentException('Invalid content type.');
    }
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }

    $slug = content_unique_slug($name);
    $stmt = db()->prepare('INSERT INTO content_items (type, name, slug, description, category, source_url, icon_url, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$type, $name, $slug, trim($description), trim($category), trim($sourceUrl), trim($iconUrl), $isActive ? 1 : 0]);
    $id = (int)db()->lastInsertId();

    return $id;
}

function update_content_item(int $id, string $type, string $name, string $description, string $category, string $sourceUrl, string $iconUrl, bool $isActive): void
{
    $existing = content_item_by_id($id);
    if (!$existing) {
        throw new InvalidArgumentException('Content item not found.');
    }
    if (!isset(content_types()[$type])) {
        throw new InvalidArgumentException('Invalid content type.');
    }
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }

    $slug = content_unique_slug($name, $id);
    db()->prepare('UPDATE content_items SET type = ?, name = ?, slug = ?, description = ?, category = ?, source_url = ?, icon_url = ?, is_active = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([$type, $name, $slug, trim($description), trim($category), trim($sourceUrl), trim($iconUrl), $isActive ? 1 : 0, $id]);
}

function delete_content_item(int $id): void
{
    db()->prepare('DELETE FROM content_items WHERE id = ?')->execute([$id]);
}

function content_skill_requirements(int $contentItemId): array
{
    $stmt = db()->prepare('SELECT * FROM content_skill_requirements WHERE content_item_id = ? ORDER BY skill_name ASC');
    $stmt->execute([$contentItemId]);
    return $stmt->fetchAll();
}

function add_content_skill_requirement(int $contentItemId, string $skillName, int $level, string $notes = ''): void
{
    $skillName = trim($skillName);
    if ($skillName === '' || $level < 1) {
        throw new InvalidArgumentException('Skill and level are required.');
    }
    db()->prepare('INSERT INTO content_skill_requirements (content_item_id, skill_name, required_level, notes) VALUES (?, ?, ?, ?)')
        ->execute([$contentItemId, $skillName, $level, trim($notes)]);
}

function delete_content_skill_requirement(int $id): void
{
    db()->prepare('DELETE FROM content_skill_requirements WHERE id = ?')->execute([$id]);
}

function content_quest_requirements(int $contentItemId): array
{
    $stmt = db()->prepare('SELECT cqr.*, ci.name AS required_name FROM content_quest_requirements cqr JOIN content_items ci ON ci.id = cqr.required_content_item_id WHERE cqr.content_item_id = ? ORDER BY ci.name ASC');
    $stmt->execute([$contentItemId]);
    return $stmt->fetchAll();
}

function add_content_quest_requirement(int $contentItemId, int $requiredContentItemId, string $notes = ''): void
{
    if ($contentItemId === $requiredContentItemId) {
        throw new InvalidArgumentException('A content item cannot require itself.');
    }
    db()->prepare('INSERT IGNORE INTO content_quest_requirements (content_item_id, required_content_item_id, notes) VALUES (?, ?, ?)')
        ->execute([$contentItemId, $requiredContentItemId, trim($notes)]);
}

function delete_content_quest_requirement(int $id): void
{
    db()->prepare('DELETE FROM content_quest_requirements WHERE id = ?')->execute([$id]);
}

function boss_drop_sources_for_boss(int $bossContentItemId): array
{
    $stmt = db()->prepare('SELECT bds.*, ci.name AS drop_name, ci.icon_url AS drop_icon_url
        FROM boss_drop_sources bds
        JOIN content_items ci ON ci.id = bds.drop_content_item_id
        WHERE bds.boss_content_item_id = ?
        ORDER BY bds.sort_order ASC, ci.name ASC');
    $stmt->execute([$bossContentItemId]);
    return $stmt->fetchAll();
}

function boss_sources_for_drop(int $dropContentItemId): array
{
    $stmt = db()->prepare('SELECT bds.*, ci.name AS boss_name
        FROM boss_drop_sources bds
        JOIN content_items ci ON ci.id = bds.boss_content_item_id
        WHERE bds.drop_content_item_id = ?
        ORDER BY ci.name ASC');
    $stmt->execute([$dropContentItemId]);
    return $stmt->fetchAll();
}

function add_boss_drop_source(int $bossContentItemId, int $dropContentItemId, string $rarity, string $quantity, string $notes, int $sortOrder): void
{
    $boss = content_item_by_id($bossContentItemId);
    $drop = content_item_by_id($dropContentItemId);
    if (!$boss || $boss['type'] !== 'boss') {
        throw new InvalidArgumentException('Boss content item is invalid.');
    }
    if (!$drop || $drop['type'] !== 'item') {
        throw new InvalidArgumentException('Item content record is invalid.');
    }

    db()->prepare('INSERT INTO boss_drop_sources (boss_content_item_id, drop_content_item_id, rarity, quantity, notes, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rarity = VALUES(rarity), quantity = VALUES(quantity), notes = VALUES(notes), sort_order = VALUES(sort_order)')
        ->execute([$bossContentItemId, $dropContentItemId, trim($rarity), trim($quantity), trim($notes), $sortOrder]);
}

function delete_boss_drop_source(int $id): void
{
    db()->prepare('DELETE FROM boss_drop_sources WHERE id = ?')->execute([$id]);
}

function content_library_counts(): array
{
    $rows = db()->query('SELECT type, COUNT(*) AS total FROM content_items GROUP BY type')->fetchAll();
    $counts = array_fill_keys(array_keys(content_types()), 0);
    foreach ($rows as $row) {
        $counts[(string)$row['type']] = (int)$row['total'];
    }
    return $counts;
}




function quest_difficulty_label(int|string|null $difficulty): string
{
    if ($difficulty === null || $difficulty === '') {
        return 'Unknown';
    }

    if (is_numeric($difficulty)) {
        return [
            0 => 'Novice',
            1 => 'Intermediate',
            2 => 'Experienced',
            3 => 'Master',
            4 => 'Grandmaster',
            5 => 'Special',
        ][(int)$difficulty] ?? 'Unknown';
    }

    return (string)$difficulty;
}

function content_metadata(array $item): array
{
    if (empty($item['metadata_json'])) {
        return [];
    }

    $decoded = json_decode((string)$item['metadata_json'], true);
    return is_array($decoded) ? $decoded : [];
}

function update_content_metadata(int $contentItemId, array $metadata): void
{
    db()->prepare('UPDATE content_items SET metadata_json = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?')
        ->execute([json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $contentItemId]);
}

function quest_metadata_from_post(array $post, array $existing = []): array
{
    $metadata = $existing;
    $metadata['quest_timeline'] = trim((string)($post['quest_timeline'] ?? ($metadata['quest_timeline'] ?? '')));
    $metadata['quest_series'] = trim((string)($post['quest_series'] ?? ($metadata['quest_series'] ?? '')));
    return $metadata;
}


function runemetrics_quest_import_url(string $rsn): string
{
    return 'https://apps.runescape.com/runemetrics/quests?user=' . rawurlencode($rsn);
}

function fetch_runemetrics_quest_list_for_import(string $rsn): array
{
    $rsn = clean_rsn_display($rsn);
    if ($rsn === '') {
        throw new InvalidArgumentException('A valid RSN is required for the quest import.');
    }

    $url = runemetrics_quest_import_url($rsn);
    $headers = [
        'Accept: application/json,text/plain,*/*',
        'User-Agent: RS3-Wayfinder/0.1'
    ];

    $body = false;
    $httpCode = null;
    $error = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            $error = curl_error($ch) ?: 'cURL request failed.';
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (isset($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m)) {
            $httpCode = (int)$m[1];
        }
        if ($body === false) {
            $error = 'HTTP request failed.';
        }
    }

    if ($httpCode !== null && $httpCode >= 400) {
        throw new RuntimeException('RuneMetrics returned HTTP ' . $httpCode . '.');
    }
    if ($body === false || $body === '') {
        throw new RuntimeException($error ?: 'RuneMetrics did not return a response.');
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('RuneMetrics returned invalid JSON.');
    }
    if (isset($decoded['error'])) {
        throw new RuntimeException(is_string($decoded['error']) ? $decoded['error'] : 'RuneMetrics returned an error.');
    }

    if (isset($decoded['quests']) && is_array($decoded['quests'])) {
        return $decoded['quests'];
    }

    // Some mirrors/examples return the list directly.
    if (array_is_list($decoded)) {
        return $decoded;
    }

    throw new RuntimeException('RuneMetrics quest response did not contain a quest list.');
}

function normalise_imported_quest_title(array $quest): string
{
    foreach (['title', 'name', 'questName'] as $key) {
        if (!empty($quest[$key]) && is_string($quest[$key])) {
            return trim($quest[$key]);
        }
    }
    return '';
}

function import_quests_from_runemetrics(string $rsn): array
{
    $quests = fetch_runemetrics_quest_list_for_import($rsn);

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    $pdo = db();

    foreach ($quests as $quest) {
        if (!is_array($quest)) {
            $skipped++;
            continue;
        }

        $title = normalise_imported_quest_title($quest);
        if ($title === '') {
            $skipped++;
            continue;
        }

        try {
            $slug = content_slugify($title);
            $difficultyRaw = $quest['difficulty'] ?? null;
            $difficultyLabel = quest_difficulty_label($difficultyRaw);
            $metadataArray = [
                'runemetrics_imported' => true,
                'last_imported_at_utc' => gmdate('Y-m-d H:i:s'),
                'difficulty' => is_numeric($difficultyRaw) ? (int)$difficultyRaw : $difficultyRaw,
                'difficulty_label' => $difficultyLabel,
                'members' => isset($quest['members']) ? (bool)$quest['members'] : null,
                'quest_points' => isset($quest['questPoints']) ? (int)$quest['questPoints'] : null,
                'user_eligible' => isset($quest['userEligible']) ? (bool)$quest['userEligible'] : null,
                'raw' => $quest,
            ];

            $metadata = json_encode($metadataArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $pdo->prepare("SELECT id, metadata_json FROM content_items WHERE type = 'quest' AND (slug = ? OR name = ?) LIMIT 1");
            $existing->execute([$slug, $title]);
            $existingId = (int)($existing->fetchColumn() ?: 0);

            $category = '';

            if ($existingId > 0) {
                $existingRow = $pdo->prepare('SELECT metadata_json FROM content_items WHERE id = ? LIMIT 1');
                $existingRow->execute([$existingId]);
                $existingMetadata = json_decode((string)($existingRow->fetchColumn() ?: '{}'), true);
                if (!is_array($existingMetadata)) {
                    $existingMetadata = [];
                }
                $mergedMetadata = array_merge($existingMetadata, $metadataArray);
                // Preserve admin-added timeline and series values.
                if (isset($existingMetadata['quest_timeline'])) {
                    $mergedMetadata['quest_timeline'] = $existingMetadata['quest_timeline'];
                }
                if (isset($existingMetadata['quest_series'])) {
                    $mergedMetadata['quest_series'] = $existingMetadata['quest_series'];
                }

                $stmt = $pdo->prepare("UPDATE content_items
                    SET
                        name = IF(name = '' OR name IS NULL, ?, name),
                        metadata_json = ?,
                        is_active = 1,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?");
                $stmt->execute([$title, json_encode($mergedMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $existingId]);
                $updated++;
            } else {
                $stmt = $pdo->prepare("INSERT INTO content_items
                    (type, name, slug, description, category, source_url, icon_url, metadata_json, is_active)
                    VALUES ('quest', ?, ?, '', ?, '', '', ?, 1)");
                $stmt->execute([$title, content_unique_slug($title), $category, $metadata]);
                $created++;
            }
        } catch (Throwable $e) {
            $errors[] = $title . ': ' . $e->getMessage();
        }
    }

    return [
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total_received' => count($quests),
    ];
}


function content_type_configs(): array
{
    try {
        $rows = db()->query('SELECT * FROM content_type_configs ORDER BY sort_order ASC, label ASC')->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }
    $configs = [];
    foreach ($rows as $row) {
        $row['custom_fields'] = json_decode((string)($row['custom_fields_json'] ?? '[]'), true) ?: [];
        $configs[(string)$row['type_slug']] = $row;
    }
    return $configs;
}

function content_type_config(string $type): array
{
    $configs = content_type_configs();
    return $configs[$type] ?? [
        'type_slug' => $type,
        'label' => content_types()[$type] ?? ucfirst($type),
        'description' => '',
        'is_enabled' => 1,
        'allow_skill_requirements' => 0,
        'allow_quest_requirements' => 0,
        'allow_achievement_requirements' => 0,
        'allow_boss_drop_links' => $type === 'boss' ? 1 : 0,
        'custom_fields' => [],
    ];
}

function enabled_content_types(): array
{
    $configs = content_type_configs();
    if (!$configs) return content_types();
    $types = [];
    foreach ($configs as $slug => $config) {
        if ((int)($config['is_enabled'] ?? 1) === 1 && isset(content_types()[$slug])) {
            $types[$slug] = (string)$config['label'];
        }
    }
    return $types;
}

function update_content_type_config(string $type, array $data): void
{
    if (!isset(content_types()[$type])) {
        throw new InvalidArgumentException('Invalid content type.');
    }
    $fields = [];
    foreach ((string)($data['custom_fields_text'] ?? '') === '' ? [] : preg_split('/\R/', (string)$data['custom_fields_text']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$key, $label, $fieldType, $placeholder] = array_pad(array_map('trim', explode('|', $line, 4)), 4, '');
        $key = preg_replace('/[^a-z0-9_]+/', '_', strtolower($key)) ?: '';
        if ($key === '' || $label === '') continue;
        $fields[] = ['key' => $key, 'label' => $label, 'type' => in_array($fieldType, ['text','textarea','url','number'], true) ? $fieldType : 'text', 'placeholder' => $placeholder];
    }

    db()->prepare('INSERT INTO content_type_configs
        (type_slug, label, description, is_enabled, allow_skill_requirements, allow_quest_requirements, allow_achievement_requirements, allow_boss_drop_links, custom_fields_json, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), is_enabled = VALUES(is_enabled), allow_skill_requirements = VALUES(allow_skill_requirements), allow_quest_requirements = VALUES(allow_quest_requirements), allow_achievement_requirements = VALUES(allow_achievement_requirements), allow_boss_drop_links = VALUES(allow_boss_drop_links), custom_fields_json = VALUES(custom_fields_json), sort_order = VALUES(sort_order)')
        ->execute([
            $type,
            trim((string)($data['label'] ?? content_types()[$type])),
            trim((string)($data['description'] ?? '')),
            !empty($data['is_enabled']) ? 1 : 0,
            !empty($data['allow_skill_requirements']) ? 1 : 0,
            !empty($data['allow_quest_requirements']) ? 1 : 0,
            !empty($data['allow_achievement_requirements']) ? 1 : 0,
            !empty($data['allow_boss_drop_links']) ? 1 : 0,
            json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            (int)($data['sort_order'] ?? 0),
        ]);
}

function content_custom_metadata_from_post(string $type, array $post, array $existing = []): array
{
    $metadata = $existing;
    foreach (content_type_config($type)['custom_fields'] ?? [] as $field) {
        $key = (string)($field['key'] ?? '');
        if ($key === '') continue;
        $metadata[$key] = trim((string)($post['meta_' . $key] ?? ($metadata[$key] ?? '')));
    }
    return $metadata;
}

function content_achievement_requirements(int $contentItemId): array
{
    $stmt = db()->prepare('SELECT car.*, ci.name AS required_name FROM content_achievement_requirements car JOIN content_items ci ON ci.id = car.required_content_item_id WHERE car.content_item_id = ? ORDER BY ci.name ASC');
    $stmt->execute([$contentItemId]);
    return $stmt->fetchAll();
}

function add_content_achievement_requirement(int $contentItemId, int $requiredContentItemId, string $notes = ''): void
{
    if ($contentItemId === $requiredContentItemId) {
        throw new InvalidArgumentException('A content item cannot require itself.');
    }
    $required = content_item_by_id($requiredContentItemId);
    if (!$required || ($required['type'] ?? '') !== 'achievement') {
        throw new InvalidArgumentException('Required content must be an achievement.');
    }
    db()->prepare('INSERT IGNORE INTO content_achievement_requirements (content_item_id, required_content_item_id, notes) VALUES (?, ?, ?)')
        ->execute([$contentItemId, $requiredContentItemId, trim($notes)]);
}

function delete_content_achievement_requirement(int $id): void
{
    db()->prepare('DELETE FROM content_achievement_requirements WHERE id = ?')->execute([$id]);
}
