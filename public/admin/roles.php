<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('roles.manage');

$roles = all_roles();
page_header('Roles & Permissions');
?>
<div class="page-title-row">
    <div>
        <h1>Roles & Permissions</h1>
        <p class="muted">Control which users can access admin areas and manage Wayfinder content.</p>
    </div>
    <a class="button" href="/admin/role_edit.php">Create role</a>
</div>

<div class="card">
    <?php if (!$roles): ?>
        <p class="muted">No roles exist yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Role</th><th>Permissions</th><th>Type</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($roles as $role): ?>
                <?php $permissions = permissions_for_role((int)$role['id']); ?>
                <tr>
                    <td>
                        <strong><?= e($role['name']) ?></strong><br>
                        <span class="muted small"><?= e($role['slug']) ?></span>
                        <?php if (!empty($role['description'])): ?><br><span class="muted small"><?= e($role['description']) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$permissions): ?>
                            <span class="muted small">No permissions</span>
                        <?php else: ?>
                            <div class="permission-pill-list">
                                <?php foreach ($permissions as $permission): ?>
                                    <span class="badge"><?= e($permission['slug']) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($role['is_system']) ? '<span class="badge">System</span>' : '<span class="badge success">Custom</span>' ?></td>
                    <td class="actions">
                        <a class="button secondary" href="/admin/role_edit.php?id=<?= (int)$role['id'] ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
