<?php
declare(strict_types=1);

function content_types(): array
{
    return [
        'quest' => 'Quest',
        'achievement' => 'Achievement',
        'task' => 'Task',
        'boss' => 'Boss',
        'drop' => 'Drop / Item',
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
    $sql .= ' ORDER BY FIELD(type, "quest","achievement","task","boss","drop","unlock","item"), name ASC LIMIT 500';

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

    if ($type === 'drop') {
        upsert_drop_item($id, $name, $sourceUrl, $iconUrl, '');
    }

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

    if ($type === 'drop') {
        upsert_drop_item($id, $name, $sourceUrl, $iconUrl, '');
    }
}

function upsert_drop_item(int $contentItemId, string $itemName, string $wikiUrl, string $iconUrl, string $notes): void
{
    db()->prepare('INSERT INTO drop_items (content_item_id, item_name, wiki_url, icon_url, notes)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE item_name = VALUES(item_name), wiki_url = VALUES(wiki_url), icon_url = VALUES(icon_url), notes = VALUES(notes), updated_at = UTC_TIMESTAMP()')
        ->execute([$contentItemId, trim($itemName), trim($wikiUrl), trim($iconUrl), trim($notes)]);
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
    if (!$drop || !in_array($drop['type'], ['drop','item'], true)) {
        throw new InvalidArgumentException('Drop content item is invalid.');
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
            $metadata = json_encode([
                'runemetrics_imported' => true,
                'last_imported_at_utc' => gmdate('Y-m-d H:i:s'),
                'raw' => $quest,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $existing = $pdo->prepare("SELECT id FROM content_items WHERE type = 'quest' AND (slug = ? OR name = ?) LIMIT 1");
            $existing->execute([$slug, $title]);
            $existingId = (int)($existing->fetchColumn() ?: 0);

            $category = '';
            if (!empty($quest['difficulty'])) {
                $category = (string)$quest['difficulty'];
            } elseif (!empty($quest['status'])) {
                $category = 'Imported';
            }

            if ($existingId > 0) {
                // Preserve admin-managed fields wherever possible. Only fill blanks and refresh import metadata.
                $stmt = $pdo->prepare("UPDATE content_items
                    SET
                        name = IF(name = '' OR name IS NULL, ?, name),
                        category = IF(category = '' OR category IS NULL, ?, category),
                        metadata_json = ?,
                        is_active = 1,
                        updated_at = UTC_TIMESTAMP()
                    WHERE id = ?");
                $stmt->execute([$title, $category, $metadata, $existingId]);
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

