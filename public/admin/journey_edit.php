<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.manage');

$id = (int)($_GET['id'] ?? 0);
$journey = $id ? journey_by_id($id) : null;
if ($id && !$journey) {
    abort_page(404, 'Journey not found.');
}
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        if ($id) {
            update_journey(
                $id,
                (string)($_POST['name'] ?? ''),
                (string)($_POST['slug'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['icon'] ?? ''),
                !empty($_POST['is_published']),
                (int)($_POST['sort_order'] ?? 0)
            );
        } else {
            $id = create_journey(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['slug'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['icon'] ?? ''),
                !empty($_POST['is_published']),
                (int)($_POST['sort_order'] ?? 0)
            );
        }
        redirect('/admin/journey_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header($id ? 'Edit Journey' : 'Create Journey');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Journey' : 'Create Journey' ?></h1>
        <p class="muted">Journeys are the top-level progression paths players can start.</p>
    </div>
    <a class="button secondary" href="/admin/journeys.php">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card" method="post">
    <?= csrf_field() ?>
    <label>Name
        <input name="name" value="<?= e($_POST['name'] ?? $journey['name'] ?? '') ?>" required>
    </label>
    <label>Slug
        <input name="slug" value="<?= e($_POST['slug'] ?? $journey['slug'] ?? '') ?>" placeholder="pvm-progression">
    </label>
    <label>Icon / Emoji
        <input name="icon" value="<?= e($_POST['icon'] ?? $journey['icon'] ?? '🧭') ?>">
    </label>
    <label>Description
        <textarea name="description" rows="5"><?= e($_POST['description'] ?? $journey['description'] ?? '') ?></textarea>
    </label>
    <label>Sort order
        <input type="number" name="sort_order" value="<?= e($_POST['sort_order'] ?? $journey['sort_order'] ?? 0) ?>">
    </label>
    <label class="checkbox-row">
        <input type="checkbox" name="is_published" value="1" <?= !empty($_POST['is_published']) || (!$_POST && !empty($journey['is_published'])) ? 'checked' : '' ?>>
        Published
    </label>
    <div class="form-actions">
        <button class="button" type="submit">Save journey</button>
    </div>
</form>
<?php page_footer(); ?>
