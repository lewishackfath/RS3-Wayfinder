<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();

$user = current_user();
$active = active_profile();
if (!$active) {
    redirect('/profiles/new.php');
}

$journeyId = (int)($_GET['id'] ?? 0);
$journey = journey_by_id($journeyId);
$isAdminPreview = (($_GET['preview'] ?? '') === '1') && current_user_can('journeys.view');
if (!$journey || ((int)$journey['is_published'] !== 1 && !$isAdminPreview)) {
    abort_page(404, 'Journey not found.');
}

$notice = null;
$playerJourney = player_journey((int)$active['id'], $journeyId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'enable') {
            start_journey_for_profile((int)$active['id'], $journeyId);
            $notice = 'Journey enabled.';
        } elseif ($action === 'disable') {
            stop_journey_for_profile((int)$active['id'], $journeyId);
            $notice = 'Journey disabled.';
        } elseif ($action === 'toggle_step') {
            if (!$playerJourney) {
                throw new InvalidArgumentException('Enable this journey before tracking steps.');
            }
            $stepId = (int)($_POST['step_id'] ?? 0);
            $completed = !empty($_POST['completed']);
            manually_set_step_progress((int)$active['id'], $stepId, $completed);
            $notice = $completed ? 'Step marked complete.' : 'Step marked incomplete.';
        }
        $playerJourney = player_journey((int)$active['id'], $journeyId);
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}

$isEnabled = (bool)$playerJourney;

if ($isEnabled) {
    try {
        runemetrics_sync_profile_if_due($active);
        $active = active_profile();
    } catch (Throwable $e) {
        // Journey can still render with cached data.
    }
}

$chapters = chapters_for_journey($journeyId);
$journeyRecommendations = [];
if ($isEnabled) {
    $progress = evaluate_journey_progress((int)$active['id'], $journeyId);
    $allSteps = $progress['steps'];
    foreach (($progress['recommended'] ?? []) as $recommendedStep) {
        $journeyRecommendations[] = recommendation_from_step((int)$active['id'], $journey, $recommendedStep);
    }
} else {
    $allSteps = steps_for_journey($journeyId);
    foreach ($allSteps as &$step) {
        $step['is_completed'] = false;
        $step['auto_complete'] = false;
        $step['can_complete_manually'] = in_array($step['completion_mode'], ['manual_only', 'auto_or_manual'], true);
    }
    unset($step);
    $progress = ['steps' => $allSteps, 'total' => count($allSteps), 'completed' => 0, 'percent' => 0];
}
$stepsByChapter = [];
foreach ($allSteps as $step) {
    $stepsByChapter[(int)$step['chapter_id']][] = $step;
}

page_header($journey['name']);
?>
<div class="page-title-row">
    <div>
        <h1><?= e($journey['icon'] ?: '🧭') ?> <?= e($journey['name']) ?></h1>
        <p class="muted"><?= nl2br(e($journey['description'] ?: 'No description yet.')) ?></p>
        <p class="muted small">Active profile: <?= e($active['rsn']) ?><?= $isEnabled ? ' • tracking enabled' : ' • preview only' ?><?= $isAdminPreview ? ' • admin preview' : '' ?></p>
    </div>
    <a class="button secondary" href="/journeys/index.php">All journeys</a>
</div>

<?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>
<?php if ($isAdminPreview): ?><div class="notice">Admin preview: this journey can be viewed even if it is not published.</div><?php endif; ?>

