<?php
declare(strict_types=1);

function wayfinder_recommendations_for_profile(int $profileId, int $limit = 6): array
{
    $recommendations = [];
    $seenRecommendationKeys = [];
    $enabledJourneys = journeys_for_profile($profileId);

    foreach ($enabledJourneys as $journey) {
        $progress = evaluate_journey_progress($profileId, (int)$journey['id']);

        foreach (($progress['recommended'] ?? []) as $step) {
            if (count($recommendations) >= $limit) {
                break 2;
            }

            $rec = recommendation_from_step($profileId, $journey, $step, 'next_step');
            $dedupeKey = strtolower(trim((string)$rec['journey_id'])) . '|' . strtolower(trim((string)$rec['title'])) . '|' . strtolower(trim((string)$rec['summary'])) . '|' . strtolower(trim((string)$rec['detail']));
            if (isset($seenRecommendationKeys[$dedupeKey])) {
                continue;
            }
            $seenRecommendationKeys[$dedupeKey] = true;
            $recommendations[] = $rec;
        }

        foreach (($progress['steps'] ?? []) as $step) {
            if (count($recommendations) >= $limit) {
                break 2;
            }

            if (!empty($step['is_completed']) || empty($step['is_locked']) || empty($step['requires_step_id'])) {
                continue;
            }

            $required = step_by_id((int)$step['requires_step_id']);
            if ($required) {
                $recommendations[] = [
                    'type' => 'locked_step',
                    'priority' => 70,
                    'title' => 'Unlock: ' . (string)$step['title'],
                    'summary' => 'This step is locked behind another milestone.',
                    'detail' => 'Complete “' . (string)$required['title'] . '” first.',
                    'journey_id' => (int)$journey['id'],
                    'journey_name' => (string)$journey['name'],
                    'journey_icon' => (string)($journey['icon'] ?: '🧭'),
                    'step_id' => (int)$step['id'],
                    'cta_label' => 'View journey',
                    'cta_url' => '/journeys/view.php?id=' . (int)$journey['id'] . '#step-' . (int)$required['id'],
                ];
            }
        }
    }

    if (count($recommendations) < $limit) {
        foreach (skill_gap_recommendations($profileId, $limit - count($recommendations)) as $rec) {
            $recommendations[] = $rec;
        }
    }

    usort($recommendations, fn(array $a, array $b): int => ((int)$b['priority'] <=> (int)$a['priority']));

    $uniqueRecommendations = [];
    $seenFinalKeys = [];
    foreach ($recommendations as $rec) {
        $key = strtolower(trim((string)($rec['journey_id'] ?? 'global'))) . '|' . strtolower(trim((string)($rec['title'] ?? ''))) . '|' . strtolower(trim((string)($rec['summary'] ?? ''))) . '|' . strtolower(trim((string)($rec['detail'] ?? '')));
        if (isset($seenFinalKeys[$key])) {
            continue;
        }
        $seenFinalKeys[$key] = true;
        $uniqueRecommendations[] = $rec;
    }

    return array_slice($uniqueRecommendations, 0, $limit);
}

function recommendation_from_step(int $profileId, array $journey, array $step, string $type = 'next_step'): array
{
    $ruleType = (string)($step['auto_rule_type'] ?? '');
    $title = (string)$step['title'];
    $summary = 'Continue your enabled journey.';
    $detail = rule_summary($step);
    $priority = !empty($step['is_optional']) ? 55 : 90;

    if ($ruleType === 'skill_level') {
        $gap = skill_gap_for_step($profileId, $step);
        if ($gap) {
            $summary = 'You are close to a skill requirement.';
            $detail = $gap['current_level'] . ' / ' . $gap['target_level'] . ' ' . $gap['skill_name'];
            if ($gap['levels_remaining'] > 0) {
                $detail .= ' — ' . $gap['levels_remaining'] . ' level' . ($gap['levels_remaining'] === 1 ? '' : 's') . ' remaining';
            }
            $priority = 95 - min(30, max(0, $gap['levels_remaining']));
        }
    } elseif ($ruleType === 'quest_complete') {
        $summary = 'A quest completion is blocking progress.';
        $questTitle = (string)($step['rule_quest_title'] ?? '');
        $status = quest_status_for_profile($profileId, $questTitle);
        $detail = $questTitle !== '' ? $questTitle . ($status ? ' — current status: ' . $status : '') : $detail;
        $priority = 88;
    } elseif ((string)$step['completion_mode'] === 'manual_only') {
        $summary = !empty($step['is_optional']) ? 'Optional goal available.' : 'Manual milestone available.';
        $detail = !empty($step['description']) ? (string)$step['description'] : 'Tick this off once you have completed it in-game.';
        $priority = !empty($step['is_optional']) ? 50 : 80;
    }

    return [
        'type' => $type,
        'priority' => $priority,
        'title' => $title,
        'summary' => $summary,
        'detail' => $detail,
        'journey_id' => (int)$journey['id'],
        'journey_name' => (string)$journey['name'],
        'journey_icon' => (string)($journey['icon'] ?: '🧭'),
        'step_id' => (int)$step['id'],
        'cta_label' => 'Go to step',
        'cta_url' => '/journeys/view.php?id=' . (int)$journey['id'] . '#step-' . (int)$step['id'],
    ];
}

