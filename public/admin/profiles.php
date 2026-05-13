<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('profiles.delete');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $profileId = (int)($_POST['profile_id'] ?? 0);
    db()->prepare('DELETE FROM player_profiles WHERE id = ?')->execute([$profileId]);
    redirect('/admin/profiles.php');
}

$profiles = db()->query('SELECT * FROM player_profiles ORDER BY created_at DESC')->fetchAll();

page_header('Manage Profiles');
?>
<h1>Manage Profiles</h1>

<div class="card">
<table class="admin-table">
<thead><tr><th>RSN</th><th>Owner</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($profiles as $profile): ?>
<tr>
<td><?= e($profile['rsn']) ?></td>
<td><?= (int)$profile['user_id'] ?></td>
<td>
<form method="post" class="inline-form" onsubmit="return confirm('Delete this profile?');">
<?= csrf_field() ?>
<input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
<button class="button danger">Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php page_footer(); ?>