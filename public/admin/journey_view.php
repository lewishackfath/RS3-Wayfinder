<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.view');

$journeyId = (int)($_GET['id'] ?? 0);
$journey = journey_by_id($journeyId);
if (!$journey) {
    abort_page(404, 'Journey not found.');
}
$canEditJourney = journey_can_edit($journey);
$canDeleteJourney = journey_can_delete_item($journey);
$notice = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        if (!$canEditJourney) {
            throw new RuntimeException('You do not have permission to edit this journey.');
        }

        if ($action === 'save_journey_details') {
            update_journey(
                $journeyId,
                (string)($_POST['name'] ?? ''),
                (string)($journey['slug'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['icon'] ?? ''),
                !empty($_POST['is_published']),
                (int)($_POST['sort_order'] ?? 0)
            );
            set_journey_tags($journeyId, is_array($_POST['tag_ids'] ?? null) ? $_POST['tag_ids'] : []);
            redirect('/admin/journey_view.php?id=' . $journeyId);
        }

        if ($action === 'expand_content_prereqs') {
            $step = step_by_id((int)($_POST['step_id'] ?? 0));
            if (!$step || empty($step['content_item_id'])) {
                throw new InvalidArgumentException('This step is not linked to a content item.');
            }
            $created = add_content_prerequisite_steps_to_chapter((int)$step['chapter_id'], (int)$step['content_item_id'], (int)$step['id']);
            $notice = count($created) . ' prerequisite step' . (count($created) === 1 ? '' : 's') . ' added.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$journey = journey_by_id($journeyId) ?: $journey;
$chapters = chapters_for_journey($journeyId);
$allTags = all_journey_tags();
$selectedTagIds = journey_tag_ids_for_journey($journeyId);

page_header('Manage Journey');
?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="notice journey-prereq-notice"><?= e($notice) ?></div><?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="notice error"><?= e((string)$_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<details class="card content-inline-edit-card" <?= $canEditJourney ? 'open' : '' ?>>
    <summary>Journey details</summary>
    <?php if (!$canEditJourney): ?>
        <p class="muted">You can view this journey, but your current role cannot edit it.</p>
    <?php else: ?>
        <form method="post" class="enhanced-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_journey_details">

            <div class="form-grid">
                <label>Journey name
                    <input name="name" value="<?= e((string)$journey['name']) ?>" required>
                </label>
                <label>Icon / Emoji
                    <input name="icon" value="<?= e((string)($journey['icon'] ?: '🧭')) ?>" placeholder="🧭">
                </label>
                <label>Sort order
                    <input type="number" name="sort_order" value="<?= e((string)($journey['sort_order'] ?? 0)) ?>">
                </label>
            </div>

            <label>Description
                <textarea name="description" rows="4"><?= e((string)($journey['description'] ?? '')) ?></textarea>
            </label>

            <?php if ($allTags): ?>
                <div class="choice-grid tag-choice-grid">
                    <?php foreach ($allTags as $tag): ?>
                        <label class="choice-card tag-choice-card">
                            <input type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
                            <span><strong><?= e($tag['name']) ?></strong><small><?= e($tag['description'] ?: $tag['slug']) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <label class="toggle-row">
                <input type="checkbox" name="is_published" value="1" <?= !empty($journey['is_published']) ? 'checked' : '' ?>>
                <span><strong>Published</strong><small>Published journeys are visible to players.</small></span>
            </label>

            <p class="muted small">Slug: <code><?= e((string)$journey['slug']) ?></code> · Created by: <?= e(journey_creator_label($journey)) ?></p>

            <div class="form-actions"><button class="button" type="submit">Save journey details</button></div>
        </form>
    <?php endif; ?>
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
        <?php if ($canEditJourney): ?>
            <a class="button secondary" href="/journeys/view.php?id=<?= (int)$journey['id'] ?>&preview=1">Preview as player</a>
            <form class="inline-form" method="post" action="/admin/journey_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="duplicate_journey">
                <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                <button class="button secondary" type="submit">Duplicate journey</button>
            </form>
            <?php if ($canDeleteJourney): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this journey? This will remove its chapters, steps and player progress for this journey.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_journey">
                <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                <button class="button danger" type="submit">Delete journey</button>
            </form><?php endif; ?>
            <a class="button" href="/admin/chapter_edit.php?journey_id=<?= (int)$journey['id'] ?>">Add chapter</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$chapters): ?>
    <div class="card"><p class="muted">No chapters yet. Add your first chapter to start building this journey.</p></div>
<?php endif; ?>

<?php if ($canEditJourney && $chapters): ?>
    <div class="notice drag-order-notice">
        <span>Drag chapters or steps by the grip handle, then save the order for that section.</span>
        <button class="button secondary drag-save-button" type="button" data-save-sort="chapters">Save chapter order</button>
    </div>
<?php endif; ?>

<div class="journey-chapter-list" data-sortable-list="chapters" data-journey-id="<?= (int)$journey['id'] ?>">
<?php foreach ($chapters as $chapter): ?>
    <?php $steps = steps_for_chapter((int)$chapter['id']); ?>
    <div class="card journey-chapter-card" data-sortable-item data-id="<?= (int)$chapter['id'] ?>" <?= $canEditJourney ? 'draggable="true"' : '' ?>>
        <div class="page-title-row compact">
            <div>
                <h2><?php if ($canEditJourney): ?><span class="drag-handle" title="Drag to reorder chapter" aria-label="Drag to reorder chapter">☰</span><?php endif; ?><?= e($chapter['title']) ?></h2>
                <p class="muted"><?= nl2br(e($chapter['description'] ?: 'No description.')) ?></p>
                <p class="muted small">Sort order: <?= (int)$chapter['sort_order'] ?></p>
            </div>
            <?php if ($canEditJourney): ?>
                <div class="form-actions">
                    <form class="inline-form" method="post" action="/admin/journey_action.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="duplicate_chapter">
                        <input type="hidden" name="chapter_id" value="<?= (int)$chapter['id'] ?>">
                        <input type="hidden" name="journey_id" value="<?= (int)$journey['id'] ?>">
                        <button class="button secondary" type="submit">Duplicate</button>
                    </form>
                    <?php if ($canDeleteJourney): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this chapter? This will remove all steps inside it.');">
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
            <?php if ($canEditJourney): ?>
                <div class="drag-section-actions"><button class="button secondary drag-save-button" type="button" data-save-sort="steps" data-chapter-id="<?= (int)$chapter['id'] ?>">Save step order</button></div>
            <?php endif; ?>
            <table class="table drag-steps-table">
                <thead><tr><th class="drag-column"></th><th>Step</th><th>Completion</th><th>Rule</th><th>Content</th><th>Logic</th><th></th></tr></thead>
                <tbody data-sortable-list="steps" data-chapter-id="<?= (int)$chapter['id'] ?>" data-journey-id="<?= (int)$journey['id'] ?>">
                <?php foreach ($steps as $step): ?>
                    <tr data-sortable-item data-id="<?= (int)$step['id'] ?>" <?= $canEditJourney ? 'draggable="true"' : '' ?>>
                        <td class="drag-column"><?php if ($canEditJourney): ?><span class="drag-handle" title="Drag to reorder step" aria-label="Drag to reorder step">☰</span><?php endif; ?></td>
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
                        <td>
                            <?php if ($canEditJourney): ?>
                                <div class="admin-step-actions">
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
                                    <?php if ($canDeleteJourney): ?><form class="inline-form" method="post" action="/admin/journey_action.php" onsubmit="return confirm('Delete this step?');">
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
</div>

<?php if ($canEditJourney): ?>
<script>
(function () {
    const csrfToken = <?= json_encode(csrf_token()) ?>;
    const actionUrl = '/admin/journey_action.php';
    let dragged = null;
    let dragIntent = false;

    document.addEventListener('pointerdown', function (event) {
        dragIntent = !!event.target.closest('.drag-handle');
    });

    document.addEventListener('pointerup', function () {
        dragIntent = false;
    });

    function closestList(el) {
        return el ? el.closest('[data-sortable-list]') : null;
    }

    document.addEventListener('dragstart', function (event) {
        const item = event.target.closest('[data-sortable-item]');
        if (!dragIntent || !item) {
            event.preventDefault();
            return;
        }
        dragged = item;
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.dataset.id || '');
    });

    document.addEventListener('dragend', function () {
        if (dragged) {
            dragged.classList.remove('is-dragging');
        }
        dragged = null;
        dragIntent = false;
        document.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
    });

    document.addEventListener('dragover', function (event) {
        if (!dragged) return;
        const target = event.target.closest('[data-sortable-item]');
        const list = closestList(target);
        if (!target || !list || closestList(dragged) !== list || target === dragged) return;
        event.preventDefault();
        const rect = target.getBoundingClientRect();
        const after = event.clientY > rect.top + rect.height / 2;
        target.classList.add('drag-over');
        if (after) {
            target.after(dragged);
        } else {
            target.before(dragged);
        }
    });

    document.addEventListener('dragleave', function (event) {
        const target = event.target.closest('[data-sortable-item]');
        if (target) target.classList.remove('drag-over');
    });

    async function saveOrder(button) {
        const type = button.dataset.saveSort;
        const chapterId = button.dataset.chapterId || '';
        const list = type === 'chapters'
            ? document.querySelector('[data-sortable-list="chapters"]')
            : document.querySelector('[data-sortable-list="steps"][data-chapter-id="' + chapterId + '"]');
        if (!list) return;

        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('journey_id', list.dataset.journeyId || <?= json_encode((string)(int)$journey['id']) ?>);
        formData.append('action', type === 'chapters' ? 'reorder_chapters' : 'reorder_steps');
        if (type === 'steps') {
            formData.append('chapter_id', chapterId);
        }

        list.querySelectorAll('[data-sortable-item]').forEach(function (item) {
            formData.append(type === 'chapters' ? 'chapter_ids[]' : 'step_ids[]', item.dataset.id);
        });

        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Saving...';
        try {
            const response = await fetch(actionUrl, { method: 'POST', body: formData, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Save failed.');
            button.textContent = 'Saved';
            setTimeout(() => { button.textContent = originalText; button.disabled = false; }, 900);
        } catch (error) {
            button.textContent = 'Save failed';
            setTimeout(() => { button.textContent = originalText; button.disabled = false; }, 1500);
        }
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-save-sort]');
        if (!button) return;
        saveOrder(button);
    });
})();
</script>
<?php endif; ?>
<?php page_footer(); ?>
