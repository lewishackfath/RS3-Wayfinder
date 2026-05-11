<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

try {
    bootstrap_schema();
    $expectedState = $_SESSION['discord_oauth_state'] ?? '';
    $actualState = $_GET['state'] ?? '';
    unset($_SESSION['discord_oauth_state']);

    if (!is_string($expectedState) || !is_string($actualState) || $expectedState === '' || !hash_equals($expectedState, $actualState)) {
        throw new RuntimeException('Invalid Discord OAuth state.');
    }

    if (!empty($_GET['error'])) {
        throw new RuntimeException('Discord OAuth error: ' . (string)$_GET['error']);
    }

    $code = $_GET['code'] ?? '';
    if (!is_string($code) || $code === '') {
        throw new RuntimeException('Discord did not return an authorisation code.');
    }

    $token = discord_exchange_code($code);
    $discordUser = discord_get_current_user((string)$token['access_token']);
    $userId = upsert_discord_user($discordUser);
    login_user($userId);
    redirect('/dashboard.php');
} catch (Throwable $e) {
    try { log_auth_event(null, null, false, $e->getMessage()); } catch (Throwable $ignored) {}
    if (is_debug()) {
        abort_page(500, $e->getMessage());
    }
    abort_page(500, 'Login failed. Please try again.');
}
