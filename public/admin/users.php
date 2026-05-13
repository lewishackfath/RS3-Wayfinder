<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('users.manage');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'toggle_ban') {
        db()->prepare('UPDATE users SET is_banned = CASE WHEN is_banned = 1 THEN 0 ELSE 1 END WHERE id = ?')->execute([$userId]);
    }

    if ($action === 'delete_user') {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }

    redirect('/admin/users.php');
}

$users = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();

page_header('Manage Users');
?>
<h1>Manage Users</h1>

<div class="card">
<table class="admin-table">
<thead><tr><th>User</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
<td><?= e($user['display_name'] ?? $user['discord_username'] ?? ('User #' . $user['id'])) ?></td>
<td><?= !empty($user['is_banned']) ? 'Banned' : 'Active' ?></td>
<td>
<form method="post" class="inline-form">
<?= csrf_field() ?>
<input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
<button class="button secondary" name="action" value="toggle_ban"><?= !empty($user['is_banned']) ? 'Unban' : 'Ban' ?></button>
<button class="button danger" name="action" value="delete_user" onclick="return confirm('Delete this user?');">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php page_footer(); ?>