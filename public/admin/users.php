<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('users.view');

$pdo = db();
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 200")->fetchAll();
$allRoles = all_roles();

page_header('Users');
?>
<div class="card">
    <h1>Users</h1>
    <p class="muted">Assign roles and control active access.</p>
    <table>
        <thead><tr><th>User</th><th>Discord ID</th><th>Status</th><th>Roles</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $userRoles = roles_for_user((int)$u['id']); $roleIds = array_map(fn($r) => (int)$r['id'], $userRoles); ?>
            <tr>
                <td><strong><?= e($u['global_name'] ?: $u['username']) ?></strong><br><span class="muted"><?= e($u['email'] ?? '') ?></span></td>
                <td><?= e($u['discord_id']) ?></td>
                <td><?= ((int)$u['is_active'] === 1) ? '<span class="badge">Active</span>' : '<span class="badge">Disabled</span>' ?></td>
                <td>
                    <?php if (current_user_can('users.manage')): ?>
                    <form method="post" action="/admin/update_user.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <?php foreach ($allRoles as $role): ?>
                            <label class="badge"><input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" <?= in_array((int)$role['id'], $roleIds, true) ? 'checked' : '' ?>> <?= e($role['name']) ?></label>
                        <?php endforeach; ?>
                </td>
                <td>
                        <label class="badge"><input type="checkbox" name="is_active" value="1" <?= ((int)$u['is_active'] === 1) ? 'checked' : '' ?>> Active</label><br><br>
                        <button type="submit">Save</button>
                    </form>
                    <?php else: ?>
                        <?php foreach ($userRoles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>
