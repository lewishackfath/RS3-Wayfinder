<?php
declare(strict_types=1);

function bootstrap_schema(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        discord_id VARCHAR(32) NOT NULL UNIQUE,
        username VARCHAR(100) NOT NULL,
        global_name VARCHAR(100) NULL,
        discriminator VARCHAR(10) NULL,
        avatar_hash VARCHAR(128) NULL,
        email VARCHAR(255) NULL,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_users_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(60) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        description TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_roles (
        user_id BIGINT UNSIGNED NOT NULL,
        role_id BIGINT UNSIGNED NOT NULL,
        assigned_by BIGINT UNSIGNED NULL,
        assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, role_id),
        CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (
        role_id BIGINT UNSIGNED NOT NULL,
        permission_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    $pdo->exec("CREATE TABLE IF NOT EXISTS player_profiles (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        rsn VARCHAR(12) NOT NULL,
        rsn_normalised VARCHAR(32) NOT NULL,
        account_type VARCHAR(40) NOT NULL DEFAULT 'main',
        visibility ENUM('private','unlisted','public') NOT NULL DEFAULT 'private',
        is_primary TINYINT(1) NOT NULL DEFAULT 0,
        runemetrics_public TINYINT(1) NULL,
        last_sync_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_profile_user_rsn (user_id, rsn_normalised),
        INDEX idx_profiles_user (user_id),
        INDEX idx_profiles_public (visibility),
        INDEX idx_profiles_primary (user_id, is_primary),
        CONSTRAINT fk_player_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_profile_settings (
        profile_id BIGINT UNSIGNED PRIMARY KEY,
        preferred_journey_id BIGINT UNSIGNED NULL,
        preferred_playstyle VARCHAR(80) NULL,
        ui_preferences_json JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_profile_settings_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_login_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        discord_id VARCHAR(32) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        was_successful TINYINT(1) NOT NULL DEFAULT 0,
        failure_reason VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_auth_events_user (user_id),
        INDEX idx_auth_events_created (created_at),
        CONSTRAINT fk_auth_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    seed_permissions_and_roles();
}

function seed_permissions_and_roles(): void
{
    $pdo = db();
    $permissions = [
        ['admin.access', 'Access admin area', 'Allows access to the admin dashboard.'],
        ['users.view', 'View users', 'Allows viewing registered users.'],
        ['users.manage', 'Manage users', 'Allows activating/deactivating users and changing roles.'],
        ['roles.manage', 'Manage roles', 'Allows changing role permissions.'],
        ['profiles.view', 'View profiles', 'Allows viewing player profiles in the admin area.'],
        ['profiles.manage', 'Manage profiles', 'Allows moderating player profiles.'],
    ];

    $stmt = $pdo->prepare("INSERT INTO permissions (slug, name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");
    foreach ($permissions as $permission) {
        $stmt->execute($permission);
    }

    $roles = [
        ['owner', 'Owner', 'Full platform access.', 1],
        ['admin', 'Admin', 'Can manage users and settings.', 1],
        ['member', 'Member', 'Default logged-in user.', 1],
    ];
    $stmt = $pdo->prepare("INSERT INTO roles (slug, name, description, is_system) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_system = VALUES(is_system)");
    foreach ($roles as $role) {
        $stmt->execute($role);
    }

    grant_role_permissions('owner', ['admin.access', 'users.view', 'users.manage', 'roles.manage', 'profiles.view', 'profiles.manage']);
    grant_role_permissions('admin', ['admin.access', 'users.view', 'users.manage', 'profiles.view']);
}

function grant_role_permissions(string $roleSlug, array $permissionSlugs): void
{
    $pdo = db();
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = ?");
    $roleStmt->execute([$roleSlug]);
    $role = $roleStmt->fetch();
    if (!$role) return;

    $permStmt = $pdo->prepare("SELECT id FROM permissions WHERE slug = ?");
    $insert = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($permissionSlugs as $slug) {
        $permStmt->execute([$slug]);
        $permission = $permStmt->fetch();
        if ($permission) {
            $insert->execute([(int)$role['id'], (int)$permission['id']]);
        }
    }
}
