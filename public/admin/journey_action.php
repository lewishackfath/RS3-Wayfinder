<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_permission('journeys.manage');
require_post();
require_csrf();

$action = (string)($_POST['action'] ?? '');
$backJourneyId = (int)($_POST['journey_id'] ?? 0);

function require_journey_edit_access(int $journeyId): array
{
    $journey = journey_by_id($journeyId);
    if (!$journey) {
        throw new InvalidArgumentException('Journey not found.');
    }
    if (!journey_can_edit($journey)) {
        throw new RuntimeException('You do not have permission to edit this journey.');
    }
    return $journey;
}

function require_journey_delete_access(int $journeyId): array
{
    $journey = journey_by_id($journeyId);
    if (!$journey) {
        throw new InvalidArgumentException('Journey not found.');
    }
    if (!journey_can_delete_item($journey)) {
        throw new RuntimeException('You do not have permission to delete this journey.');
    }
    return $journey;
}

try {
    if ($action === 'duplicate_journey') {
        require_journey_edit_access((int)($_POST['journey_id'] ?? 0));
        $newId = duplicate_journey((int)($_POST['journey_id'] ?? 0));
        redirect('/admin/journey_view.php?id=' . $newId);
    }

    if ($action === 'delete_journey') {
        require_journey_delete_access((int)($_POST['journey_id'] ?? 0));
        delete_journey((int)($_POST['journey_id'] ?? 0));
        redirect('/admin/journeys.php');
    }

    require_journey_edit_access($backJourneyId);

    if ($action === 'reorder_chapters') {
        reorder_chapters_for_journey($backJourneyId, is_array($_POST['chapter_ids'] ?? null) ? $_POST['chapter_ids'] : []);
    } elseif ($action === 'reorder_steps') {
        $chapterId = (int)($_POST['chapter_id'] ?? 0);
        $chapter = chapter_by_id($chapterId);
        if (!$chapter || (int)$chapter['journey_id'] !== $backJourneyId) {
            throw new InvalidArgumentException('Chapter not found for this journey.');
        }
        reorder_steps_for_chapter($chapterId, is_array($_POST['step_ids'] ?? null) ? $_POST['step_ids'] : []);
    } elseif ($action === 'duplicate_chapter') {
        duplicate_chapter((int)($_POST['chapter_id'] ?? 0));
    } elseif ($action === 'duplicate_step') {
        duplicate_step((int)($_POST['step_id'] ?? 0));
    } elseif ($action === 'delete_chapter') {
        require_journey_delete_access($backJourneyId);
        delete_chapter((int)($_POST['chapter_id'] ?? 0));
    } elseif ($action === 'delete_step') {
        require_journey_delete_access($backJourneyId);
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
