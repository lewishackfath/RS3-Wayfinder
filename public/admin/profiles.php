<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('profiles.view');

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('profiles.delete');
    require_csrf();
    $profileId = (int)($_POST['profile_id'] ?? 0);
    if ($profileId > 0) {
        $ownerStmt = $pdo->prepare('SELECT user_id FROM player_profiles WHERE id = ? LIMIT 1');
        $ownerStmt->execute([$profileId]);
        $ownerId = (int)($ownerStmt->fetchColumn() ?: 0);
        $pdo->prepare('DELETE FROM player_profiles WHERE id = ?')->execute([$profileId]);
        if ($ownerId > 0) {
            ensure_user_has_primary_profile($ownerId);
        }
    }
    redirect('/admin/profiles.php?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'account_type' => $_GET['account_type'] ?? null,
        'visibility' => $_GET['visibility'] ?? null,
        'sync' => $_GET['sync'] ?? null,
    ], fn($value) => $value !== null && $value !== '')));
}

$q = trim((string)($_GET['q'] ?? ''));
$accountType = (string)($_GET['account_type'] ?? '');
$visibility = (string)($_GET['visibility'] ?? '');
$sync = (string)($_GET['sync'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(pp.rsn LIKE ? OR pp.rsn_normalised LIKE ? OR u.username LIKE ? OR u.global_name LIKE ? OR u.discord_id LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($accountType !== '' && isset(account_type_options()[$accountType])) {
    $where[] = 'pp.account_type = ?';
    $params[] = $accountType;
}

if ($visibility !== '' && isset(visibility_options()[$visibility])) {
    $where[] = 'pp.visibility = ?';
    $params[] = $visibility;
}

if ($sync === 'public') {
    $where[] = 'pp.runemetrics_public = 1';
} elseif ($sync === 'private') {
    $where[] = 'pp.runemetrics_public = 0';
} elseif ($sync === 'never') {
    $where[] = 'pp.last_sync_at IS NULL';
} elseif ($sync === 'synced') {
    $where[] = 'pp.last_sync_at IS NOT NULL';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("\n    SELECT\n        pp.*,\n        u.username AS owner_username,\n        u.global_name AS owner_global_name,\n        u.discord_id AS owner_discord_id,\n        u.avatar_hash AS owner_avatar_hash,\n        u.email AS owner_email,\n        ppm.display_name AS rm_display_name,\n        ppm.total_level,\n        ppm.total_xp,\n        ppm.combat_level,\n        ppm.quests_complete,\n        ppm.quests_started,\n        ppm.quests_not_started,\n        ppm.last_successful_sync_at,\n        ppm.last_sync_status,\n        ppm.last_sync_error,\n        COALESCE(pj_stats.journey_count, 0) AS journey_count,\n        COALESCE(pj_stats.active_journey_count, 0) AS active_journey_count\n    FROM player_profiles pp\n    JOIN users u ON u.id = pp.user_id\n    LEFT JOIN player_profile_metrics ppm ON ppm.profile_id = pp.id\n    LEFT JOIN (\n        SELECT profile_id, COUNT(*) AS journey_count, SUM(CASE WHEN completed_at IS NULL THEN 1 ELSE 0 END) AS active_journey_count\n        FROM player_journeys\n        GROUP BY profile_id\n    ) pj_stats ON pj_stats.profile_id = pp.id\n    {$whereSql}\n    ORDER BY pp.created_at DESC, pp.rsn ASC\n");
$stmt->execute($params);
$profiles = $stmt->fetchAll();

$totalProfiles = (int)$pdo->query('SELECT COUNT(*) FROM player_profiles')->fetchColumn();
$matchingProfiles = count($profiles);

function admin_owner_name(array $row): string
{
    $name = trim((string)($row['owner_global_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $username = trim((string)($row['owner_username'] ?? ''));
    return $username !== '' ? $username : 'User #' . (int)$row['user_id'];
}

function admin_number(?int $value): string
{
    return $value === null ? '—' : number_format($value);
}

function admin_sync_label(array $profile): string
{
    if ($profile['last_sync_at'] === null) {
        return 'Never synced';
    }
    if ($profile['runemetrics_public'] === null) {
        return 'Synced';
    }
    return ((int)$profile['runemetrics_public'] === 1) ? 'RuneMetrics public' : 'RuneMetrics private';
}

page_header('Manage Profiles');
?>
<div class="page-title-row">
    <div>
        <h1>Manage Profiles</h1>
        <p class="muted">Review player profiles, owners, account details and RuneMetrics sync state.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>Total profiles</span><strong><?= e($totalProfiles) ?></strong></div>
    <div class="stat-card"><span>Showing</span><strong><?= e($matchingProfiles) ?></strong></div>
</div>

<div class="card admin-filter-card">
    <form method="get" class="admin-filter-form">
        <label>
            <span>Search</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="RSN, owner, Discord ID">
        </label>
        <label>
            <span>Account type</span>
            <select name="account_type">
                <option value="">All account types</option>
                <?php foreach (account_type_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $accountType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Visibility</span>
            <select name="visibility">
                <option value="">All visibility</option>
                <?php foreach (visibility_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $visibility === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Sync state</span>
            <select name="sync">
                <option value="">All sync states</option>
                <option value="synced" <?= $sync === 'synced' ? 'selected' : '' ?>>Synced</option>
                <option value="never" <?= $sync === 'never' ? 'selected' : '' ?>>Never synced</option>
                <option value="public" <?= $sync === 'public' ? 'selected' : '' ?>>RuneMetrics public</option>
                <option value="private" <?= $sync === 'private' ? 'selected' : '' ?>>RuneMetrics private</option>
            </select>
        </label>
        <div class="admin-filter-actions">
            <button class="button">Filter</button>
            <a class="button secondary" href="/admin/profiles.php">Reset</a>
        </div>
    </form>
</div>

<div class="card admin-profiles-ledger-card">
<?php if (!$profiles): ?>
    <div class="empty-state">
        <h2>No profiles found</h2>
        <p class="muted">Try clearing or changing your filters.</p>
    </div>
<?php else: ?>
<div class="admin-table-scroll"><table class="admin-table admin-rich-table">
<thead>
<tr>
    <th>Profile</th>
    <th>Owner</th>
    <th>Progress snapshot</th>
    <th>Sync</th>
    <th>Journeys</th>
    <th>Created</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($profiles as $profile): ?>
<tr>
<td>
    <div class="admin-profile-cell">
        <img class="profile-avatar tiny" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
        <div>
            <strong><?= e($profile['rm_display_name'] ?: $profile['rsn']) ?></strong>
            <div class="muted small">RSN: <?= e($profile['rsn']) ?></div>
            <div>
                <span class="badge accent"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?></span>
                <span class="badge"><?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></span>
                <?php if ((int)$profile['is_primary'] === 1): ?><span class="badge success">Primary</span><?php endif; ?>
            </div>
        </div>
    </div>
</td>
<td>
    <strong><?= e(admin_owner_name($profile)) ?></strong>
    <div class="muted small">@<?= e($profile['owner_username']) ?></div>
    <?php if (!empty($profile['owner_email'])): ?><div class="muted small"><?= e($profile['owner_email']) ?></div><?php endif; ?>
</td>
<td>
    <div class="metric-list compact">
        <div><span>Total level</span><strong><?= e(admin_number($profile['total_level'] !== null ? (int)$profile['total_level'] : null)) ?></strong></div>
        <div><span>Combat</span><strong><?= e(admin_number($profile['combat_level'] !== null ? (int)$profile['combat_level'] : null)) ?></strong></div>
        <div><span>Total XP</span><strong><?= e(admin_number($profile['total_xp'] !== null ? (int)$profile['total_xp'] : null)) ?></strong></div>
        <div><span>Quests complete</span><strong><?= e(admin_number($profile['quests_complete'] !== null ? (int)$profile['quests_complete'] : null)) ?></strong></div>
    </div>
</td>
<td>
    <span class="badge"><?= e(admin_sync_label($profile)) ?></span>
    <div class="muted small">Last sync: <?= e($profile['last_sync_at'] ?: 'Never') ?></div>
    <?php if (!empty($profile['last_sync_status'])): ?><div class="muted small">Status: <?= e($profile['last_sync_status']) ?></div><?php endif; ?>
    <?php if (!empty($profile['last_sync_error'])): ?><div class="alert error small"><?= e($profile['last_sync_error']) ?></div><?php endif; ?>
</td>
<td>
    <strong><?= e((int)$profile['active_journey_count']) ?></strong> active
    <div class="muted small"><?= e((int)$profile['journey_count']) ?> total</div>
</td>
<td><?= e($profile['created_at']) ?></td>
<td>
    <div class="admin-action-stack">
        <a class="button secondary" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>&admin=1">View</a>
        <?php if (current_user_can('profiles.delete')): ?>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this profile? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
            <button class="button danger">Delete</button>
        </form>
        <?php endif; ?>
    </div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
</div>
<?php page_footer(); ?>
