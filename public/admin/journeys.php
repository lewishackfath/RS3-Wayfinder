<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.view');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && current_user_can('journeys.manage')) {
    require_csrf();
    try {
        $id = create_journey(
            (string)($_POST['name'] ?? ''),
            '',
            (string)($_POST['description'] ?? ''),
            (string)($_POST['icon'] ?? '🧭'),
            !empty($_POST['is_published']),
            (int)($_POST['sort_order'] ?? 0)
        );
        set_journey_tags($id, is_array($_POST['tag_ids'] ?? null) ? $_POST['tag_ids'] : []);
        redirect('/admin/journey_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$journeys = all_journeys(false);
$q = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$tagFilter = (int)($_GET['tag_id'] ?? 0);
$editableOnly = !empty($_GET['editable']);
$allTags = all_journey_tags();

$journeys = array_values(array_filter($journeys, function (array $journey) use ($q, $status, $tagFilter, $editableOnly): bool {
    if ($q !== '') {
        $haystack = strtolower((string)$journey['name'] . ' ' . (string)$journey['slug'] . ' ' . (string)$journey['description'] . ' ' . journey_creator_label($journey));
        if (strpos($haystack, strtolower($q)) === false) {
            return false;
        }
    }
    if ($status === 'published' && (int)$journey['is_published'] !== 1) {
        return false;
    }
    if ($status === 'draft' && (int)$journey['is_published'] === 1) {
        return false;
    }
    if ($editableOnly && !journey_can_edit($journey)) {
        return false;
    }
    if ($tagFilter > 0 && !in_array($tagFilter, journey_tag_ids_for_journey((int)$journey['id']), true)) {
        return false;
    }
    return true;
}));

page_header('Manage Journeys');
?>
<div class="page-title-row">
    <div>
        <h1>Journeys</h1>
        <p class="muted">A single admin workspace for creating, finding and maintaining player progression journeys.</p>
    </div>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?php if (current_user_can('journeys.manage')): ?>
<details class="card content-inline-edit-card" <?= $error ? 'open' : '' ?>>
    <summary>Create new journey</summary>
    <form method="post" class="enhanced-form">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label>Journey name
                <input name="name" value="<?= e((string)($_POST['name'] ?? '')) ?>" required placeholder="PvM Progression">
            </label>
            <label>Icon / Emoji
                <input name="icon" value="<?= e((string)($_POST['icon'] ?? '🧭')) ?>" placeholder="🧭">
            </label>
            <label>Sort order
                <input type="number" name="sort_order" value="<?= e((string)($_POST['sort_order'] ?? '0')) ?>">
            </label>
        </div>
        <label>Description
            <textarea name="description" rows="3" placeholder="Describe who this journey is for and what it helps players achieve."><?= e((string)($_POST['description'] ?? '')) ?></textarea>
        </label>
        <?php if ($allTags): ?>
            <div class="choice-grid tag-choice-grid">
                <?php foreach ($allTags as $tag): ?>
                    <label class="choice-card tag-choice-card">
                        <input type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>">
                        <span><strong><?= e($tag['name']) ?></strong><small><?= e($tag['description'] ?: $tag['slug']) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <label class="toggle-row"><input type="checkbox" name="is_published" value="1"><span><strong>Published</strong><small>Visible to players immediately.</small></span></label>
        <div class="form-actions"><button class="button" type="submit">Create and manage journey</button></div>
    </form>
</details>
<?php endif; ?>

<form class="card filters-card enhanced-form" method="get">
    <div class="form-grid">
        <label>Search
            <input name="q" value="<?= e($q) ?>" placeholder="Search name, slug, description or creator">
        </label>
        <label>Status
            <select name="status">
                <option value="" <?= $status === '' ? 'selected' : '' ?>>Any status</option>
                <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </label>
        <label>Tag
            <select name="tag_id">
                <option value="0">Any tag</option>
                <?php foreach ($allTags as $tag): ?><option value="<?= (int)$tag['id'] ?>" <?= $tagFilter === (int)$tag['id'] ? 'selected' : '' ?>><?= e($tag['name']) ?></option><?php endforeach; ?>
            </select>
        </label>
    </div>
    <label class="toggle-row"><input type="checkbox" name="editable" value="1" <?= $editableOnly ? 'checked' : '' ?>><span><strong>Only show journeys I can edit</strong></span></label>
    <div class="form-actions"><button class="button" type="submit">Apply filters</button><a class="button secondary" href="/admin/journeys.php">Reset</a></div>
</form>

<div class="card">
    <div class="page-title-row compact"><h2>Journey library</h2><span class="badge"><?= count($journeys) ?> shown</span></div>
    <?php if (!$journeys): ?>
        <p class="muted">No journeys match those filters.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Journey</th><th>Status</th><th>Creator</th><th>Sort</th><th>Permission</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($journeys as $journey): ?>
                <?php $canEdit = journey_can_edit($journey); ?>
                <tr>
                    <td>
                        <strong><?= e($journey['icon'] ?: '🧭') ?> <?= e($journey['name']) ?></strong><br>
                        <span class="muted small"><?= e($journey['slug']) ?></span><br>
                        <?php foreach (journey_tags_for_journey((int)$journey['id']) as $tag): ?><span class="badge"><?= e($tag['name']) ?></span><?php endforeach; ?>
                    </td>
                    <td><?= ((int)$journey['is_published'] === 1) ? '<span class="badge success">Published</span>' : '<span class="badge">Draft</span>' ?></td>
                    <td><?= e(journey_creator_label($journey)) ?></td>
                    <td><?= (int)$journey['sort_order'] ?></td>
                    <td><?= $canEdit ? '<span class="badge success">Editable</span>' : '<span class="badge">View only</span>' ?></td>
                    <td class="actions"><a class="button <?= $canEdit ? '' : 'secondary' ?>" href="/admin/journey_view.php?id=<?= (int)$journey['id'] ?>"><?= $canEdit ? 'Manage' : 'View' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
