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
            require_permission('content.delete');
            $existingItem = content_item_by_id($id);
            if (($existingItem['type'] ?? '') === 'quest') {
                throw new RuntimeException('Quest content cannot be manually deleted.');
            }
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

        if ((string)($_POST['type'] ?? $typeValue ?? '') === 'quest') {
            $savedItem = content_item_by_id($id);
            $existingMetadata = $savedItem ? content_metadata($savedItem) : [];
            update_content_metadata($id, quest_metadata_from_post($_POST, $existingMetadata));
        }

        redirect('/admin/content_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $item = array_merge($item ?: [], $_POST);
    }
}

$typeValue = (string)($item['type'] ?? $_GET['type'] ?? 'quest');
$metadata = $item ? content_metadata($item) : [];
page_header($id ? 'Edit Content' : 'Add Content');
?>
<div class="page-title-row">
    <div>
        <h1><?= $id ? 'Edit Content' : 'Add Content' ?></h1>
        <p class="muted">Create reusable records for quests, achievements, bosses, items, unlocks and other progression items.</p>
    </div>
    <a class="button secondary" href="<?= $id ? '/admin/content_view.php?id=' . (int)$id : '/admin/content.php' ?>">Back</a>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="card form-card enhanced-form content-editor-form" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <section class="form-section">
        <div class="form-section-intro">
            <h2>What is this?</h2>
            <p class="muted">Choose the content type and give it a clear name players will recognise.</p>
        </div>

        <div class="form-grid">
            <label>Content type
                <select name="type" id="content-type-select">
                    <?php foreach (content_types() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $typeValue === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field-help">This controls where the item appears and what extra options are shown.</span>
            </label>

            <label>Name
                <input name="name" required value="<?= e($item['name'] ?? '') ?>" placeholder="Temple at Senntisten">
                <span class="field-help">Use the official in-game name where possible.</span>
            </label>
        </div>

        <label>Description
            <textarea name="description" rows="5" placeholder="What is this item and why does it matter for account progression?"><?= e($item['description'] ?? '') ?></textarea>
        </label>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Organisation</h2>
            <p class="muted">These fields help admins browse and enrich the library. They can be adjusted later.</p>
        </div>

        <div class="form-grid">
            <label>Category
                <input name="category" value="<?= e($item['category'] ?? '') ?>" placeholder="Area Tasks / Mahjarrat / Zamorak">
                <span class="field-help">Optional grouping. Quest difficulty is stored separately and should not be used here.</span>
            </label>
            <label>Source / Wiki URL
                <input name="source_url" value="<?= e($item['source_url'] ?? '') ?>" placeholder="https://runescape.wiki/...">
            </label>
        </div>

        <label>Icon URL
            <input name="icon_url" value="<?= e($item['icon_url'] ?? '') ?>" placeholder="Optional icon URL">
        </label>
    </section>

    <section class="form-section quest-extra-fields" data-quest-fields>
        <div class="form-section-intro">
            <h2>Quest details</h2>
            <p class="muted">Optional admin-managed quest metadata. RuneMetrics import will not overwrite these values.</p>
        </div>

        <div class="form-grid">
            <label>Timeline
                <input name="quest_timeline" value="<?= e($_POST['quest_timeline'] ?? $metadata['quest_timeline'] ?? '') ?>" placeholder="Fifth Age / Sixth Age / Fort Forinthry">
            </label>
            <label>Series
                <input name="quest_series" value="<?= e($_POST['quest_series'] ?? $metadata['quest_series'] ?? '') ?>" placeholder="Mahjarrat / Elf / Pirate">
            </label>
        </div>

        <?php if (!empty($metadata['difficulty_label']) || isset($metadata['quest_points']) || array_key_exists('members', $metadata)): ?>
            <div class="imported-meta-note">
                <strong>Imported quest data</strong>
                <span>
                    <?= e($metadata['difficulty_label'] ?? 'Unknown difficulty') ?>
                    <?php if (isset($metadata['quest_points'])): ?> • <?= e((string)$metadata['quest_points']) ?> QP<?php endif; ?>
                    <?php if (array_key_exists('members', $metadata)): ?> • <?= !empty($metadata['members']) ? 'Members' : 'Free-to-play' ?><?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </section>

    <section class="form-section">
        <div class="form-section-intro">
            <h2>Availability</h2>
            <p class="muted">Inactive content stays in the database but can be hidden from future selectors.</p>
        </div>

        <label class="toggle-row">
            <input type="checkbox" name="is_active" value="1" <?= ((int)($item['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
            <span><strong>Active</strong><small>Keep this enabled for normal content records.</small></span>
        </label>
    </section>

    <div class="sticky-form-actions">
        <?php if ($id && current_user_can('content.delete') && ($item['type'] ?? '') !== 'quest'): ?>
            <button class="button danger" type="submit" name="delete_content" value="1" onclick="return confirm('Delete this content item and all related requirements/item sources?');">Delete</button>
        <?php endif; ?>
        <a class="button secondary" href="<?= $id ? '/admin/content_view.php?id=' . (int)$id : '/admin/content.php' ?>">Cancel</a>
        <button class="button" type="submit">Save content</button>
    </div>
</form>

<script>
(function () {
    const typeSelect = document.getElementById('content-type-select');
    const questFields = document.querySelector('[data-quest-fields]');
    function updateQuestFields() {
        if (!typeSelect || !questFields) return;
        questFields.hidden = typeSelect.value !== 'quest';
    }
    if (typeSelect) typeSelect.addEventListener('change', updateQuestFields);
    updateQuestFields();
})();
</script>
<?php page_footer(); ?>
