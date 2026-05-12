<?php
declare(strict_types=1);

function bootstrap_schema(): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_migrations (
        migration_key VARCHAR(120) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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



    $pdo->exec("CREATE TABLE IF NOT EXISTS runemetrics_fetches (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_id BIGINT UNSIGNED NOT NULL,
        endpoint VARCHAR(40) NOT NULL,
        request_url TEXT NOT NULL,
        http_status INT NULL,
        was_successful TINYINT(1) NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        response_json LONGTEXT NULL,
        fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rm_fetches_profile_endpoint (profile_id, endpoint, fetched_at),
        CONSTRAINT fk_rm_fetches_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_profile_metrics (
        profile_id BIGINT UNSIGNED PRIMARY KEY,
        display_name VARCHAR(100) NULL,
        overall_rank BIGINT NULL,
        total_level INT NULL,
        total_xp BIGINT NULL,
        combat_level INT NULL,
        melee_xp BIGINT NULL,
        magic_xp BIGINT NULL,
        ranged_xp BIGINT NULL,
        quests_started INT NULL,
        quests_complete INT NULL,
        quests_not_started INT NULL,
        logged_in TINYINT(1) NULL,
        last_profile_fetch_at DATETIME NULL,
        last_quest_fetch_at DATETIME NULL,
        last_successful_sync_at DATETIME NULL,
        last_sync_attempt_at DATETIME NULL,
        last_sync_status VARCHAR(30) NULL,
        last_sync_error TEXT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_profile_metrics_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_skill_snapshots (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_id BIGINT UNSIGNED NOT NULL,
        skill_id INT NOT NULL,
        skill_name VARCHAR(80) NOT NULL,
        level INT NULL,
        xp BIGINT NULL,
        rank BIGINT NULL,
        fetched_at DATETIME NOT NULL,
        UNIQUE KEY uniq_skill_snapshot (profile_id, skill_id, fetched_at),
        INDEX idx_skill_latest (profile_id, skill_id, fetched_at),
        CONSTRAINT fk_skill_snapshots_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_latest_skills (
        profile_id BIGINT UNSIGNED NOT NULL,
        skill_id INT NOT NULL,
        skill_name VARCHAR(80) NOT NULL,
        level INT NULL,
        xp BIGINT NULL,
        rank BIGINT NULL,
        fetched_at DATETIME NOT NULL,
        PRIMARY KEY (profile_id, skill_id),
        CONSTRAINT fk_latest_skills_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_activity_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        profile_id BIGINT UNSIGNED NOT NULL,
        activity_date_raw VARCHAR(80) NULL,
        activity_date_utc DATETIME NULL,
        activity_text VARCHAR(255) NULL,
        activity_details TEXT NULL,
        raw_json JSON NULL,
        source_hash CHAR(64) NOT NULL,
        first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_activity_source (profile_id, source_hash),
        INDEX idx_activities_profile_date (profile_id, activity_date_utc),
        CONSTRAINT fk_activity_logs_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_quest_statuses (
        profile_id BIGINT UNSIGNED NOT NULL,
        quest_title VARCHAR(255) NOT NULL,
        status VARCHAR(80) NULL,
        difficulty VARCHAR(80) NULL,
        quest_points INT NULL,
        raw_json JSON NULL,
        fetched_at DATETIME NOT NULL,
        PRIMARY KEY (profile_id, quest_title),
        INDEX idx_quests_profile_status (profile_id, status),
        CONSTRAINT fk_quest_statuses_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");


    $pdo->exec("CREATE TABLE IF NOT EXISTS journeys (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(160) NOT NULL,
        slug VARCHAR(180) NOT NULL UNIQUE,
        description TEXT NULL,
        icon VARCHAR(120) NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journeys_published (is_published, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS journey_chapters (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        journey_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journey_chapters_journey (journey_id, sort_order),
        CONSTRAINT fk_journey_chapters_journey FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS journey_steps (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        chapter_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(220) NOT NULL,
        description TEXT NULL,
        completion_mode ENUM('auto_only','manual_only','auto_or_manual') NOT NULL DEFAULT 'manual_only',
        auto_rule_type ENUM('skill_level','quest_complete') NULL,
        rule_skill_name VARCHAR(80) NULL,
        rule_level INT NULL,
        rule_quest_title VARCHAR(255) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_optional TINYINT(1) NOT NULL DEFAULT 0,
        requires_step_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journey_steps_chapter (chapter_id, sort_order),
        INDEX idx_journey_steps_rule (auto_rule_type),
        INDEX idx_journey_steps_requires (requires_step_id),
        CONSTRAINT fk_journey_steps_chapter FOREIGN KEY (chapter_id) REFERENCES journey_chapters(id) ON DELETE CASCADE,
        CONSTRAINT fk_journey_steps_requires FOREIGN KEY (requires_step_id) REFERENCES journey_steps(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_journeys (
        profile_id BIGINT UNSIGNED NOT NULL,
        journey_id BIGINT UNSIGNED NOT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        PRIMARY KEY (profile_id, journey_id),
        CONSTRAINT fk_player_journeys_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_player_journeys_journey FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_step_progress (
        profile_id BIGINT UNSIGNED NOT NULL,
        step_id BIGINT UNSIGNED NOT NULL,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        completion_source ENUM('manual','automatic') NULL,
        completed_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (profile_id, step_id),
        INDEX idx_player_step_progress_completed (profile_id, is_completed),
        CONSTRAINT fk_player_step_progress_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_player_step_progress_step FOREIGN KEY (step_id) REFERENCES journey_steps(id) ON DELETE CASCADE
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
    run_schema_migrations();
}

function run_schema_migrations(): void
{
    run_once_migration('20260511_normalise_runemetrics_skill_xp', function (PDO $pdo): void {
        // Versions before this migration stored RuneMetrics skillvalues[].xp directly.
        // RuneMetrics returns individual skill XP multiplied by 10, so repair existing parsed rows once.
        $pdo->exec('UPDATE player_latest_skills SET xp = FLOOR(xp / 10) WHERE xp IS NOT NULL');
        $pdo->exec('UPDATE player_skill_snapshots SET xp = FLOOR(xp / 10) WHERE xp IS NOT NULL');
    });

    run_once_migration('20260512_smart_journey_step_fields', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM journey_steps")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_optional', $cols, true)) {
            $pdo->exec("ALTER TABLE journey_steps ADD COLUMN is_optional TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order");
        }
        if (!in_array('requires_step_id', $cols, true)) {
            $pdo->exec("ALTER TABLE journey_steps ADD COLUMN requires_step_id BIGINT UNSIGNED NULL AFTER is_optional");
            $pdo->exec("ALTER TABLE journey_steps ADD INDEX idx_journey_steps_requires (requires_step_id)");
            // Foreign key may fail on some shared hosts if duplicate names exist, so ignore gracefully.
            try {
                $pdo->exec("ALTER TABLE journey_steps ADD CONSTRAINT fk_journey_steps_requires FOREIGN KEY (requires_step_id) REFERENCES journey_steps(id) ON DELETE SET NULL");
            } catch (Throwable $e) {
                // Column and index are enough for the app to function.
            }
        }
    });
}

function run_once_migration(string $key, callable $callback): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT migration_key FROM app_migrations WHERE migration_key = ? LIMIT 1');
    $stmt->execute([$key]);
    if ($stmt->fetch()) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $callback($pdo);
        $insert = $pdo->prepare('INSERT INTO app_migrations (migration_key) VALUES (?)');
        $insert->execute([$key]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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
        ['journeys.view', 'View journeys', 'Allows viewing journeys in the admin area.'],
        ['journeys.manage', 'Manage journeys', 'Allows creating and editing journeys, chapters and steps.'],
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

    grant_role_permissions('owner', ['admin.access', 'users.view', 'users.manage', 'roles.manage', 'profiles.view', 'profiles.manage', 'journeys.view', 'journeys.manage']);
    grant_role_permissions('admin', ['admin.access', 'users.view', 'users.manage', 'profiles.view', 'journeys.view', 'journeys.manage']);
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
