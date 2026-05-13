<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('journeys.manage');
require_post();
require_csrf();

$action = (string)($_POST['action'] ?? '');
$backJourneyId = (int)($_POST['journey_id'] ?? 0);

try {
    if ($action === 'duplicate_journey') {
        $newId = duplicate_journey((int)($_POST['journey_id'] ?? 0));
        redirect('/admin/journey_view.php?id=' . $newId);
    }

    if ($action === 'delete_journey') {
        delete_journey((int)($_POST['journey_id'] ?? 0));
        redirect('/admin/journeys.php');
    }

    if ($action === 'duplicate_chapter') {
        duplicate_chapter((int)($_POST['chapter_id'] ?? 0));
    } elseif ($action === 'duplicate_step') {
        duplicate_step((int)($_POST['step_id'] ?? 0));
    } elseif ($action === 'delete_chapter') {
        delete_chapter((int)($_POST['chapter_id'] ?? 0));
    } elseif ($action === 'delete_step') {
        delete_step((int)($_POST['step_id'] ?? 0));
    } elseif ($action === 'move_chapter') {
        move_chapter((int)($_POST['chapter_id'] ?? 0), (string)($_POST['direction'] ?? ''));
    } elseif ($action === 'move_step') {
        move_step((int)($_POST['step_id'] ?? 0), (string)($_POST['direction'] ?? ''));
    } else {
        throw new InvalidArgumentException('Unknown journey action.');
    }
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
}

if ($backJourneyId <= 0) {
    $backJourneyId = (int)($_POST['back_journey_id'] ?? 0);
}
redirect($backJourneyId > 0 ? '/admin/journey_view.php?id=' . $backJourneyId : '/admin/journeys.php');
