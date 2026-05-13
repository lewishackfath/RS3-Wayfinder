<?php
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
env_load(dirname(__DIR__) . '/.env');

date_default_timezone_set((string) env('APP_TIMEZONE', 'Australia/Sydney'));

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/session.php';
require_once __DIR__ . '/lib/schema.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/permissions.php';
require_once __DIR__ . '/lib/profiles.php';
require_once __DIR__ . '/lib/accounts.php';
require_once __DIR__ . '/lib/skills.php';
require_once __DIR__ . '/lib/runemetrics.php';
require_once __DIR__ . '/lib/journeys.php';
require_once __DIR__ . '/lib/content.php';
require_once __DIR__ . '/lib/recommendations.php';
require_once __DIR__ . '/lib/discord_oauth.php';

start_app_session();
