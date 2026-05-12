<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.manage');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $id ? content_item_by_id($id) : null;
if ($id && !$item) abort_page(404, 'Content item not found.');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        if (isset($_POST['delete_content'])) {
            delete_content_item($id);
            redirect('/admin/content.php');
        }

        if ($id) {
            update_content_item(
                $id,
                (string)($_POST['type'] ?? ''),
                (string)($_POST['name'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['category'] ?? ''),
                (string)($_POST['source_url'] ?? ''),
                (string)($_POST['icon_url'] ?? ''),
                !empty($_POST['is_active'])
            );
        } else {
            $id = create_content_item(
                (string)($_POST['type'] ?? ''),
                (string)($_POST['name'] ?? ''),
                (string)($_POST['description'] ?? ''),
                (string)($_POST['category'] ?? ''),
                (string)($_POST['source_url'] ?? ''),
                (string)($_POST['icon_url'] ?? ''),
                !empty($_POST['is_active'])
            );
        }
        redirect('/admin/content_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $item = array_merge($item ?: [], $_POST);
    }
}

$typeValue = (string)($item['type'] ?? $_GET['type'] ?? 'quest');
page_header($id ? 'Edit Content' : 'Add Content');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Content' : 'Add Content' ?></h1>
        <p class="muted">Create reusable content records that journeys and recommendations can reference.</p>
    </div>
    <a class="button secondary" href="<?= $id ? '/admin/content_view.php?id=' . (int)$id : '/admin/content.php' ?>">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card enhanced-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Content details</h2>
            <p class="muted">This is the canonical record for the quest, boss, achievement, drop or unlock.</p>
        </div>

        <div class="form-grid">
            <label>Type
                <select name="type">
                    <?php foreach (content_types() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $typeValue === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Name
                <input name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="Temple at Senntisten">
            </label>
        </div>

        <label>Description
            <textarea name="description" rows="5"><?= e($item['description'] ?? '') ?></textarea>
        </label>

        <div class="form-grid">
            <label>Category
                <input name="category" value="<?= e($item['category'] ?? '') ?>" placeholder="Prifddinas / Zamorak / Area Tasks">
            </label>
            <label>Source URL
                <input name="source_url" value="<?= e($item['source_url'] ?? '') ?>" placeholder="RuneScape Wiki URL">
            </label>
        </div>

        <label>Icon URL
            <input name="icon_url" value="<?= e($item['icon_url'] ?? '') ?>" placeholder="Optional icon URL">
        </label>

        <label class="toggle-row">
            <input type="checkbox" name="is_active" value="1" <?= ((int)($item['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><strong>Active</strong><small>Inactive content is hidden from selectors later.</small></span>
        </label>
    </section>

    <div class="sticky-form-actions">
        <?php if ($id): ?>
            <button class="button danger" type="submit" name="delete_content" value="1" onclick="return confirm('Delete this content item and all related requirements/drop sources?');">Delete</button>
        <?php endif; ?>
        <a class="button secondary" href="<?= $id ? '/admin/content_view.php?id=' . (int)$id : '/admin/content.php' ?>">Cancel</a>
        <button class="button" type="submit">Save content</button>
    </div>
</form>
<?php page_footer(); ?>
