<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_login();
$user = current_user();
page_header('Dashboard');
$roles = roles_for_user((int)$user['id']);
$profiles = profiles_for_user((int)$user['id']);
?>
<div class="card">
    <h1>Welcome, <?= e($user['global_name'] ?: $user['username']) ?></h1>
    <p class="muted">Your account is active. Add RSN profiles now; RuneMetrics syncing will build on top of these profiles next.</p>
    <h2>Your roles</h2>
    <p><?php foreach ($roles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?></p>
    <h2>Your profiles</h2>
    <?php if ($profiles): ?>
        <p><?php foreach ($profiles as $profile): ?><span class="badge"><?= e($profile['rsn']) ?><?= ((int)$profile['is_primary'] === 1) ? ' • Primary' : '' ?></span><?php endforeach; ?></p>
        <p><a class="button secondary" href="/profiles/index.php">Manage profiles</a></p>
    <?php else: ?>
        <p class="muted">No RSNs are attached yet.</p>
        <p><a class="button" href="/profiles/new.php">Add your first RSN</a></p>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
