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
        $name = (string)($_POST['name'] ?? '');
        $slug = $id ? (string)($journey['slug'] ?? '') : '';
        if ($id) {
            update_journey(
                $id,
                $name,
                $slug,
                (string)($_POST['description'] ?? ''),
                (string)($_POST['icon'] ?? ''),
                !empty($_POST['is_published']),
                (int)($_POST['sort_order'] ?? 0)
            );
        } else {
            $id = create_journey(
                $name,
                '',
                (string)($_POST['description'] ?? ''),
                (string)($_POST['icon'] ?? ''),
                !empty($_POST['is_published']),
                (int)($_POST['sort_order'] ?? 0)
            );
        }
        set_journey_tags($id, is_array($_POST['tag_ids'] ?? null) ? $_POST['tag_ids'] : []);
        redirect('/admin/journey_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

page_header($id ? 'Edit Journey' : 'Create Journey');
$nameValue = (string)($_POST['name'] ?? $journey['name'] ?? '');
$descriptionValue = (string)($_POST['description'] ?? $journey['description'] ?? '');
$iconValue = (string)($_POST['icon'] ?? $journey['icon'] ?? '🧭');
$sortValue = (int)($_POST['sort_order'] ?? $journey['sort_order'] ?? 0);
$isPublished = !empty($_POST['is_published']) || (!$_POST && !empty($journey['is_published']));
$allTags = all_journey_tags();
$selectedTagIds = $_POST && isset($_POST['tag_ids']) && is_array($_POST['tag_ids'])
    ? array_map('intval', $_POST['tag_ids'])
    : ($id ? journey_tag_ids_for_journey($id) : []);
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Journey' : 'Create Journey' ?></h1>
        <p class="muted">Create the main path players will follow. Technical details like the URL slug are handled automatically.</p>
    </div>
    <a class="button secondary" href="<?= $id ? '/admin/journey_view.php?id=' . (int)$id : '/admin/journeys.php' ?>">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Journey basics</h2>
            <p class="muted">Give the journey a clear name and a short explanation of who it is for.</p>
        </div>

        <label>Journey name
            <input name="name" value="<?= e($nameValue) ?>" required placeholder="PvM Progression">
            <span class="field-help">This is what players will see in the journey list.</span>
        </label>

        <label>Description
            <textarea name="description" rows="5" placeholder="A guided path for learning RS3 combat systems, unlocks and bosses."><?= e($descriptionValue) ?></textarea>
            <span class="field-help">Keep this player-facing. You can explain goals, intended account stage and playstyle.</span>
        </label>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Journey type / tags</h2>
            <p class="muted">Tags help Wayfinder recommend this journey to players with matching interests.</p>
        </div>

        <div class="choice-grid tag-choice-grid">
            <?php foreach ($allTags as $tag): ?>
                <label class="choice-card tag-choice-card">
                    <input type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $selectedTagIds, true) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= e($tag['name']) ?></strong>
                        <small><?= e($tag['description'] ?: $tag['slug']) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Display options</h2>
            <p class="muted">Small presentation settings for the journey cards.</p>
        </div>

        <div class="form-grid">
            <label>Icon / Emoji
                <input name="icon" value="<?= e($iconValue) ?>" placeholder="🧭">
                <span class="field-help">Used beside the journey name. Emoji work well here.</span>
            </label>

            <label>Sort order
                <input type="number" name="sort_order" value="<?= e((string)$sortValue) ?>">
                <span class="field-help">Lower numbers appear first.</span>
            </label>
        </div>

        <label class="toggle-row">
            <input type="checkbox" name="is_published" value="1" <?= $isPublished ? 'checked' : '' ?>>
            <span>
                <strong>Published</strong>
                <small>Published journeys are visible to players. Leave this off while drafting.</small>
            </span>
        </label>

        <?php if ($id && !empty($journey['slug'])): ?>
            <p class="muted small">Generated URL slug: <code><?= e($journey['slug']) ?></code></p>
        <?php endif; ?>
    </section>

    <div class="sticky-form-actions">
        <a class="button secondary" href="<?= $id ? '/admin/journey_view.php?id=' . (int)$id : '/admin/journeys.php' ?>">Cancel</a>
        <button class="button" type="submit">Save journey</button>
    </div>
</form>
<?php page_footer(); ?>
