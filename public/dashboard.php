<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$user = current_user();
page_header('Dashboard');
$roles = roles_for_user((int)$user['id']);
$profiles = profiles_for_user((int)$user['id']);
$active = active_profile();
$syncNotice = null;
$activeMetrics = null;
if ($active) {
    try {
        $sync = runemetrics_sync_profile_if_due($active);
        $active = active_profile();
        $activeMetrics = runemetrics_profile_metrics((int)$active['id']);
        if (($sync['success'] ?? false) === true) {
            $syncNotice = 'RuneMetrics data refreshed for your active profile.';
        }
    } catch (Throwable $e) {
        $syncNotice = is_debug() ? $e->getMessage() : 'RuneMetrics sync failed. Cached data is shown where available.';
        $activeMetrics = runemetrics_profile_metrics((int)$active['id']);
    }
}
?>
<div class="card">
    <h1>Welcome, <?= e($user['global_name'] ?: $user['username']) ?></h1>
    <p class="muted">Your account is active. Add RSN profiles and Wayfinder will collect RuneMetrics profile and quest data when each profile is viewed.</p>
    <?php if ($syncNotice): ?><div class="notice"><?= e($syncNotice) ?></div><?php endif; ?>
    <h2>Your roles</h2>
    <p><?php foreach ($roles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?></p>
    <h2>Active profile</h2>
    <?php if ($active): ?>
        <div class="active-profile-panel">
            <img class="profile-avatar large" src="<?= e(runescape_avatar_url((string)$active['rsn'])) ?>" alt="Avatar for <?= e($active['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
            <div>
                <h3><?= e($active['rsn']) ?></h3>
                <p class="muted"><?= e(account_type_options()[$active['account_type']] ?? $active['account_type']) ?> • <?= e(visibility_options()[$active['visibility']] ?? $active['visibility']) ?></p>
                <?php if ($activeMetrics): ?>
                    <p class="muted">Total level <?= e(format_number_short($activeMetrics['total_level'] ?? null)) ?> • Combat <?= e(format_number_short($activeMetrics['combat_level'] ?? null)) ?> • Last sync <?= e(format_sync_age($active['last_sync_at'] ?? null)) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <p><a class="button" href="/profiles/view.php?id=<?= (int)$active['id'] ?>">View profile data</a> <a class="button secondary" href="/journeys/index.php">Browse journeys</a> <a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>
    <?php elseif ($profiles): ?>
        <p><?php foreach ($profiles as $profile): ?><span class="badge"><?= e($profile['rsn']) ?><?= ((int)$profile['is_primary'] === 1) ? ' • Primary' : '' ?></span><?php endforeach; ?></p>
        <p><a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>
    <?php else: ?>
        <p class="muted">No RSNs are attached yet.</p>
        <p><a class="button" href="/profiles/new.php">Add your first RSN</a></p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
