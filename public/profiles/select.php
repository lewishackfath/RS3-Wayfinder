<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
require_post();
require_csrf();

$user = current_user();
$profileId = (int)($_POST['profile_id'] ?? 0);

try {
    set_active_profile($profileId, (int)$user['id']);
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

$back = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
if (!is_string($back) || $back === '' || str_contains($back, "\n") || str_contains($back, "\r")) {
    $back = '/dashboard.php';
}
header('Location: ' . $back);
exit;
