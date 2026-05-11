<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('admin.access');
page_header('Admin');
?>
<div class="card">
    <h1>Admin</h1>
    <p class="muted">Manage RS3 Wayfinder users and permissions.</p>
    <p><a class="button" href="/admin/users.php">Manage Users</a></p>
</div>
<?php page_footer(); ?>
