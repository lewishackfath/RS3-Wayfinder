<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('roles.manage');

$id = (int)($_GET['id'] ?? 0);
$role = $id ? role_by_id($id) : null;
if ($id && !$role) {
    abort_page(404, 'Role not found.');
}

$error = null;
$permissionGroups = permission_groups();
$currentPermissions = $id ? permissions_for_role($id) : [];
$currentPermissionIds = array_map(fn($p) => (int)$p['id'], $currentPermissions);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'save');
    try {
        if ($action === 'delete') {
            delete_role($id);
            redirect('/admin/roles.php');
        }

        $permissionIds = $_POST['permission_ids'] ?? [];
        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }

        if ($id) {
            update_role($id, (string)($_POST['name'] ?? ''), (string)($_POST['description'] ?? ''), $permissionIds);
        } else {
            $id = create_role((string)($_POST['name'] ?? ''), (string)($_POST['description'] ?? ''), $permissionIds);
        }

        redirect('/admin/roles.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$nameValue = (string)($_POST['name'] ?? $role['name'] ?? '');
$descriptionValue = (string)($_POST['description'] ?? $role['description'] ?? '');
if ($_POST && isset($_POST['permission_ids']) && is_array($_POST['permission_ids'])) {
    $currentPermissionIds = array_map('intval', $_POST['permission_ids']);
}

page_header($id ? 'Edit Role' : 'Create Role');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Role' : 'Create Role' ?></h1>
        <p class="muted">Assign permissions that control access to admin and management features.</p>
    </div>
    <a class="button secondary" href="/admin/roles.php">Back to roles</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Role details</h2>
            <p class="muted">The role name is shown to admins when assigning access to users.</p>
        </div>

        <label>Role name
            <input name="name" value="<?= e($nameValue) ?>" required <?= !empty($role['is_system']) ? '' : '' ?> placeholder="Journey Editor">
            <span class="field-help">Custom role slugs are generated automatically. System role slugs are preserved.</span>
        </label>

        <label>Description
            <textarea name="description" rows="4" placeholder="Can manage journeys but cannot manage users."><?= e($descriptionValue) ?></textarea>
        </label>

        <?php if ($role): ?>
            <p class="muted small">Slug: <code><?= e($role['slug']) ?></code><?= !empty($role['is_system']) ? ' • System role' : '' ?></p>
        <?php endif; ?>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Permissions</h2>
            <p class="muted">Tick the capabilities this role should grant.</p>
        </div>

        <div class="permission-groups">
            <?php foreach ($permissionGroups as $groupName => $permissions): ?>
                <fieldset class="permission-group">
                    <legend><?= e($groupName) ?></legend>
                    <?php foreach ($permissions as $permission): ?>
                        <label class="permission-check">
                            <input type="checkbox" name="permission_ids[]" value="<?= (int)$permission['id'] ?>" <?= in_array((int)$permission['id'], $currentPermissionIds, true) ? 'checked' : '' ?>>
                            <span>
                                <strong><?= e($permission['name']) ?></strong>
                                <small><?= e($permission['slug']) ?><?= !empty($permission['description']) ? ' — ' . e($permission['description']) : '' ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-form-actions">
        <?php if ($id && empty($role['is_system'])): ?>
            <button class="button danger" type="submit" name="action" value="delete" onclick="return confirm('Delete this role? Users assigned to it will lose these permissions.');">Delete role</button>
        <?php endif; ?>
        <a class="button secondary" href="/admin/roles.php">Cancel</a>
        <button class="button" type="submit">Save role</button>
    </div>
</form>
<?php page_footer(); ?>