<div class="card">
    <div class="journey-progress-summary">
        <div>
            <h2><?= e((string)$progress['percent']) ?>%</h2>
            <p class="muted">
                <?php if ($isEnabled): ?>
                    <?= (int)$progress['required_completed'] ?> of <?= (int)$progress['required_total'] ?> required steps complete
                <?php else: ?>
                    <?= (int)$progress['total'] ?> steps available. Enable this journey to begin tracking.
                <?php endif; ?>
            </p>
        </div>
        <div class="progress-bar large"><span style="width: <?= e((string)$progress['percent']) ?>%"></span></div>
    </div>

    <form method="post" class="inline-form">
        <?= csrf_field() ?>
        <?php if ($isEnabled): ?>
            <input type="hidden" name="action" value="disable">
            <button class="button secondary" type="submit">Disable journey</button>
        <?php else: ?>
            <input type="hidden" name="action" value="enable">
            <button class="button" type="submit">Enable journey</button>
        <?php endif; ?>
    </form>
</div>

<?php if ($isEnabled && !empty($journeyRecommendations)): ?>
    <div class="card recommended-steps-card">
        <div class="page-title-row compact">
            <div>
                <h2>Recommended next steps</h2>
                <p class="muted">These are the next available incomplete steps for <?= e($active['rsn']) ?>.</p>
            </div>
        </div>
        <div class="recommended-step-list">
            <?php foreach ($journeyRecommendations as $rec): ?>
                <a class="recommended-step rich" href="<?= e($rec['cta_url']) ?>">
                    <span class="step-status">○</span>
                    <span>
                        <strong><?= e($rec['title']) ?></strong>
                        <small><?= e($rec['summary']) ?> • <?= e($rec['detail']) ?></small>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php elseif ($isEnabled && (int)$progress['required_total'] > 0 && (int)$progress['required_completed'] >= (int)$progress['required_total']): ?>
    <div class="card recommended-steps-card">
        <h2>Main path complete</h2>
        <p class="muted">All required steps are complete. Optional steps can still be ticked off if you want to fully clear the journey.</p>
    </div>
<?php endif; ?>

<?php foreach ($chapters as $chapter): ?>
    <div class="card journey-chapter">
        <h2><?= e($chapter['title']) ?></h2>
        <?php if (!empty($chapter['description'])): ?><p class="muted"><?= nl2br(e($chapter['description'])) ?></p><?php endif; ?>

        <?php foreach (($stepsByChapter[(int)$chapter['id']] ?? []) as $step): ?>
            <div id="step-<?= (int)$step['id'] ?>" class="journey-step <?= !empty($step['is_completed']) ? 'complete' : '' ?> <?= !empty($step['is_locked']) ? 'locked' : '' ?> <?= !empty($step['is_optional']) ? 'optional' : '' ?>">
                <div class="step-status"><?= !empty($step['is_completed']) ? '✓' : '○' ?></div>
                <div class="step-body">
                    <h3><?= e($step['title']) ?></h3>
                    <?php if (!empty($step['description'])): ?><p class="muted"><?= nl2br(e($step['description'])) ?></p><?php endif; ?>
                    <p class="muted small">
                        <?= e(completion_mode_label((string)$step['completion_mode'])) ?> • <?= e(rule_summary($step)) ?>
                        <?php if (!empty($step['is_optional'])): ?> • Optional<?php endif; ?>
                        <?php if (!empty($step['is_locked'])): ?> • <?= e($step['lock_reason'] ?? 'Locked') ?><?php endif; ?>
                        <?php if (!empty($step['is_completed'])): ?> • <?= e(!empty($step['auto_complete']) ? 'Completed automatically' : 'Completed manually') ?><?php endif; ?>
                    </p>
                </div>
                <div class="step-actions">
                    <?php if (!$isEnabled): ?>
                        <span class="badge">Preview</span>
                    <?php elseif (!empty($step['is_locked'])): ?>
                        <span class="badge">Locked</span>
                    <?php elseif (!empty($step['can_complete_manually'])): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_step">
                            <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                            <input type="hidden" name="completed" value="<?= !empty($step['is_completed']) ? '0' : '1' ?>">
                            <button class="button secondary" type="submit"><?= !empty($step['is_completed']) ? 'Untick' : 'Tick off' ?></button>
                        </form>
                    <?php else: ?>
                        <span class="badge">Automatic</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php page_footer(); ?>
