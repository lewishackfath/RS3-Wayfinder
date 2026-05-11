<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('profiles.view');
$profiles = all_profiles_admin();
page_header('Admin Profiles');
?>
<div class="page-title-row">
    <div>
        <h1>Player Profiles</h1>
        <p class="muted">A read-only overview of RSNs attached to Wayfinder users. Moderation controls can be expanded later.</p>
    </div>
    <a class="button secondary" href="/admin/index.php">Admin dashboard</a>
</div>
<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>RSN</th>
                <th>User</th>
                <th>Type</th>
                <th>Visibility</th>
                <th>Primary</th>
                <th>Last Sync</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($profiles as $profile): ?>
                <tr>
                    <td><?= e($profile['rsn']) ?></td>
                    <td><?= e($profile['global_name'] ?: $profile['username']) ?><br><span class="muted small">Discord ID: <?= e($profile['discord_id']) ?></span></td>
                    <td><?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?></td>
                    <td><span class="badge"><?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></span></td>
                    <td><?= ((int)$profile['is_primary'] === 1) ? 'Yes' : 'No' ?></td>
                    <td><?= $profile['last_sync_at'] ? e($profile['last_sync_at']) : '<span class="muted">Never</span>' ?></td>
                    <td><?= e($profile['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$profiles): ?>
                <tr><td colspan="7" class="muted">No profiles have been created yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>
