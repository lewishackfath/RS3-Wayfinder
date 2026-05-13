<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.view');

$journeyId = (int)($_GET['id'] ?? 0);
$journey = journey_by_id($journeyId);
if (!$journey) {
    abort_page(404, 'Journey not found.');
}
$notice = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && current_user_can('journeys.manage')) {
    require_csrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'expand_content_prereqs') {
            $step = step_by_id((int)($_POST['step_id'] ?? 0));
            if (!$step || empty($step['content_item_id'])) {
                throw new InvalidArgumentException('This step is not linked to a content item.');
            }
            $created = add_content_prerequisite_steps_to_chapter((int)$step['chapter_id'], (int)$step['content_item_id'], (int)$step['id']);
            $notice = count($created) . ' prerequisite step' . (count($created) === 1 ? '' : 's') . ' added.';
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
    }
}
$chapters = chapters_for_journey($journeyId);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update_journey_details') {
        update_journey(
            $journeyId,
            (string)($_POST['name'] ?? ''),
            (string)($_POST['description'] ?? ''),
            (string)($_POST['difficulty'] ?? ''),
            !empty($_POST['is_active'])
        );
        redirect('/admin/journey_view.php?id=' . $journeyId);
    }
}
page_header('Manage Journey');
?>
<details class="card content-inline-edit-card" open>
    <summary>Journey Details</summary>
    <form method="post" class="enhanced-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_journey_details">

        <div class="form-grid">
            <label>Name
                <input name="name" value="<?= e($journey['name']) ?>" required>
            </label>

            <label>Difficulty
                <input name="difficulty" value="<?= e($journey['difficulty'] ?? '') ?>">
            </label>
        </div>

        <label>Description
            <textarea name="description" rows="4"><?= e($journey['description'] ?? '') ?></textarea>
        </label>

        <label class="toggle-row">
            <input type="checkbox" name="is_active" value="1" <?= !empty($journey['is_active']) ? 'checked' : '' ?>>
            <span><strong>Active</strong></span>
        </label>

        <div class="form-actions">
            <button class="button" type="submit">Save Journey</button>
        </div>
    </form>
</details>

<div class="page-title-row">
    <div>
        <h1><?= e($journey['icon'] ?: '🧭') ?> <?= e($journey['name']) ?></h1>
        <p class="muted"><?= nl2br(e($journey['description'] ?: 'No description yet.')) ?></p>
        <?php $journeyTags = journey_tags_for_journey((int)$journey['id']); ?>
        <?php if ($journeyTags): ?>
            <p class="journey-tags-row"><?php foreach ($journeyTags as $tag): ?><span class="badge"><?= e($tag['name']) ?></span><?php endforeach; ?></p>
        <?php endif; ?>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="/admin/journeys.php">All journeys</a>
        <?php if (current_user_can('journeys.manage')): ?>
            <a class="button secondary" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>&preview=1">Preview as player</a>
            <a class="button secondary" href="/admin/journey_edit.php?id=<?= (int)$journey['id'] ?>">Edit journey</a>
            <form class="inline-form" method="post" action="/admin/journey_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="duplicate_journey">
                <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                <button class="button secondary" type="submit">Duplicate journey</button>
            </form>
            <?php if (current_user_can('journeys.delete')): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this journey? This will remove its chapters, steps and player progress for this journey.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_journey">
                <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                <button class="button danger" type="submit">Delete journey</button>
            </form><?php endif; ?>
            <a class="button" href="/admin/chapter_edit.php?journey_id=<?= (int)$journey['id'] ?>">Add chapter</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($notice): ?><div class="notice journey-prereq-notice"><?= e($notice) ?></div><?php endif; ?>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="notice error"><?= e((string)$_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

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
                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="move_chapter">
                        <input type="hidden" name="direction" value="up">
                        <input type="hidden" name="chapter_id" value="<?= (int)$chapter['id'] ?>">
                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                        <button class="button secondary icon-button" type="submit" title="Move chapter up">↑</button>
                    </form>
                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="move_chapter">
                        <input type="hidden" name="direction" value="down">
                        <input type="hidden" name="chapter_id" value="<?= (int)$chapter['id'] ?>">
                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                        <button class="button secondary icon-button" type="submit" title="Move chapter down">↓</button>
                    </form>
                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="duplicate_chapter">
                        <input type="hidden" name="chapter_id" value="<?= (int)$chapter['id'] ?>">
                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                        <button class="button secondary" type="submit">Duplicate</button>
                    </form>
                    <?php if (current_user_can('journeys.delete')): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this chapter? This will remove all steps inside it.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_chapter">
                        <input type="hidden" name="chapter_id" value="<?= (int)$chapter['id'] ?>">
                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                        <button class="button danger" type="submit">Delete</button>
                    </form><?php endif; ?>
                    <a class="button secondary" href="/admin/chapter_edit.php?id=<?= (int)$chapter['id'] ?>">Edit chapter</a>
                    <a class="button" href="/admin/step_edit.php?chapter_id=<?= (int)$chapter['id'] ?>">Add step</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$steps): ?>
            <p class="muted">No steps in this chapter yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Step</th><th>Completion</th><th>Rule</th><th>Content</th><th>Logic</th><th>Sort</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($steps as $step): ?>
                    <tr>
                        <td><strong><?= e($step['title']) ?></strong><br><span class="muted small"><?= e($step['description'] ?: '') ?></span></td>
                        <td><?= e(completion_mode_label((string)$step['completion_mode'])) ?></td>
                        <td><?= e(rule_summary($step)) ?></td>
                        <td>
                            <?php if (!empty($step['content_name'])): ?>
                                <span class="badge"><?= e(content_types()[$step['content_type']] ?? $step['content_type']) ?></span><br>
                                <span class="muted small"><?= e($step['content_name']) ?></span>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($step['is_optional'])): ?><span class="badge">Optional</span><?php endif; ?>
                            <?php if (!empty($step['requires_step_id'])): ?><span class="badge">Locked by prerequisite</span><?php endif; ?>
                            <?php if (empty($step['is_optional']) && empty($step['requires_step_id'])): ?><span class="muted small">Standard</span><?php endif; ?>
                        </td>
                        <td><?= (int)$step['sort_order'] ?></td>
                        <td>
                            <?php if (current_user_can('journeys.manage')): ?>
                                <div class="admin-step-actions">
                                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_step">
                                        <input type="hidden" name="direction" value="up">
                                        <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                                        <button class="button secondary icon-button" type="submit" title="Move step up">↑</button>
                                    </form>
                                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="move_step">
                                        <input type="hidden" name="direction" value="down">
                                        <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                                        <button class="button secondary icon-button" type="submit" title="Move step down">↓</button>
                                    </form>
                                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="duplicate_step">
                                        <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                                        <button class="button secondary" type="submit">Copy</button>
                                    </form>
                                    <?php if (!empty($step['content_item_id'])): ?>
                                        <form class="inline-form" method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="expand_content_prereqs">
                                            <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                                            <button class="button secondary" type="submit">Add prereqs</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (current_user_can('journeys.delete')): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this step?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_step">
                                        <input type="hidden" name="step_id" value="<?= (int)$step['id'] ?>">
                                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                                        <button class="button danger" type="submit">Delete</button>
                                    </form><?php endif; ?>
                                    <a class="button secondary" href="/admin/step_edit.php?id=<?= (int)$step['id'] ?>">Edit</a>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php page_footer(); ?>
