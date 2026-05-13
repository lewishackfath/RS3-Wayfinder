<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('users.view');

$pdo = db();

$columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
$hasIsBanned = in_array('is_banned', $columns, true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('users.manage');
    require_csrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($userId > 0 && $userId !== (int)(current_user()['id'] ?? 0)) {
        if ($action === 'toggle_active') {
            $pdo->prepare('UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$userId]);
        }

        if ($action === 'toggle_ban' && $hasIsBanned) {
            $pdo->prepare('UPDATE users SET is_banned = CASE WHEN is_banned = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$userId]);
        }

        if ($action === 'delete_user') {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        }
    }

    redirect('/admin/users.php?' . http_build_query(array_filter([
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'role' => $_GET['role'] ?? null,
        'profile_type' => $_GET['profile_type'] ?? null,
    ], fn($value) => $value !== null && $value !== '')));
}

$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$role = (string)($_GET['role'] ?? '');
$profileType = (string)($_GET['profile_type'] ?? '');

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.username LIKE ? OR u.global_name LIKE ? OR u.email LIKE ? OR u.discord_id LIKE ? OR EXISTS (SELECT 1 FROM player_profiles pp_q WHERE pp_q.user_id = u.id AND pp_q.rsn LIKE ?))';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if ($status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($status === 'disabled') {
    $where[] = 'u.is_active = 0';
} elseif ($status === 'banned' && $hasIsBanned) {
    $where[] = 'u.is_banned = 1';
} elseif ($status === 'unverified') {
    $where[] = '(u.email IS NOT NULL AND u.email_verified = 0)';
}

if ($role !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM user_roles ur_filter JOIN roles r_filter ON r_filter.id = ur_filter.role_id WHERE ur_filter.user_id = u.id AND r_filter.slug = ?)';
    $params[] = $role;
}

