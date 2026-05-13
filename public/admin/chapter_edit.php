<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.manage');

$id = (int)($_GET['id'] ?? 0);
$chapter = $id ? chapter_by_id($id) : null;
$journeyId = $chapter ? (int)$chapter['journey_id'] : (int)($_GET['journey_id'] ?? 0);
$journey = journey_by_id($journeyId);
if (!$journey || ($id && !$chapter)) {
    abort_page(404, 'Chapter or journey not found.');
}
if (!journey_can_edit($journey)) {
    abort_page(403, 'You do not have permission to edit this journey.');
}
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        if ($id) {
            update_chapter($id, (string)($_POST['title'] ?? ''), (string)($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        } else {
            create_chapter($journeyId, (string)($_POST['title'] ?? ''), (string)($_POST['description'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        }
        redirect('/admin/journey_view.php?id=' . $journeyId);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header($id ? 'Edit Chapter' : 'Create Chapter');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Chapter' : 'Create Chapter' ?></h1>
        <p class="muted">Journey: <?= e($journey['name']) ?></p>
    </div>
    <a class="button secondary" href="/admin/journey_view.php?id=<?= (int)$journeyId ?>">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Chapter details</h2>
            <p class="muted">Chapters group related steps together, such as account setup, quests, unlocks or early bosses.</p>
        </div>

        <label>Chapter title
            <input name="title" value="<?= e($_POST['title'] ?? $chapter['title'] ?? '') ?>" required placeholder="Core Account Setup">
            <span class="field-help">Use a short, scannable title.</span>
        </label>

        <label>Description
            <textarea name="description" rows="5" placeholder="Unlock the baseline systems this journey depends on."><?= e($_POST['description'] ?? $chapter['description'] ?? '') ?></textarea>
            <span class="field-help">Optional, but useful for explaining why this chapter matters.</span>
        </label>

        <label>Sort order
            <input type="number" name="sort_order" value="<?= e($_POST['sort_order'] ?? $chapter['sort_order'] ?? 0) ?>">
            <span class="field-help">Lower numbers appear first within the journey.</span>
        </label>
    </section>

    <div class="sticky-form-actions">
        <a class="button secondary" href="/admin/journey_view.php?id=<?= (int)$journeyId ?>">Cancel</a>
        <button class="button" type="submit">Save chapter</button>
    </div>
</form>
<?php page_footer(); ?>
