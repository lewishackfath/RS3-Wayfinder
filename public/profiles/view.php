<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : (active_profile_id() ?? 0);
$profile = $profileId ? profile_for_user($profileId, (int)$user['id']) : active_profile();
if (!$profile) {
    redirect('/profiles/index.php');
}

$notice = null;
$syncResult = null;
if (($_GET['refresh'] ?? '') === '1') {
    if (!verify_csrf_token((string)($_GET['csrf_token'] ?? ''))) {
        abort_page(400, 'Invalid security token.');
    }
    $syncResult = runemetrics_sync_profile_if_due($profile);
    $profile = profile_for_user((int)$profile['id'], (int)$user['id']) ?: $profile;
    if (($syncResult['skipped'] ?? false) === true) {
        $mins = (int)ceil(($syncResult['seconds_until_sync'] ?? 0) / 60);
        $notice = 'RuneMetrics was not refreshed because this profile is still on cooldown. Try again in about ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
    } elseif (($syncResult['success'] ?? false) === true) {
        $notice = 'RuneMetrics data refreshed.';
    } else {
        $notice = 'RuneMetrics data could not be fully refreshed. Existing cached data is shown where available.';
    }
} else {
    try {
        $syncResult = runemetrics_sync_profile_if_due($profile);
        $profile = profile_for_user((int)$profile['id'], (int)$user['id']) ?: $profile;
    } catch (Throwable $e) {
        $notice = is_debug() ? $e->getMessage() : 'RuneMetrics sync failed. Existing cached data is shown where available.';
    }
}

$metrics = runemetrics_profile_metrics((int)$profile['id']);
$skills = latest_skills_for_profile((int)$profile['id']);
$activities = recent_activities_for_profile((int)$profile['id'], 12);
$questCounts = quest_status_counts((int)$profile['id']);
$quests = quests_for_profile((int)$profile['id']);
$completedQuests = 0;
$totalQuests = count($quests);
foreach ($quests as $quest) {
    if (str_contains(strtolower((string)$quest['status']), 'complete')) $completedQuests++;
}
$completionPct = $totalQuests > 0 ? round(($completedQuests / $totalQuests) * 100, 1) : null;
page_header('Profile Data');
?>
<div class="page-title-row">
    <div>
        <h1><?= e($profile['rsn']) ?></h1>
        <p class="muted">RuneMetrics profile and quest data. This syncs on page load only when the profile cache is older than 15 minutes.</p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="/profiles/index.php">Back to profiles</a>
        <a class="button" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>&refresh=1&csrf_token=<?= e(csrf_token()) ?>">Refresh data</a>
    </div>
</div>

<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>

<div class="card profile-hero-card">
    <img class="profile-avatar large" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
    <div>
        <h2><?= e($metrics['display_name'] ?? $profile['rsn']) ?></h2>
        <p class="muted"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?> • <?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></p>
        <p class="muted small">Last sync: <?= e(format_sync_age($profile['last_sync_at'] ?? null)) ?><?= isset($metrics['last_sync_status']) ? ' • Status: ' . e((string)$metrics['last_sync_status']) : '' ?></p>
        <?php if (!empty($metrics['last_sync_error'])): ?><p class="alert error small"><?= nl2br(e($metrics['last_sync_error'])) ?></p><?php endif; ?>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>Total Level</span><strong><?= e(format_number_short($metrics['total_level'] ?? null)) ?></strong></div>
    <div class="stat-card"><span>Total XP</span><strong><?= e(format_number_short($metrics['total_xp'] ?? null)) ?></strong></div>
    <div class="stat-card"><span>Combat Level</span><strong><?= e(format_number_short($metrics['combat_level'] ?? null)) ?></strong></div>
    <div class="stat-card"><span>Overall Rank</span><strong><?= e(format_number_short($metrics['overall_rank'] ?? null)) ?></strong></div>
    <div class="stat-card"><span>Quests</span><strong><?= $completionPct === null ? '—' : e($completedQuests . '/' . $totalQuests) ?></strong></div>
    <div class="stat-card"><span>Quest Completion</span><strong><?= $completionPct === null ? '—' : e($completionPct . '%') ?></strong></div>
</div>

<div class="grid two-col-grid">
    <div class="card">
        <h2>Skills</h2>
        <?php if (!$skills): ?>
            <p class="muted">No skill data has been collected yet.</p>
        <?php else: ?>
            <div class="skill-grid">
                <?php foreach ($skills as $skill): ?>
                    <?php $display = rs3_display_level((string)$skill['skill_name'], $skill['level'] ?? null, $skill['xp'] ?? null); ?>
                    <div class="skill-row<?= $display['is_virtual'] ? ' is-virtual' : '' ?>">
                        <span><?= e($skill['skill_name']) ?></span>
                        <strong>
                            <?= e((string)$display['display_level']) ?>
                            <?php if ($display['is_virtual']): ?>
                                <em title="Reported level <?= e((string)$display['reported_level']) ?>, virtual level <?= e((string)$display['virtual_level']) ?>">virtual</em>
                            <?php endif; ?>
                        </strong>
                        <small>
                            <?= e(format_number_short($skill['xp'])) ?> XP
                            <?php if ($display['is_virtual']): ?> • reported <?= e((string)$display['reported_level']) ?><?php endif; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Quest Status</h2>
        <?php if (!$questCounts): ?>
            <p class="muted">No quest data has been collected yet.</p>
        <?php else: ?>
            <table class="table compact-table">
                <thead><tr><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($questCounts as $row): ?>
                    <tr><td><?= e($row['status']) ?></td><td><?= e($row['total']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2>Recent RuneMetrics Activity</h2>
    <?php if (!$activities): ?>
        <p class="muted">No recent activities have been collected yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Date</th><th>Activity</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr>
                    <td><?= e($activity['activity_date_raw'] ?: $activity['activity_date_utc'] ?: '—') ?></td>
                    <td><?= e($activity['activity_text'] ?: '—') ?></td>
                    <td><?= e($activity['activity_details'] ?: '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Quest List</h2>
    <?php if (!$quests): ?>
        <p class="muted">No quest list has been collected yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Quest</th><th>Status</th><th>Difficulty</th><th>QP</th></tr></thead>
            <tbody>
            <?php foreach ($quests as $quest): ?>
                <tr>
                    <td><?= e($quest['quest_title']) ?></td>
                    <td><?= e($quest['status'] ?: 'Unknown') ?></td>
                    <td><?= e($quest['difficulty'] ?: '—') ?></td>
                    <td><?= e($quest['quest_points'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
