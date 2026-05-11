<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('users.manage');
require_post();
require_csrf();

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    abort_page(400, 'Invalid user.');
}

$roleIds = $_POST['role_ids'] ?? [];
if (!is_array($roleIds)) {
    $roleIds = [];
}

$pdo = db();
$pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([!empty($_POST['is_active']) ? 1 : 0, $userId]);
set_user_roles($userId, $roleIds, (int)current_user()['id']);
redirect('/admin/users.php');
