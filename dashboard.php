<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/layout.php';
require_login();
$user = current_user();
page_header('Dashboard');
$roles = roles_for_user((int)$user['id']);
?>
<div class="card">
    <h1>Welcome, <?= e($user['global_name'] ?: $user['username']) ?></h1>
    <p class="muted">User authentication is active. RSN profiles and RuneMetrics syncing will build on top of this foundation.</p>
    <h2>Your roles</h2>
    <p><?php foreach ($roles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?></p>
</div>
<?php page_footer(); ?>
