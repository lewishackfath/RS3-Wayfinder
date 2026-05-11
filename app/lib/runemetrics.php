<?php
declare(strict_types=1);

const RUNEMETRICS_SYNC_COOLDOWN_SECONDS = 900;

function runemetrics_skill_names(): array
{
    return [
        0 => 'Attack', 1 => 'Defence', 2 => 'Strength', 3 => 'Constitution', 4 => 'Ranged',
        5 => 'Prayer', 6 => 'Magic', 7 => 'Cooking', 8 => 'Woodcutting', 9 => 'Fletching',
        10 => 'Fishing', 11 => 'Firemaking', 12 => 'Crafting', 13 => 'Smithing', 14 => 'Mining',
        15 => 'Herblore', 16 => 'Agility', 17 => 'Thieving', 18 => 'Slayer', 19 => 'Farming',
        20 => 'Runecrafting', 21 => 'Hunter', 22 => 'Construction', 23 => 'Summoning', 24 => 'Dungeoneering',
        25 => 'Divination', 26 => 'Invention', 27 => 'Archaeology', 28 => 'Necromancy',
    ];
}

function runemetrics_profile_url(string $rsn): string
{
    return 'https://apps.runescape.com/runemetrics/profile/profile?user=' . rawurlencode($rsn) . '&activities=20';
}

function runemetrics_quests_url(string $rsn): string
{
    return 'https://apps.runescape.com/runemetrics/quests?user=' . rawurlencode($rsn);
}

