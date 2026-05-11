<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();
$user = current_user();
$profiles = profiles_for_user((int)$user['id']);
page_header('My Profiles');
?>
<div class="page-title-row">
    <div>
        <h1>My RSN Profiles</h1>
        <p class="muted">Attach each RuneScape character you want Wayfinder to support. RuneMetrics syncing will be added next.</p>
    </div>
    <a class="button" href="/profiles/new.php">Add RSN</a>
</div>

<?php if (!$profiles): ?>
    <div class="card empty-state">
        <h2>No profiles yet</h2>
        <p>Add your first RSN to start preparing your Wayfinder dashboard.</p>
        <a class="button" href="/profiles/new.php">Add your first RSN</a>
    </div>
<?php else: ?>
    <div class="grid cards-grid">
        <?php foreach ($profiles as $profile): ?>
            <div class="card profile-card">
                <div class="card-row">
                    <h2><?= e($profile['rsn']) ?></h2>
                    <?php if ((int)$profile['is_primary'] === 1): ?><span class="badge">Primary</span><?php endif; ?>
                </div>
                <p><strong>Account type:</strong> <?= e(account_type_options()[$profile['account_type']] ?? $profile['account_type']) ?></p>
                <p><strong>Visibility:</strong> <?= e(visibility_options()[$profile['visibility']] ?? $profile['visibility']) ?></p>
                <p class="muted">RuneMetrics sync: <?= $profile['last_sync_at'] ? e($profile['last_sync_at']) : 'Not synced yet' ?></p>
                <a class="button secondary" href="/profiles/edit.php?id=<?= (int)$profile['id'] ?>">Edit profile</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
