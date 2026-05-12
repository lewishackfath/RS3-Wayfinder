<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.view');

$journeyId = (int)($_GET['id'] ?? 0);
$journey = journey_by_id($journeyId);
if (!$journey) {
    abort_page(404, 'Journey not found.');
}
$chapters = chapters_for_journey($journeyId);
page_header('Manage Journey');
?>
<div class="page-title-row">
    <div>
        <h1><?= e($journey['icon'] ?: '🧭') ?> <?= e($journey['name']) ?></h1>
        <p class="muted"><?= nl2br(e($journey['description'] ?: 'No description yet.')) ?></p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="/admin/journeys.php">All journeys</a>
        <?php if (current_user_can('journeys.manage')): ?>
            <a class="button secondary" href="/admin/journey_edit.php?id=<?= (int)$journey['id'] ?>">Edit journey</a>
            <a class="button" href="/admin/chapter_edit.php?journey_id=<?= (int)$journey['id'] ?>">Add chapter</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$chapters): ?>
    <div class="card"><p class="muted">No chapters yet. Add your first chapter to start building this journey.</p></div>
<?php endif; ?>

<?php foreach ($chapters as $chapter): ?>
    <?php $steps = steps_for_chapter((int)$chapter['id']); ?>
    <div class="card">
        <div class="page-title-row compact">
            <div>
                <h2><?= e($chapter['title']) ?></h2>
                <p class="muted"><?= nl2br(e($chapter['description'] ?: 'No description.')) ?></p>
                <p class="muted small">Sort order: <?= (int)$chapter['sort_order'] ?></p>
            </div>
            <?php if (current_user_can('journeys.manage')): ?>
                <div class="form-actions">
                    <a class="button secondary" href="/admin/chapter_edit.php?id=<?= (int)$chapter['id'] ?>">Edit chapter</a>
                    <a class="button" href="/admin/step_edit.php?chapter_id=<?= (int)$chapter['id'] ?>">Add step</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$steps): ?>
            <p class="muted">No steps in this chapter yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Step</th><th>Completion</th><th>Rule</th><th>Sort</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($steps as $step): ?>
                    <tr>
                        <td><strong><?= e($step['title']) ?></strong><br><span class="muted small"><?= e($step['description'] ?: '') ?></span></td>
                        <td><?= e(completion_mode_label((string)$step['completion_mode'])) ?></td>
                        <td><?= e(rule_summary($step)) ?></td>
                        <td><?= (int)$step['sort_order'] ?></td>
                        <td><?php if (current_user_can('journeys.manage')): ?><a class="button secondary" href="/admin/step_edit.php?id=<?= (int)$step['id'] ?>">Edit</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php page_footer(); ?>
