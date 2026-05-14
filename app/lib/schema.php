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
        nickname VARCHAR(100) NULL,
        discriminator VARCHAR(10) NULL,
        avatar_hash VARCHAR(128) NULL,
        email VARCHAR(255) NULL,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        is_banned TINYINT(1) NOT NULL DEFAULT 0,
        deletion_requested_at DATETIME NULL,
        deleted_at DATETIME NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_users_active (is_active),
        INDEX idx_users_banned (is_banned),
        INDEX idx_users_deletion_requested (deletion_requested_at),
        INDEX idx_users_deleted (deleted_at)
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
        created_by_user_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journeys_published (is_published, sort_order),
        INDEX idx_journeys_created_by (created_by_user_id),
        CONSTRAINT fk_journeys_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
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
        content_item_id BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_journey_steps_chapter (chapter_id, sort_order),
        INDEX idx_journey_steps_rule (auto_rule_type),
        INDEX idx_journey_steps_requires (requires_step_id),
        INDEX idx_journey_steps_content (content_item_id),
        CONSTRAINT fk_journey_steps_chapter FOREIGN KEY (chapter_id) REFERENCES journey_chapters(id) ON DELETE CASCADE,
        CONSTRAINT fk_journey_steps_content FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE SET NULL,
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



    $pdo->exec("CREATE TABLE IF NOT EXISTS journey_tags (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(80) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        description TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS journey_tag_map (
        journey_id BIGINT UNSIGNED NOT NULL,
        tag_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (journey_id, tag_id),
        INDEX idx_journey_tag_map_tag (tag_id),
        CONSTRAINT fk_journey_tag_map_journey FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
        CONSTRAINT fk_journey_tag_map_tag FOREIGN KEY (tag_id) REFERENCES journey_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_interests (
        profile_id BIGINT UNSIGNED NOT NULL,
        tag_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (profile_id, tag_id),
        INDEX idx_profile_interests_tag (tag_id),
        CONSTRAINT fk_profile_interests_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_profile_interests_tag FOREIGN KEY (tag_id) REFERENCES journey_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    $pdo->exec("CREATE TABLE IF NOT EXISTS user_remember_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        selector CHAR(24) NOT NULL UNIQUE,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        last_used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_remember_user (user_id),
        INDEX idx_remember_expires (expires_at),
        CONSTRAINT fk_user_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    $pdo->exec("CREATE TABLE IF NOT EXISTS content_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type ENUM('quest','achievement','task','boss','unlock','item') NOT NULL,
        name VARCHAR(220) NOT NULL,
        slug VARCHAR(240) NOT NULL UNIQUE,
        description TEXT NULL,
        category VARCHAR(120) NULL,
        source_url TEXT NULL,
        icon_url TEXT NULL,
        metadata_json JSON NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_content_type (type, is_active),
        INDEX idx_content_category (category),
        FULLTEXT KEY ft_content_search (name, description, category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");



    $pdo->exec("CREATE TABLE IF NOT EXISTS content_type_configs (
        type_slug VARCHAR(40) PRIMARY KEY,
        label VARCHAR(120) NOT NULL,
        description TEXT NULL,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        allow_skill_requirements TINYINT(1) NOT NULL DEFAULT 0,
        allow_quest_requirements TINYINT(1) NOT NULL DEFAULT 0,
        allow_achievement_requirements TINYINT(1) NOT NULL DEFAULT 0,
        allow_boss_drop_links TINYINT(1) NOT NULL DEFAULT 0,
        custom_fields_json JSON NULL,
        sort_order INT NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_achievement_requirements (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        content_item_id BIGINT UNSIGNED NOT NULL,
        required_content_item_id BIGINT UNSIGNED NOT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_content_achievement_req (content_item_id, required_content_item_id),
        INDEX idx_content_achievement_req_item (content_item_id),
        CONSTRAINT fk_content_achievement_req_item FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_content_achievement_req_required FOREIGN KEY (required_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_skill_requirements (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        content_item_id BIGINT UNSIGNED NOT NULL,
        skill_name VARCHAR(80) NOT NULL,
        required_level INT NOT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_content_skill_req_item (content_item_id),
        CONSTRAINT fk_content_skill_req_item FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_quest_requirements (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        content_item_id BIGINT UNSIGNED NOT NULL,
        required_content_item_id BIGINT UNSIGNED NOT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_content_quest_req (content_item_id, required_content_item_id),
        INDEX idx_content_quest_req_item (content_item_id),
        CONSTRAINT fk_content_quest_req_item FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_content_quest_req_required FOREIGN KEY (required_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS content_relationships (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_content_item_id BIGINT UNSIGNED NOT NULL,
        relationship_type ENUM('requires','unlocks','related_to','contains','part_of') NOT NULL,
        target_content_item_id BIGINT UNSIGNED NOT NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_content_relationship (source_content_item_id, relationship_type, target_content_item_id),
        INDEX idx_content_relationship_source (source_content_item_id),
        INDEX idx_content_relationship_target (target_content_item_id),
        CONSTRAINT fk_content_relationship_source FOREIGN KEY (source_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_content_relationship_target FOREIGN KEY (target_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS boss_drop_sources (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        boss_content_item_id BIGINT UNSIGNED NOT NULL,
        drop_content_item_id BIGINT UNSIGNED NOT NULL,
        rarity VARCHAR(80) NULL,
        quantity VARCHAR(80) NULL,
        notes TEXT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_boss_drop_source (boss_content_item_id, drop_content_item_id),
        INDEX idx_boss_drop_source_boss (boss_content_item_id),
        INDEX idx_boss_drop_source_drop (drop_content_item_id),
        CONSTRAINT fk_boss_drop_sources_boss FOREIGN KEY (boss_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_boss_drop_sources_drop FOREIGN KEY (drop_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");




    $pdo->exec("CREATE TABLE IF NOT EXISTS player_boss_drop_log (
        profile_id BIGINT UNSIGNED NOT NULL,
        boss_content_item_id BIGINT UNSIGNED NOT NULL,
        drop_content_item_id BIGINT UNSIGNED NOT NULL,
        is_obtained TINYINT(1) NOT NULL DEFAULT 1,
        obtained_at DATETIME NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (profile_id, boss_content_item_id, drop_content_item_id),
        INDEX idx_player_boss_drop_log_profile (profile_id),
        INDEX idx_player_boss_drop_log_boss (boss_content_item_id),
        INDEX idx_player_boss_drop_log_drop (drop_content_item_id),
        CONSTRAINT fk_player_boss_drop_log_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_player_boss_drop_log_boss FOREIGN KEY (boss_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE,
        CONSTRAINT fk_player_boss_drop_log_drop FOREIGN KEY (drop_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS player_boss_killcounts (
        profile_id BIGINT UNSIGNED NOT NULL,
        boss_content_item_id BIGINT UNSIGNED NOT NULL,
        kill_count INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (profile_id, boss_content_item_id),
        INDEX idx_player_boss_kc_profile (profile_id),
        INDEX idx_player_boss_kc_boss (boss_content_item_id),
        CONSTRAINT fk_player_boss_kc_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_player_boss_kc_boss FOREIGN KEY (boss_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS profile_achievement_progress (
        profile_id BIGINT UNSIGNED NOT NULL,
        achievement_content_item_id BIGINT UNSIGNED NOT NULL,
        is_completed TINYINT(1) NOT NULL DEFAULT 1,
        completed_at DATETIME NULL,
        source ENUM('manual','sync','admin') NOT NULL DEFAULT 'manual',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (profile_id, achievement_content_item_id),
        INDEX idx_profile_achievement_progress_profile (profile_id),
        INDEX idx_profile_achievement_progress_achievement (achievement_content_item_id),
        CONSTRAINT fk_profile_achievement_progress_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
        CONSTRAINT fk_profile_achievement_progress_achievement FOREIGN KEY (achievement_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
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
    seed_default_journey_tags();
    seed_content_type_configs();
    run_schema_migrations();

    run_once_migration('20260513_journey_steps_content_item_link', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM journey_steps")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('content_item_id', $cols, true)) {
            $pdo->exec("ALTER TABLE journey_steps ADD COLUMN content_item_id BIGINT UNSIGNED NULL AFTER requires_step_id");
            $pdo->exec("ALTER TABLE journey_steps ADD INDEX idx_journey_steps_content (content_item_id)");
            try {
                $pdo->exec("ALTER TABLE journey_steps ADD CONSTRAINT fk_journey_steps_content FOREIGN KEY (content_item_id) REFERENCES content_items(id) ON DELETE SET NULL");
            } catch (Throwable $e) {
                // Shared hosts may block FK creation. Column + index are enough for the app.
            }
        }
    });
}


function run_schema_migrations(): void
{
    run_once_migration('20260511_normalise_runemetrics_skill_xp', function (PDO $pdo): void {
        // Versions before this migration stored RuneMetrics skillvalues[].xp directly.
        // RuneMetrics returns individual skill XP multiplied by 10, so repair existing parsed rows once.
        $pdo->exec('UPDATE player_latest_skills SET xp = FLOOR(xp / 10) WHERE xp IS NOT NULL');
        $pdo->exec('UPDATE player_skill_snapshots SET xp = FLOOR(xp / 10) WHERE xp IS NOT NULL');
    });




    run_once_migration('20260513_journey_creator_tracking', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM journeys")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('created_by_user_id', $cols, true)) {
            $pdo->exec("ALTER TABLE journeys ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL AFTER sort_order");
            try {
                $pdo->exec("ALTER TABLE journeys ADD INDEX idx_journeys_created_by (created_by_user_id)");
            } catch (Throwable $e) {
                // Index may already exist on repaired installs.
            }
            try {
                $pdo->exec("ALTER TABLE journeys ADD CONSTRAINT fk_journeys_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL");
            } catch (Throwable $e) {
                // Column and index are enough for permission-aware screens.
            }
        }
    });



    run_once_migration('20260513_user_nickname', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('nickname', $cols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(100) NULL AFTER global_name");
        }
    });

    run_once_migration('20260513_user_ban_flag', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('is_banned', $cols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
            try {
                $pdo->exec("ALTER TABLE users ADD INDEX idx_users_banned (is_banned)");
            } catch (Throwable $e) {
                // Index may already exist on some repaired installs.
            }
        }
    });


    run_once_migration('20260513_soft_delete_accounts', function (PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('deletion_requested_at', $cols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN deletion_requested_at DATETIME NULL AFTER is_banned");
        }
        if (!in_array('deleted_at', $cols, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL AFTER deletion_requested_at");
        }
        try { $pdo->exec("ALTER TABLE users ADD INDEX idx_users_deletion_requested (deletion_requested_at)"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE users ADD INDEX idx_users_deleted (deleted_at)"); } catch (Throwable $e) {}
    });


    run_once_migration('20260513_remove_legacy_drop_content', function (PDO $pdo): void {
        // Convert legacy content records before narrowing the ENUM.
        $pdo->exec("UPDATE content_items SET type = 'item' WHERE type = 'drop'");
        $pdo->exec("DELETE FROM content_type_configs WHERE type_slug = 'drop'");

        // Remove the old drop_items metadata table. Item metadata now lives on content_items/custom fields.
        $pdo->exec("DROP TABLE IF EXISTS drop_items");

        // Narrow the content_items.type enum so legacy drops cannot be created again.
        try {
            $pdo->exec("ALTER TABLE content_items MODIFY type ENUM('quest','achievement','task','boss','unlock','item') NOT NULL");
        } catch (Throwable $e) {
            // Some MySQL/MariaDB installs can reject enum changes while old constraints/indexes are being repaired.
            // The application-level type list still prevents new drop records.
        }
    });

    run_once_migration('20260514_boss_kc_and_achievement_progress', function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS player_boss_killcounts (
            profile_id BIGINT UNSIGNED NOT NULL,
            boss_content_item_id BIGINT UNSIGNED NOT NULL,
            kill_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (profile_id, boss_content_item_id),
            INDEX idx_player_boss_kc_profile (profile_id),
            INDEX idx_player_boss_kc_boss (boss_content_item_id),
            CONSTRAINT fk_player_boss_kc_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
            CONSTRAINT fk_player_boss_kc_boss FOREIGN KEY (boss_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS profile_achievement_progress (
            profile_id BIGINT UNSIGNED NOT NULL,
            achievement_content_item_id BIGINT UNSIGNED NOT NULL,
            is_completed TINYINT(1) NOT NULL DEFAULT 1,
            completed_at DATETIME NULL,
            source ENUM('manual','sync','admin') NOT NULL DEFAULT 'manual',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (profile_id, achievement_content_item_id),
            INDEX idx_profile_achievement_progress_profile (profile_id),
            INDEX idx_profile_achievement_progress_achievement (achievement_content_item_id),
            CONSTRAINT fk_profile_achievement_progress_profile FOREIGN KEY (profile_id) REFERENCES player_profiles(id) ON DELETE CASCADE,
            CONSTRAINT fk_profile_achievement_progress_achievement FOREIGN KEY (achievement_content_item_id) REFERENCES content_items(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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


function seed_content_type_configs(): void
{
    $configs = [
        ['quest', 'Quest', 'Story quests imported from RuneMetrics or maintained by admins.', 1, 1, 1, 0, 0, json_encode([
            ['key' => 'quest_timeline', 'label' => 'Timeline', 'type' => 'text', 'placeholder' => 'Fifth Age / Sixth Age / Fort Forinthry'],
            ['key' => 'quest_series', 'label' => 'Series', 'type' => 'text', 'placeholder' => 'Mahjarrat / Elf / Pirate'],
        ]), 10],
        ['achievement', 'Achievement', 'Achievement, task and RuneScore-style progression records.', 1, 1, 1, 1, 0, json_encode([
            ['key' => 'category', 'label' => 'Achievement Category', 'type' => 'text', 'placeholder' => 'Exploration / Combat / Skills'],
            ['key' => 'subcategory', 'label' => 'Subcategory', 'type' => 'text', 'placeholder' => 'Area Tasks / Bosses / Lore'],
            ['key' => 'subsubcategory', 'label' => 'Sub-subcategory', 'type' => 'text', 'placeholder' => 'Desert / Morytania / God Wars'],
        ]), 20],
        ['task', 'Task', 'General manually tracked progression tasks.', 1, 1, 1, 1, 0, json_encode([]), 30],
        ['boss', 'Boss', 'Bosses that can have unlock requirements and drop table links.', 1, 1, 1, 0, 1, json_encode([
            ['key' => 'boss_image_url', 'label' => 'Boss Image URL', 'type' => 'url', 'placeholder' => 'Optional large boss image URL'],
            ['key' => 'combat_style', 'label' => 'Combat Style', 'type' => 'text', 'placeholder' => 'Melee / Magic / Necromancy / Hybrid'],
        ]), 40],
        ['item', 'Item', 'Reusable items, drops, unlocks and collection log entries.', 1, 1, 1, 0, 0, json_encode([
            ['key' => 'item_source', 'label' => 'Item Source', 'type' => 'text', 'placeholder' => 'Boss drop / skilling / shop / quest reward'],
        ]), 50],
        ['unlock', 'Unlock', 'Unlockable features, areas, abilities and account access.', 1, 1, 1, 0, 0, json_encode([]), 70],
    ];

    $stmt = db()->prepare("INSERT INTO content_type_configs
        (type_slug, label, description, is_enabled, allow_skill_requirements, allow_quest_requirements, allow_achievement_requirements, allow_boss_drop_links, custom_fields_json, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            label = VALUES(label),
            description = VALUES(description),
            is_enabled = VALUES(is_enabled),
            sort_order = VALUES(sort_order)");
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
}

function seed_default_journey_tags(): void
{
    $tags = [
        ['pvm', 'PvM', 'Bossing, combat progression and combat unlocks.', 10],
        ['questing', 'Questing', 'Quest progression, unlock quests and story paths.', 20],
        ['completionist', 'Completionist', 'Completionist, comp cape and broad account completion.', 30],
        ['skilling', 'Skilling', 'Skill training, skilling unlocks and non-combat progression.', 40],
        ['ironman', 'Ironman', 'Ironman-friendly progression and self-sufficient unlock paths.', 50],
        ['new-player', 'New Player', 'Beginner friendly progression for newer accounts.', 60],
        ['returning-player', 'Returning Player', 'Catch-up paths for players returning to RuneScape.', 70],
        ['lore', 'Lore', 'Story, lore and world exploration.', 80],
        ['clues', 'Clues', 'Clue scroll progression and unlocks.', 90],
    ];

    $stmt = db()->prepare("INSERT INTO journey_tags (slug, name, description, sort_order)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), sort_order = VALUES(sort_order)");
    foreach ($tags as $tag) {
        $stmt->execute($tag);
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
        ['journeys.delete', 'Delete own journeys', 'Allows deleting journeys created by the current user.'],
        ['journeys.delete.all', 'Delete all journeys', 'Allows deleting any journey regardless of creator.'],
        ['journeys.edit.all', 'Edit all journeys', 'Allows editing any journey regardless of creator.'],
        ['content.view', 'View content library', 'Allows viewing the admin content library.'],
        ['content.manage', 'Manage content library', 'Allows creating and editing quests, achievements, bosses and items.'],
        ['content.delete', 'Delete non-quest content library items', 'Allows deleting content library items except quests.'],
        ['users.manage', 'Manage users', 'Allows blocking, banning and deleting users.'],
        ['profiles.delete', 'Delete player profiles', 'Allows deleting player profiles.'],
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

    grant_role_permissions('owner', ['admin.access', 'users.view', 'users.manage', 'roles.manage', 'profiles.view', 'profiles.manage', 'profiles.delete', 'journeys.view', 'journeys.manage', 'journeys.delete', 'journeys.delete.all', 'journeys.edit.all', 'content.view', 'content.manage', 'content.delete']);
    grant_role_permissions('admin', ['admin.access', 'users.view', 'users.manage', 'profiles.view', 'profiles.manage', 'profiles.delete', 'journeys.view', 'journeys.manage', 'journeys.delete', 'content.view', 'content.manage', 'content.delete']);
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
