<?php
declare(strict_types=1);

function user_has_permission(int $userId, string $permissionSlug): bool
{
    $stmt = db()->prepare("SELECT 1
        FROM user_roles ur
        JOIN role_permissions rp ON rp.role_id = ur.role_id
        JOIN permissions p ON p.id = rp.permission_id
        WHERE ur.user_id = ? AND p.slug = ?
        LIMIT 1");
    $stmt->execute([$userId, $permissionSlug]);
    return (bool)$stmt->fetchColumn();
}

function current_user_can(string $permissionSlug): bool
{
    $user = current_user();
    return $user ? user_has_permission((int)$user['id'], $permissionSlug) : false;
}

function require_permission(string $permissionSlug): void
{
    require_login();
    if (!current_user_can($permissionSlug)) {
        abort_page(403, 'You do not have permission to access this page.');
    }
}

function all_roles(): array
{
    return db()->query("SELECT * FROM roles ORDER BY FIELD(slug, 'owner', 'admin', 'member'), name")->fetchAll();
}

function roles_for_user(int $userId): array
{
    $stmt = db()->prepare("SELECT r.* FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.name");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function set_user_roles(int $userId, array $roleIds, ?int $assignedBy): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
        $insert = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if ($roleId > 0) {
                $insert->execute([$userId, $roleId, $assignedBy]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}


function all_permissions(): array
{
    return db()->query("SELECT * FROM permissions ORDER BY slug ASC")->fetchAll();
}

function permission_groups(): array
{
    $groups = [];
    foreach (all_permissions() as $permission) {
        $parts = explode('.', (string)$permission['slug'], 2);
        $group = ucfirst($parts[0] ?: 'General');
        $groups[$group][] = $permission;
    }
    ksort($groups);
    return $groups;
}

function permissions_for_role(int $roleId): array
{
    $stmt = db()->prepare("SELECT p.* FROM permissions p JOIN role_permissions rp ON rp.permission_id = p.id WHERE rp.role_id = ? ORDER BY p.slug ASC");
    $stmt->execute([$roleId]);
    return $stmt->fetchAll();
}

function role_by_id(int $roleId): ?array
{
    $stmt = db()->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    return $role ?: null;
}

function role_slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'role';
}

function role_unique_slug(string $base, ?int $ignoreRoleId = null): string
{
    $base = role_slugify($base);
    $slug = $base;
    $i = 2;
    $pdo = db();

    while (true) {
        $sql = "SELECT id FROM roles WHERE slug = ?";
        $params = [$slug];
        if ($ignoreRoleId !== null) {
            $sql .= " AND id <> ?";
            $params[] = $ignoreRoleId;
        }
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function create_role(string $name, string $description, array $permissionIds): int
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Role name is required.');
    }

    $slug = role_unique_slug($name);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO roles (slug, name, description, is_system) VALUES (?, ?, ?, 0)");
        $stmt->execute([$slug, $name, trim($description)]);
        $roleId = (int)$pdo->lastInsertId();
        set_role_permissions($roleId, $permissionIds);
        $pdo->commit();
        return $roleId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function update_role(int $roleId, string $name, string $description, array $permissionIds): void
{
    $role = role_by_id($roleId);
    if (!$role) {
        throw new InvalidArgumentException('Role not found.');
    }

    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Role name is required.');
    }

    $slug = !empty($role['is_system']) ? (string)$role['slug'] : role_unique_slug($name, $roleId);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE roles SET slug = ?, name = ?, description = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?");
        $stmt->execute([$slug, $name, trim($description), $roleId]);
        set_role_permissions($roleId, $permissionIds);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function set_role_permissions(int $roleId, array $permissionIds): void
{
    $pdo = db();
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$roleId]);

    $insert = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
        if ($permissionId > 0) {
            $insert->execute([$roleId, $permissionId]);
        }
    }
}

function delete_role(int $roleId): void
{
    $role = role_by_id($roleId);
    if (!$role) {
        throw new InvalidArgumentException('Role not found.');
    }
    if (!empty($role['is_system'])) {
        throw new InvalidArgumentException('System roles cannot be deleted.');
    }
    db()->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);
}