function skill_gap_for_step(int $profileId, array $step): ?array
{
    if ((string)($step['auto_rule_type'] ?? '') !== 'skill_level') {
        return null;
    }

    $skillName = trim((string)($step['rule_skill_name'] ?? ''));
    $targetLevel = (int)($step['rule_level'] ?? 0);
    if ($skillName === '' || $targetLevel <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT level, xp FROM player_latest_skills WHERE profile_id = ? AND LOWER(skill_name) = LOWER(?) LIMIT 1');
    $stmt->execute([$profileId, $skillName]);
    $skill = $stmt->fetch();

    $currentLevel = 0;
    if ($skill) {
        $display = rs3_display_level($skillName, $skill['level'] ?? null, $skill['xp'] ?? null);
        $currentLevel = (int)$display['display_level'];
    }

    return [
        'skill_name' => $skillName,
        'target_level' => $targetLevel,
        'current_level' => $currentLevel,
        'levels_remaining' => max(0, $targetLevel - $currentLevel),
    ];
}

function quest_status_for_profile(int $profileId, string $questTitle): ?string
{
    $questTitle = trim($questTitle);
    if ($questTitle === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT status FROM player_quest_statuses WHERE profile_id = ? AND LOWER(quest_title) = LOWER(?) LIMIT 1');
    $stmt->execute([$profileId, $questTitle]);
    $status = $stmt->fetchColumn();
    return $status === false ? null : (string)$status;
}

function skill_gap_recommendations(int $profileId, int $limit = 3): array
{
    if ($limit <= 0) {
        return [];
    }

    $enabledJourneys = journeys_for_profile($profileId);
    $gaps = [];

    foreach ($enabledJourneys as $journey) {
        foreach (steps_for_journey((int)$journey['id']) as $step) {
            if ((string)($step['auto_rule_type'] ?? '') !== 'skill_level') {
                continue;
            }

            if (step_auto_complete($profileId, $step)) {
                continue;
            }

            $gap = skill_gap_for_step($profileId, $step);
            if (!$gap || $gap['levels_remaining'] <= 0) {
                continue;
            }

            $gaps[] = [
                'type' => 'skill_gap',
                'priority' => 60 - min(35, $gap['levels_remaining']),
                'title' => 'Train ' . $gap['skill_name'] . ' to ' . $gap['target_level'],
                'summary' => 'This skill requirement appears in an enabled journey.',
                'detail' => $gap['current_level'] . ' / ' . $gap['target_level'] . ' — ' . $gap['levels_remaining'] . ' level' . ($gap['levels_remaining'] === 1 ? '' : 's') . ' remaining',
                'journey_id' => (int)$journey['id'],
                'journey_name' => (string)$journey['name'],
                'journey_icon' => (string)($journey['icon'] ?: '🧭'),
                'step_id' => (int)$step['id'],
                'cta_label' => 'View requirement',
                'cta_url' => '/journeys/view.php?id=' . (int)$journey['id'] . '#step-' . (int)$step['id'],
                'levels_remaining' => $gap['levels_remaining'],
            ];
        }
    }

    usort($gaps, function (array $a, array $b): int {
        $aRemaining = (int)($a['levels_remaining'] ?? 999);
        $bRemaining = (int)($b['levels_remaining'] ?? 999);
        if ($aRemaining === $bRemaining) {
            return ((int)$b['priority'] <=> (int)$a['priority']);
        }
        return $aRemaining <=> $bRemaining;
    });

    return array_slice($gaps, 0, $limit);
}

function wayfinder_profile_analysis(int $profileId): array
{
    $enabledJourneys = journeys_for_profile($profileId);
    $totalRequired = 0;
    $completedRequired = 0;
    $available = 0;
    $locked = 0;
    $optional = 0;

    foreach ($enabledJourneys as $journey) {
        $progress = evaluate_journey_progress($profileId, (int)$journey['id']);
        $totalRequired += (int)($progress['required_total'] ?? 0);
        $completedRequired += (int)($progress['required_completed'] ?? 0);

        foreach (($progress['steps'] ?? []) as $step) {
            if (!empty($step['is_optional'])) {
                $optional++;
            }
            if (!empty($step['is_locked']) && empty($step['is_completed'])) {
                $locked++;
            } elseif (empty($step['is_completed'])) {
                $available++;
            }
        }
    }

    return [
        'enabled_journeys' => count($enabledJourneys),
        'required_total' => $totalRequired,
        'required_completed' => $completedRequired,
        'overall_percent' => $totalRequired ? round(($completedRequired / $totalRequired) * 100, 1) : 0,
        'available_steps' => $available,
        'locked_steps' => $locked,
        'optional_steps' => $optional,
    ];
}



function recommended_journeys_for_profile(int $profileId, int $limit = 4): array
{
    $profile = profile_by_id($profileId);
    if (!$profile) {
        return [];
    }

    $enabled = journeys_for_profile($profileId);
    $enabledIds = array_fill_keys(array_map(fn($j) => (int)$j['id'], $enabled), true);
    $interestTags = profile_interest_tags($profileId);
    $interestIds = array_map(fn($t) => (int)$t['id'], $interestTags);
    $interestSlugs = array_map(fn($t) => (string)$t['slug'], $interestTags);

    $metrics = runemetrics_profile_metrics($profileId);
    $totalLevel = (int)($metrics['total_level'] ?? 0);
    $combatLevel = (int)($metrics['combat_level'] ?? 0);
    $questComplete = (int)($metrics['quests_complete'] ?? 0);

    $results = [];
    foreach (journeys_with_tags(true) as $journey) {
        $journeyId = (int)$journey['id'];
        if (isset($enabledIds[$journeyId])) {
            continue;
        }

        $tags = $journey['tags'] ?? [];
        $tagIds = array_map(fn($t) => (int)$t['id'], $tags);
        $tagSlugs = array_map(fn($t) => (string)$t['slug'], $tags);
        $tagNames = array_map(fn($t) => (string)$t['name'], $tags);

        $score = 10;
        $reasons = [];

        $matches = array_intersect($interestIds, $tagIds);
        if ($matches) {
            $score += 50 + (10 * count($matches));
            $matchedNames = [];
            foreach ($tags as $tag) {
                if (in_array((int)$tag['id'], $matches, true)) {
                    $matchedNames[] = (string)$tag['name'];
                }
            }
            $reasons[] = 'Matches your interests: ' . implode(', ', $matchedNames);
        }

        $accountType = (string)($profile['account_type'] ?? 'main');
        if (str_contains($accountType, 'ironman') && in_array('ironman', $tagSlugs, true)) {
            $score += 35;
            $reasons[] = 'Matches your Ironman account type.';
        }

        if ($totalLevel > 0) {
            if ($totalLevel < 1000 && (in_array('new-player', $tagSlugs, true) || in_array('skilling', $tagSlugs, true))) {
                $score += 25;
                $reasons[] = 'Good fit for your current total level.';
            } elseif ($totalLevel >= 1000 && $totalLevel < 2400 && (in_array('questing', $tagSlugs, true) || in_array('pvm', $tagSlugs, true))) {
                $score += 20;
                $reasons[] = 'Useful for mid-game account progression.';
            } elseif ($totalLevel >= 2400 && in_array('completionist', $tagSlugs, true)) {
                $score += 30;
                $reasons[] = 'Good fit for late-game account completion.';
            }
        }

        if ($combatLevel >= 80 && in_array('pvm', $tagSlugs, true)) {
            $score += 20;
            $reasons[] = 'Your combat level suggests PvM paths may be useful.';
        }

        if ($questComplete < 150 && in_array('questing', $tagSlugs, true)) {
            $score += 15;
            $reasons[] = 'Questing paths can unlock major account upgrades.';
        }

        if (!$reasons && $interestTags) {
            $score -= 5;
            $reasons[] = 'A general journey you have not enabled yet.';
        } elseif (!$reasons) {
            $reasons[] = 'Popular starting point once you choose interests.';
        }

        $results[] = [
            'journey' => $journey,
            'score' => $score,
            'reasons' => $reasons,
            'tags' => $tagNames,
        ];
    }

    usort($results, fn(array $a, array $b): int => ($b['score'] <=> $a['score']));

    return array_slice($results, 0, $limit);
}

