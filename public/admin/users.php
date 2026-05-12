<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('users.view');

$pdo = db();
$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 200")->fetchAll();
$allRoles = all_roles();

page_header('Users');
?>
<div class="page-title-row">
    <div>
        <h1>Users</h1>
        <p class="muted">Assign roles and control active access.</p>
    </div>
    <?php if (current_user_can('roles.manage')): ?>
        <a class="button secondary" href="/admin/roles.php">Manage roles</a>
    <?php endif; ?>
</div>

<div class="card">
    <table class="table">
        <thead><tr><th>User</th><th>Status</th><th>Roles</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <?php $userRoles = roles_for_user((int)$u['id']); $roleIds = array_map(fn($r) => (int)$r['id'], $userRoles); ?>
            <tr>
                <td>
                    <strong><?= e($u['global_name'] ?: $u['username']) ?></strong><br>
                    <span class="muted small"><?= e($u['email'] ?? '') ?></span><br>
                    <span class="muted small">Discord ID: <?= e($u['discord_id']) ?></span>
                </td>
                <td><?= ((int)$u['is_active'] === 1) ? '<span class="badge success">Active</span>' : '<span class="badge">Disabled</span>' ?></td>
                <td>
                    <?php if (current_user_can('users.manage')): ?>
                        <form class="user-role-form" method="post" action="/admin/update_user.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <div class="role-check-list">
                                <?php foreach ($allRoles as $role): ?>
                                    <label class="permission-check compact">
                                        <input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>" <?= in_array((int)$role['id'], $roleIds, true) ? 'checked' : '' ?>>
                                        <span>
                                            <strong><?= e($role['name']) ?></strong>
                                            <small><?= e($role['slug']) ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                </td>
                <td>
                            <label class="toggle-row compact">
                                <input type="checkbox" name="is_active" value="1" <?= ((int)$u['is_active'] === 1) ? 'checked' : '' ?>>
                                <span><strong>Active</strong><small>User can sign in</small></span>
                            </label>
                            <button class="button" type="submit">Save user</button>
                        </form>
                    <?php else: ?>
                        <div class="permission-pill-list">
                            <?php foreach ($userRoles as $role): ?><span class="badge"><?= e($role['name']) ?></span><?php endforeach; ?>
                        </div>
                </td>
                    <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>
