<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('journeys.manage');

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    redirect('/admin/journey_view.php?id=' . $id);
}
redirect('/admin/journeys.php');
