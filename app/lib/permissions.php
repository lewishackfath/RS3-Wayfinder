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
