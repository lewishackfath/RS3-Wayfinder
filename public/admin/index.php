<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('admin.access');

$pdo = db();

$userTotals = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
    'disabled' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn(),
];

$userCountsByRole = $pdo->query("
    SELECT r.name, r.slug, COUNT(ur.user_id) AS total
    FROM roles r
    LEFT JOIN user_roles ur ON ur.role_id = r.id
    GROUP BY r.id, r.name, r.slug
    ORDER BY FIELD(r.slug, 'owner', 'admin', 'member'), r.name
")->fetchAll();

$journeyTotals = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM journeys")->fetchColumn(),
    'published' => (int)$pdo->query("SELECT COUNT(*) FROM journeys WHERE is_published = 1")->fetchColumn(),
    'draft' => (int)$pdo->query("SELECT COUNT(*) FROM journeys WHERE is_published = 0")->fetchColumn(),
    'chapters' => (int)$pdo->query("SELECT COUNT(*) FROM journey_chapters")->fetchColumn(),
    'steps' => (int)$pdo->query("SELECT COUNT(*) FROM journey_steps")->fetchColumn(),
    'optional_steps' => (int)$pdo->query("SELECT COUNT(*) FROM journey_steps WHERE is_optional = 1")->fetchColumn(),
];

$profileTotals = [
    'total' => (int)$pdo->query("SELECT COUNT(*) FROM player_profiles")->fetchColumn(),
    'public' => (int)$pdo->query("SELECT COUNT(*) FROM player_profiles WHERE visibility = 'public'")->fetchColumn(),
    'private' => (int)$pdo->query("SELECT COUNT(*) FROM player_profiles WHERE visibility = 'private'")->fetchColumn(),
    'unlisted' => (int)$pdo->query("SELECT COUNT(*) FROM player_profiles WHERE visibility = 'unlisted'")->fetchColumn(),
];

$profileCountsByType = $pdo->query("
    SELECT account_type, COUNT(*) AS total
    FROM player_profiles
    GROUP BY account_type
    ORDER BY total DESC, account_type ASC
")->fetchAll();

page_header('Admin');
?>
<div class="page-title-row">
    <div>
        <h1>Admin Dashboard</h1>
        <p class="muted">A quick overview of users, roles, profiles and journeys.</p>
    </div>
    <div class="form-actions">
        <?php if (current_user_can('users.view')): ?><a class="button secondary" href="/admin/users.php">Users</a><?php endif; ?>
        <?php if (current_user_can('journeys.view')): ?><a class="button secondary" href="/admin/journeys.php">Journeys</a><?php endif; ?>
    </div>
</div>

<div class="admin-summary-grid">
    <div class="stat-card">
        <span>Total Users</span>
        <strong><?= e($userTotals['total']) ?></strong>
        <small class="muted"><?= e($userTotals['active']) ?> active • <?= e($userTotals['disabled']) ?> disabled</small>
    </div>
    <div class="stat-card">
        <span>Total Profiles</span>
        <strong><?= e($profileTotals['total']) ?></strong>
        <small class="muted"><?= e($profileTotals['public']) ?> public • <?= e($profileTotals['private']) ?> private</small>
    </div>
    <div class="stat-card">
        <span>Total Journeys</span>
        <strong><?= e($journeyTotals['total']) ?></strong>
        <small class="muted"><?= e($journeyTotals['published']) ?> published • <?= e($journeyTotals['draft']) ?> draft</small>
    </div>
    <div class="stat-card">
        <span>Journey Steps</span>
        <strong><?= e($journeyTotals['steps']) ?></strong>
        <small class="muted"><?= e($journeyTotals['chapters']) ?> chapters • <?= e($journeyTotals['optional_steps']) ?> optional</small>
    </div>
</div>

<div class="grid two-col-grid admin-dashboard-grid">
    <div class="card">
        <div class="page-title-row compact">
            <div>
                <h2>Users by Role</h2>
                <p class="muted">Counts include users assigned to each role.</p>
            </div>
            <?php if (current_user_can('roles.manage')): ?><a class="button secondary" href="/admin/roles.php">Manage roles</a><?php endif; ?>
        </div>

        <?php if (!$userCountsByRole): ?>
            <p class="muted">No roles found.</p>
        <?php else: ?>
            <table class="table compact-table">
                <thead><tr><th>Role</th><th>Users</th></tr></thead>
                <tbody>
                <?php foreach ($userCountsByRole as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong><br><span class="muted small"><?= e($row['slug']) ?></span></td>
                        <td><?= e((int)$row['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="page-title-row compact">
            <div>
                <h2>Profiles by Type</h2>
                <p class="muted">Grouped by the account type users selected.</p>
            </div>
            <?php if (current_user_can('profiles.view')): ?><a class="button secondary" href="/admin/profiles.php">View profiles</a><?php endif; ?>
        </div>

        <?php if (!$profileCountsByType): ?>
            <p class="muted">No player profiles found.</p>
        <?php else: ?>
            <table class="table compact-table">
                <thead><tr><th>Profile Type</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($profileCountsByType as $row): ?>
                    <tr>
                        <td><?= e(account_type_options()[$row['account_type']] ?? $row['account_type']) ?></td>
                        <td><?= e((int)$row['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="page-title-row compact">
            <div>
                <h2>Journey Overview</h2>
                <p class="muted">Content currently available in the journey system.</p>
            </div>
            <?php if (current_user_can('journeys.view')): ?><a class="button secondary" href="/admin/journeys.php">Manage journeys</a><?php endif; ?>
        </div>

        <div class="metric-list">
            <div><span>Published journeys</span><strong><?= e($journeyTotals['published']) ?></strong></div>
            <div><span>Draft journeys</span><strong><?= e($journeyTotals['draft']) ?></strong></div>
            <div><span>Chapters</span><strong><?= e($journeyTotals['chapters']) ?></strong></div>
            <div><span>Steps</span><strong><?= e($journeyTotals['steps']) ?></strong></div>
            <div><span>Optional steps</span><strong><?= e($journeyTotals['optional_steps']) ?></strong></div>
        </div>
    </div>

    <div class="card">
        <h2>Quick Actions</h2>
        <div class="admin-quick-actions">
            <?php if (current_user_can('users.view')): ?><a class="button secondary" href="/admin/users.php">Manage users</a><?php endif; ?>
            <?php if (current_user_can('roles.manage')): ?><a class="button secondary" href="/admin/roles.php">Roles & permissions</a><?php endif; ?>
            <?php if (current_user_can('profiles.view')): ?><a class="button secondary" href="/admin/profiles.php">Player profiles</a><?php endif; ?>
            <?php if (current_user_can('journeys.manage')): ?><a class="button" href="/admin/journey_edit.php">Create journey</a><?php endif; ?>
        </div>
    </div>
</div>
<?php page_footer(); ?>
