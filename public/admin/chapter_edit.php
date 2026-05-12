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

<form class="card form-card" method="post">
    <?= csrf_field() ?>
    <label>Title
        <input name="title" value="<?= e($_POST['title'] ?? $chapter['title'] ?? '') ?>" required>
    </label>
    <label>Description
        <textarea name="description" rows="5"><?= e($_POST['description'] ?? $chapter['description'] ?? '') ?></textarea>
    </label>
    <label>Sort order
        <input type="number" name="sort_order" value="<?= e($_POST['sort_order'] ?? $chapter['sort_order'] ?? 0) ?>">
    </label>
    <div class="form-actions">
        <button class="button" type="submit">Save chapter</button>
    </div>
</form>
<?php page_footer(); ?>