if ($profileType !== '' && isset(account_type_options()[$profileType])) {
    $where[] = 'EXISTS (SELECT 1 FROM player_profiles pp_filter WHERE pp_filter.user_id = u.id AND pp_filter.account_type = ?)';
    $params[] = $profileType;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("\n    SELECT\n        u.*,\n        " . ($hasIsBanned ? 'u.is_banned' : '0 AS is_banned') . ",\n        COALESCE(profile_stats.profile_count, 0) AS profile_count,\n        COALESCE(profile_stats.primary_rsn, '') AS primary_rsn,\n        COALESCE(profile_stats.last_profile_sync_at, NULL) AS last_profile_sync_at,\n        COALESCE(role_stats.role_names, '') AS role_names,\n        COALESCE(role_stats.role_slugs, '') AS role_slugs\n    FROM users u\n    LEFT JOIN (\n        SELECT\n            user_id,\n            COUNT(*) AS profile_count,\n            MAX(CASE WHEN is_primary = 1 THEN rsn ELSE NULL END) AS primary_rsn,\n            MAX(last_sync_at) AS last_profile_sync_at\n        FROM player_profiles\n        GROUP BY user_id\n    ) profile_stats ON profile_stats.user_id = u.id\n    LEFT JOIN (\n        SELECT ur.user_id, GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS role_names, GROUP_CONCAT(r.slug ORDER BY r.name SEPARATOR ',') AS role_slugs\n        FROM user_roles ur\n        JOIN roles r ON r.id = ur.role_id\n        GROUP BY ur.user_id\n    ) role_stats ON role_stats.user_id = u.id\n    {$whereSql}\n    ORDER BY u.created_at DESC\n");
$stmt->execute($params);
$users = $stmt->fetchAll();

$userIds = array_map(fn($user) => (int)$user['id'], $users);
$profilesByUser = [];
if ($userIds) {
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $profileStmt = $pdo->prepare("\n        SELECT pp.*, ppm.display_name, ppm.total_level, ppm.combat_level, ppm.quests_complete\n        FROM player_profiles pp\n        LEFT JOIN player_profile_metrics ppm ON ppm.profile_id = pp.id\n        WHERE pp.user_id IN ({$placeholders})\n        ORDER BY pp.is_primary DESC, pp.rsn ASC\n    ");
    $profileStmt->execute($userIds);
    foreach ($profileStmt->fetchAll() as $profile) {
        $profilesByUser[(int)$profile['user_id']][] = $profile;
    }
}

$roles = $pdo->query('SELECT slug, name FROM roles ORDER BY name ASC')->fetchAll();
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$matchingUsers = count($users);

function admin_user_name(array $user): string
{
    $name = trim((string)($user['global_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? $username : 'User #' . (int)$user['id'];
}

function discord_avatar_url_admin(array $user): string
{
    $discordId = (string)($user['discord_id'] ?? '');
    $hash = (string)($user['avatar_hash'] ?? '');
    if ($discordId !== '' && $hash !== '') {
        $ext = str_starts_with($hash, 'a_') ? 'gif' : 'png';
        return 'https://cdn.discordapp.com/avatars/' . rawurlencode($discordId) . '/' . rawurlencode($hash) . '.' . $ext . '?size=64';
    }
    return '/assets/default-avatar.svg';
}

page_header('Manage Users');
?>
<div class="page-title-row">
    <div>
        <h1>Manage Users</h1>
        <p class="muted">Review Discord users, roles, login state and linked RuneScape profiles.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card"><span>Total users</span><strong><?= e($totalUsers) ?></strong></div>
    <div class="stat-card"><span>Showing</span><strong><?= e($matchingUsers) ?></strong></div>
</div>

<div class="card admin-filter-card">
    <form method="get" class="admin-filter-form">
        <label>
            <span>Search</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, username, email, RSN, Discord ID">
        </label>
        <label>
            <span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                <?php if ($hasIsBanned): ?><option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banned</option><?php endif; ?>
                <option value="unverified" <?= $status === 'unverified' ? 'selected' : '' ?>>Email unverified</option>
            </select>
        </label>
        <label>
            <span>Role</span>
            <select name="role">
                <option value="">All roles</option>
                <?php foreach ($roles as $roleOption): ?>
                    <option value="<?= e($roleOption['slug']) ?>" <?= $role === $roleOption['slug'] ? 'selected' : '' ?>><?= e($roleOption['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Profile type</span>
            <select name="profile_type">
                <option value="">All profile types</option>
                <?php foreach (account_type_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $profileType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="admin-filter-actions">
            <button class="button">Filter</button>
            <a class="button secondary" href="/admin/users.php">Reset</a>
        </div>
    </form>
</div>

<div class="card">
<?php if (!$users): ?>
    <div class="empty-state">
        <h2>No users found</h2>
        <p class="muted">Try clearing or changing your filters.</p>
    </div>
<?php else: ?>
<table class="admin-table admin-rich-table">
<thead>
<tr>
    <th>User</th>
    <th>Contact / Discord</th>
    <th>Roles</th>
    <th>Profiles</th>
    <th>Status</th>
    <th>Last login</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<?php $linkedProfiles = $profilesByUser[(int)$user['id']] ?? []; ?>
<tr>
<td>
    <div class="admin-profile-cell">
        <img class="profile-avatar tiny" src="<?= e(discord_avatar_url_admin($user)) ?>" alt="Avatar for <?= e(admin_user_name($user)) ?>" loading="lazy" referrerpolicy="no-referrer">
        <div>
            <strong><?= e(admin_user_name($user)) ?></strong>
            <div class="muted small">@<?= e($user['username']) ?></div>
            <div class="muted small">Joined <?= e($user['created_at']) ?></div>
        </div>
    </div>
</td>
<td>
    <?php if (!empty($user['email'])): ?>
        <div><?= e($user['email']) ?></div>
        <span class="badge <?= (int)$user['email_verified'] === 1 ? 'success' : '' ?>"><?= (int)$user['email_verified'] === 1 ? 'Email verified' : 'Email unverified' ?></span>
    <?php else: ?>
        <div class="muted">No email shared</div>
    <?php endif; ?>
    <div class="muted small">Discord ID: <?= e($user['discord_id']) ?></div>
</td>
<td>
    <?php if (!empty($user['role_names'])): ?>
        <?php foreach (explode(', ', (string)$user['role_names']) as $roleName): ?>
            <span class="badge accent"><?= e($roleName) ?></span>
        <?php endforeach; ?>
    <?php else: ?>
        <span class="muted">No roles</span>
    <?php endif; ?>
</td>
<td>
    <strong><?= e((int)$user['profile_count']) ?></strong> linked
    <?php if (!empty($user['primary_rsn'])): ?><div class="muted small">Primary: <?= e($user['primary_rsn']) ?></div><?php endif; ?>
    <?php if (!empty($user['last_profile_sync_at'])): ?><div class="muted small">Last profile sync: <?= e($user['last_profile_sync_at']) ?></div><?php endif; ?>
</td>
<td>
    <span class="badge <?= (int)$user['is_active'] === 1 ? 'success' : '' ?>"><?= (int)$user['is_active'] === 1 ? 'Active' : 'Disabled' ?></span>
    <?php if ($hasIsBanned && (int)$user['is_banned'] === 1): ?><span class="badge danger">Banned</span><?php endif; ?>
</td>
<td><?= e($user['last_login_at'] ?: 'Never') ?></td>
<td>
    <?php if (current_user_can('users.manage')): ?>
    <form method="post" class="admin-action-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <button class="button secondary" name="action" value="toggle_active" <?= (int)$user['id'] === (int)(current_user()['id'] ?? 0) ? 'disabled' : '' ?>><?= (int)$user['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
        <?php if ($hasIsBanned): ?>
            <button class="button secondary" name="action" value="toggle_ban" <?= (int)$user['id'] === (int)(current_user()['id'] ?? 0) ? 'disabled' : '' ?>><?= (int)$user['is_banned'] === 1 ? 'Unban' : 'Ban' ?></button>
        <?php endif; ?>
        <button class="button danger" name="action" value="delete_user" onclick="return confirm('Delete this user and all linked profiles? This cannot be undone.');" <?= (int)$user['id'] === (int)(current_user()['id'] ?? 0) ? 'disabled' : '' ?>>Delete</button>
    </form>
    <?php else: ?>
        <span class="muted">View only</span>
    <?php endif; ?>
</td>
</tr>
<tr class="admin-details-row">
<td colspan="7">
    <details>
        <summary>Linked profiles<?= $linkedProfiles ? ' (' . count($linkedProfiles) . ')' : '' ?></summary>
        <?php if (!$linkedProfiles): ?>
            <p class="muted">No linked profiles.</p>
        <?php else: ?>
            <div class="admin-linked-profile-grid">
                <?php foreach ($linkedProfiles as $profile): ?>
                    <a class="admin-linked-profile-card" href="/profiles/view.php?id=<?= (int)$profile['id'] ?>&admin=1">
                        <img class="profile-avatar tiny" src="<?= e(runescape_avatar_url((string)$profile['rsn'])) ?>" alt="Avatar for <?= e($profile['rsn']) ?>" loading="lazy" referrerpolicy="no-referrer">
                        <div>
                            <strong><?= e($profile['display_name'] ?: $profile['rsn']) ?></strong>
                            <div class="muted small"><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?><?= (int)$profile['is_primary'] === 1 ? ' • Primary' : '' ?></div>
                            <div class="muted small">Level <?= e($profile['total_level'] ?? '—') ?> • Combat <?= e($profile['combat_level'] ?? '—') ?> • Quests <?= e($profile['quests_complete'] ?? '—') ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </details>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php page_footer(); ?>