function runemetrics_profile_metrics(int $profileId): ?array
{
    $stmt = db()->prepare('SELECT * FROM player_profile_metrics WHERE profile_id = ? LIMIT 1');
    $stmt->execute([$profileId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function runemetrics_sync_due(array $profile): bool
{
    $last = $profile['last_sync_at'] ?? null;
    if (!$last) {
        return true;
    }
    return (time() - strtotime((string)$last)) >= RUNEMETRICS_SYNC_COOLDOWN_SECONDS;
}

function runemetrics_seconds_until_sync(array $profile): int
{
    $last = $profile['last_sync_at'] ?? null;
    if (!$last) {
        return 0;
    }
    $remaining = RUNEMETRICS_SYNC_COOLDOWN_SECONDS - (time() - strtotime((string)$last));
    return max(0, $remaining);
}

function runemetrics_sync_profile_if_due(array $profile): array
{
    if (!runemetrics_sync_due($profile)) {
        return ['skipped' => true, 'reason' => 'cooldown', 'seconds_until_sync' => runemetrics_seconds_until_sync($profile)];
    }
    return runemetrics_sync_profile((int)$profile['id']);
}

function runemetrics_sync_profile(int $profileId): array
{
    $profile = profile_by_id($profileId);
    if (!$profile) {
        throw new InvalidArgumentException('Profile not found.');
    }

    $pdo = db();
    $rsn = (string)$profile['rsn'];
    $attemptAt = gmdate('Y-m-d H:i:s');
    $overall = ['profile' => null, 'quests' => null, 'errors' => []];

    $profileResult = runemetrics_fetch_json($profileId, 'profile', runemetrics_profile_url($rsn));
    $overall['profile'] = $profileResult;

    if ($profileResult['ok'] && is_array($profileResult['json'])) {
        runemetrics_store_profile_payload($profileId, $profileResult['json'], $attemptAt);
    } else {
        $overall['errors'][] = $profileResult['error'] ?: 'Profile data could not be loaded.';
    }

    $questResult = runemetrics_fetch_json($profileId, 'quests', runemetrics_quests_url($rsn));
    $overall['quests'] = $questResult;

    if ($questResult['ok'] && is_array($questResult['json'])) {
        runemetrics_store_quest_payload($profileId, $questResult['json'], $attemptAt);
    } else {
        $overall['errors'][] = $questResult['error'] ?: 'Quest data could not be loaded.';
    }

    $success = $profileResult['ok'] || $questResult['ok'];
    $status = $success ? (empty($overall['errors']) ? 'success' : 'partial') : 'failed';
    $errorText = empty($overall['errors']) ? null : implode("\n", array_unique(array_filter($overall['errors'])));

    $pdo->prepare("INSERT INTO player_profile_metrics (profile_id, last_sync_attempt_at, last_successful_sync_at, last_sync_status, last_sync_error)
        VALUES (?, UTC_TIMESTAMP(), IF(?, UTC_TIMESTAMP(), NULL), ?, ?)
        ON DUPLICATE KEY UPDATE
            last_sync_attempt_at = UTC_TIMESTAMP(),
            last_successful_sync_at = IF(?, UTC_TIMESTAMP(), last_successful_sync_at),
            last_sync_status = VALUES(last_sync_status),
            last_sync_error = VALUES(last_sync_error)")
        ->execute([$profileId, $success ? 1 : 0, $status, $errorText, $success ? 1 : 0]);

    $pdo->prepare('UPDATE player_profiles SET last_sync_at = UTC_TIMESTAMP(), runemetrics_public = ? WHERE id = ?')
        ->execute([$success ? 1 : 0, $profileId]);

    $overall['success'] = $success;
    $overall['status'] = $status;
    return $overall;
}

function runemetrics_fetch_json(int $profileId, string $endpoint, string $url): array
{
    $headers = [
        'Accept: application/json,text/plain,*/*',
        'User-Agent: RS3-Wayfinder/0.1 (+https://rs3wayfinder.local)'
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
            CURLOPT_TIMEOUT => 15,
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
                'timeout' => 15,
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

    $decoded = null;
    if ($body !== false && $body !== '') {
        $decoded = json_decode((string)$body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = 'Invalid JSON returned: ' . json_last_error_msg();
        }
    }

    if ($httpCode !== null && $httpCode >= 400) {
        $error = 'RuneMetrics returned HTTP ' . $httpCode . '.';
    }

    if (is_array($decoded) && isset($decoded['error'])) {
        $error = is_string($decoded['error']) ? $decoded['error'] : 'RuneMetrics returned an error.';
    }

    $ok = ($error === null && $body !== false && is_array($decoded));

    db()->prepare('INSERT INTO runemetrics_fetches (profile_id, endpoint, request_url, http_status, was_successful, error_message, response_json, fetched_at) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
        ->execute([$profileId, $endpoint, $url, $httpCode, $ok ? 1 : 0, $error, $body === false ? null : (string)$body]);

    return ['ok' => $ok, 'http_status' => $httpCode, 'json' => $decoded, 'error' => $error];
}

function runemetrics_store_profile_payload(int $profileId, array $payload, string $fetchedAt): void
{
    $pdo = db();

    $stmt = $pdo->prepare("INSERT INTO player_profile_metrics (
            profile_id, display_name, overall_rank, total_level, total_xp, combat_level,
            melee_xp, magic_xp, ranged_xp, quests_started, quests_complete, quests_not_started,
            logged_in, last_profile_fetch_at, last_successful_sync_at, last_sync_status, last_sync_error
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), 'success', NULL)
        ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            overall_rank = VALUES(overall_rank),
            total_level = VALUES(total_level),
            total_xp = VALUES(total_xp),
            combat_level = VALUES(combat_level),
            melee_xp = VALUES(melee_xp),
            magic_xp = VALUES(magic_xp),
            ranged_xp = VALUES(ranged_xp),
            quests_started = VALUES(quests_started),
            quests_complete = VALUES(quests_complete),
            quests_not_started = VALUES(quests_not_started),
            logged_in = VALUES(logged_in),
            last_profile_fetch_at = VALUES(last_profile_fetch_at),
            last_successful_sync_at = UTC_TIMESTAMP(),
            last_sync_status = 'success',
            last_sync_error = NULL");

    $stmt->execute([
        $profileId,
        string_or_null($payload['name'] ?? null),
        int_or_null($payload['rank'] ?? null),
        int_or_null($payload['totalskill'] ?? null),
        int_or_null($payload['totalxp'] ?? null),
        int_or_null($payload['combatlevel'] ?? null),
        int_or_null($payload['melee'] ?? null),
        int_or_null($payload['magic'] ?? null),
        int_or_null($payload['ranged'] ?? null),
        int_or_null($payload['questsstarted'] ?? null),
        int_or_null($payload['questscomplete'] ?? null),
        int_or_null($payload['questsnotstarted'] ?? null),
        isset($payload['loggedIn']) ? ((bool)$payload['loggedIn'] ? 1 : 0) : null,
        $fetchedAt,
    ]);

    if (!empty($payload['skillvalues']) && is_array($payload['skillvalues'])) {
        runemetrics_store_skills($profileId, $payload['skillvalues'], $fetchedAt);
    }

    if (!empty($payload['activities']) && is_array($payload['activities'])) {
        runemetrics_store_activities($profileId, $payload['activities']);
    }
}

function runemetrics_store_skills(int $profileId, array $skills, string $fetchedAt): void
{
    $pdo = db();
    $names = runemetrics_skill_names();
    $snapshot = $pdo->prepare('INSERT IGNORE INTO player_skill_snapshots (profile_id, skill_id, skill_name, level, xp, rank, fetched_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $latest = $pdo->prepare('INSERT INTO player_latest_skills (profile_id, skill_id, skill_name, level, xp, rank, fetched_at) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE skill_name = VALUES(skill_name), level = VALUES(level), xp = VALUES(xp), rank = VALUES(rank), fetched_at = VALUES(fetched_at)');

    foreach ($skills as $skill) {
        if (!is_array($skill)) continue;
        $id = int_or_null($skill['id'] ?? null);
        if ($id === null) continue;
        $name = $names[$id] ?? ('Skill ' . $id);
        $level = int_or_null($skill['level'] ?? null);
        // RuneMetrics skill XP is returned multiplied by 10. Store parsed data as real XP.
        $xp = runemetrics_normalise_skill_xp($skill['xp'] ?? null);
        $rank = int_or_null($skill['rank'] ?? null);
        $snapshot->execute([$profileId, $id, $name, $level, $xp, $rank, $fetchedAt]);
        $latest->execute([$profileId, $id, $name, $level, $xp, $rank, $fetchedAt]);
    }
}

function runemetrics_store_activities(int $profileId, array $activities): void
{
    $stmt = db()->prepare('INSERT INTO player_activity_logs (profile_id, activity_date_raw, activity_date_utc, activity_text, activity_details, raw_json, source_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP(), activity_text = VALUES(activity_text), activity_details = VALUES(activity_details), raw_json = VALUES(raw_json)');

    foreach ($activities as $activity) {
        if (!is_array($activity)) continue;
        $text = string_or_null($activity['text'] ?? null);
        $details = string_or_null($activity['details'] ?? null);
        $dateRaw = string_or_null($activity['date'] ?? null);
        $dateUtc = runemetrics_parse_activity_date($dateRaw);
        $raw = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', implode('|', [$dateRaw ?? '', $text ?? '', $details ?? '', $raw ?: '']));
        $stmt->execute([$profileId, $dateRaw, $dateUtc, $text, $details, $raw ?: null, $hash]);
    }
}

function runemetrics_store_quest_payload(int $profileId, array $payload, string $fetchedAt): void
{
    $quests = $payload;
    if (isset($payload['quests']) && is_array($payload['quests'])) {
        $quests = $payload['quests'];
    }

    $stmt = db()->prepare('INSERT INTO player_quest_statuses (profile_id, quest_title, status, difficulty, quest_points, raw_json, fetched_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), difficulty = VALUES(difficulty), quest_points = VALUES(quest_points), raw_json = VALUES(raw_json), fetched_at = VALUES(fetched_at)');

    $completed = 0;
    $started = 0;
    $notStarted = 0;

    foreach ($quests as $quest) {
        if (!is_array($quest)) continue;
        $title = string_or_null($quest['title'] ?? $quest['name'] ?? null);
        if (!$title) continue;
        $status = string_or_null($quest['status'] ?? null);
        $difficulty = string_or_null($quest['difficulty'] ?? null);
        $questPoints = int_or_null($quest['questPoints'] ?? $quest['questpoints'] ?? $quest['points'] ?? null);
        $raw = json_encode($quest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $stmt->execute([$profileId, $title, $status, $difficulty, $questPoints, $raw ?: null, $fetchedAt]);

        $normalStatus = strtolower((string)$status);
        if (str_contains($normalStatus, 'complete')) $completed++;
        elseif (str_contains($normalStatus, 'started')) $started++;
        else $notStarted++;
    }

    db()->prepare("INSERT INTO player_profile_metrics (profile_id, quests_complete, quests_started, quests_not_started, last_quest_fetch_at, last_successful_sync_at)
        VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE quests_complete = VALUES(quests_complete), quests_started = VALUES(quests_started), quests_not_started = VALUES(quests_not_started), last_quest_fetch_at = VALUES(last_quest_fetch_at), last_successful_sync_at = UTC_TIMESTAMP()")
        ->execute([$profileId, $completed ?: null, $started ?: null, $notStarted ?: null, $fetchedAt]);
}

function runemetrics_parse_activity_date(?string $dateRaw): ?string
{
    if (!$dateRaw) return null;
    $dateRaw = trim($dateRaw);
    $formats = ['d-M-Y H:i', 'd-M-Y H:i:s', 'd-M-y H:i', 'j-M-Y H:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $dateRaw, new DateTimeZone('UTC'));
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    $ts = strtotime($dateRaw . ' UTC');
    return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
}

function latest_skills_for_profile(int $profileId): array
{
    $stmt = db()->prepare('SELECT * FROM player_latest_skills WHERE profile_id = ? ORDER BY skill_id ASC');
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function recent_activities_for_profile(int $profileId, int $limit = 10): array
{
    $stmt = db()->prepare('SELECT * FROM player_activity_logs WHERE profile_id = ? ORDER BY COALESCE(activity_date_utc, first_seen_at) DESC LIMIT ' . max(1, min(50, $limit)));
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function quests_for_profile(int $profileId, ?string $status = null): array
{
    if ($status !== null && $status !== '') {
        $stmt = db()->prepare('SELECT * FROM player_quest_statuses WHERE profile_id = ? AND status = ? ORDER BY quest_title ASC');
        $stmt->execute([$profileId, $status]);
        return $stmt->fetchAll();
    }
    $stmt = db()->prepare('SELECT * FROM player_quest_statuses WHERE profile_id = ? ORDER BY quest_title ASC');
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function quest_status_counts(int $profileId): array
{
    $stmt = db()->prepare('SELECT COALESCE(status, "Unknown") AS status, COUNT(*) AS total FROM player_quest_statuses WHERE profile_id = ? GROUP BY COALESCE(status, "Unknown") ORDER BY total DESC');
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function runemetrics_last_fetches(int $profileId): array
{
    $stmt = db()->prepare('SELECT * FROM runemetrics_fetches WHERE profile_id = ? ORDER BY fetched_at DESC LIMIT 10');
    $stmt->execute([$profileId]);
    return $stmt->fetchAll();
}

function runemetrics_normalise_skill_xp(mixed $value): ?int
{
    $raw = int_or_null($value);
    if ($raw === null) return null;

    // RuneMetrics skillvalues[].xp is actual XP * 10.
    // Keep raw API JSON untouched in runemetrics_fetches, but store parsed skill XP as the true value.
    return intdiv($raw, 10);
}

function int_or_null(mixed $value): ?int
{
    if ($value === null || $value === '') return null;
    if (is_string($value)) $value = str_replace(',', '', $value);
    return is_numeric($value) ? (int)$value : null;
}

function string_or_null(mixed $value): ?string
{
    if ($value === null) return null;
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function format_number_short(mixed $value): string
{
    if ($value === null || $value === '') return '—';
    return number_format((float)$value, 0);
}

function format_sync_age(?string $datetime): string
{
    if (!$datetime) return 'Never';
    $seconds = max(0, time() - strtotime($datetime));
    if ($seconds < 60) return $seconds . 's ago';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ago';
    if ($seconds < 86400) return floor($seconds / 3600) . 'h ago';
    return floor($seconds / 86400) . 'd ago';
}
