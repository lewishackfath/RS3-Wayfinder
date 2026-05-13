<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.manage');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        update_content_type_config((string)($_POST['type_slug'] ?? ''), $_POST);
        redirect('/admin/content_types.php?saved=1&type=' . urlencode((string)($_POST['type_slug'] ?? '')));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$configs = content_type_configs();
$openType = (string)($_GET['type'] ?? '');
page_header('Content Type Settings');
?>
<div class="page-title-row">
    <div>
        <h1>Content Type Settings</h1>
        <p class="muted">Configure which requirement panels and extra metadata fields appear for each content type.</p>
    </div>
    <a class="button secondary" href="/admin/content.php">Content library</a>
</div>

<?php if (!empty($_GET['saved'])): ?><div class="notice success">Content type settings saved.</div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="content-type-accordion">
<?php foreach (content_types() as $type => $fallbackLabel): ?>
    <?php
        $config = $configs[$type] ?? content_type_config($type);
        $fields = $config['custom_fields'] ?? [];
        $isOpen = $openType === $type || ($openType === '' && $type === array_key_first(content_types()));
    ?>
    <details class="card content-type-panel" <?= $isOpen ? 'open' : '' ?>>
        <summary class="content-type-summary">
            <span>
                <strong><?= e($config['label'] ?? $fallbackLabel) ?></strong>
                <small>Internal type: <code><?= e($type) ?></code></small>
            </span>
            <span class="badge <?= !empty($config['is_enabled']) ? 'success' : 'muted-badge' ?>">
                <?= !empty($config['is_enabled']) ? 'Enabled' : 'Disabled' ?>
            </span>
        </summary>

        <form class="enhanced-form content-type-form" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="type_slug" value="<?= e($type) ?>">

            <div class="form-grid">
                <label>Display label
                    <input name="label" value="<?= e($config['label'] ?? $fallbackLabel) ?>">
                </label>
                <label>Sort order
                    <input type="number" name="sort_order" value="<?= (int)($config['sort_order'] ?? 0) ?>">
                </label>
            </div>

            <label>Description
                <textarea name="description" rows="2"><?= e($config['description'] ?? '') ?></textarea>
            </label>

            <div class="settings-section">
                <h3>Requirement panels</h3>
                <p class="muted small">Only enabled requirement panels will appear when editing this content type.</p>
                <div class="form-grid requirements-grid">
                    <label class="toggle-row"><input type="checkbox" name="is_enabled" value="1" <?= !empty($config['is_enabled']) ? 'checked' : '' ?>><span><strong>Enabled</strong><small>Show this type in content forms.</small></span></label>
                    <label class="toggle-row"><input type="checkbox" name="allow_skill_requirements" value="1" <?= !empty($config['allow_skill_requirements']) ? 'checked' : '' ?>><span><strong>Skill requirements</strong><small>Show skill requirement panel.</small></span></label>
                    <label class="toggle-row"><input type="checkbox" name="allow_quest_requirements" value="1" <?= !empty($config['allow_quest_requirements']) ? 'checked' : '' ?>><span><strong>Quest requirements</strong><small>Show quest requirement panel.</small></span></label>
                    <label class="toggle-row"><input type="checkbox" name="allow_achievement_requirements" value="1" <?= !empty($config['allow_achievement_requirements']) ? 'checked' : '' ?>><span><strong>Achievement requirements</strong><small>Show achievement requirement panel.</small></span></label>
                    <label class="toggle-row"><input type="checkbox" name="allow_boss_drop_links" value="1" <?= !empty($config['allow_boss_drop_links']) ? 'checked' : '' ?>><span><strong>Boss item links</strong><small>Show item source management for bosses.</small></span></label>
                </div>
            </div>

            <div class="settings-section custom-fields-section" data-custom-fields>
                <div class="section-title-row">
                    <div>
                        <h3>Custom fields</h3>
                        <p class="muted small">Add metadata fields that appear only for this content type.</p>
                    </div>
                    <button class="button secondary small-button" type="button" data-add-custom-field>Add field</button>
                </div>

                <div class="custom-fields-table-wrap">
                    <table class="admin-table custom-fields-table">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Placeholder</th>
                                <th class="action-col">Remove</th>
                            </tr>
                        </thead>
                        <tbody data-custom-field-rows>
                            <?php if (!$fields): ?>
                                <tr class="empty-row" data-empty-custom-field-row>
                                    <td colspan="5" class="muted">No custom fields configured for this content type yet.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td><input name="custom_field_key[]" value="<?= e($field['key'] ?? '') ?>" placeholder="category"></td>
                                    <td><input name="custom_field_label[]" value="<?= e($field['label'] ?? '') ?>" placeholder="Category"></td>
                                    <td>
                                        <select name="custom_field_type[]">
                                            <?php foreach (['text' => 'Text', 'textarea' => 'Textarea', 'url' => 'URL', 'number' => 'Number'] as $fieldType => $fieldLabel): ?>
                                                <option value="<?= e($fieldType) ?>" <?= (($field['type'] ?? 'text') === $fieldType) ? 'selected' : '' ?>><?= e($fieldLabel) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input name="custom_field_placeholder[]" value="<?= e($field['placeholder'] ?? '') ?>" placeholder="Optional helper text"></td>
                                    <td class="action-col"><button class="button danger ghost small-button" type="button" data-remove-custom-field>Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions">
                <button class="button" type="submit">Save <?= e($config['label'] ?? $fallbackLabel) ?></button>
            </div>
        </form>
    </details>
<?php endforeach; ?>
</div>

<template id="custom-field-row-template">
    <tr>
        <td><input name="custom_field_key[]" placeholder="category"></td>
        <td><input name="custom_field_label[]" placeholder="Category"></td>
        <td>
            <select name="custom_field_type[]">
                <option value="text">Text</option>
                <option value="textarea">Textarea</option>
                <option value="url">URL</option>
                <option value="number">Number</option>
            </select>
        </td>
        <td><input name="custom_field_placeholder[]" placeholder="Optional helper text"></td>
        <td class="action-col"><button class="button danger ghost small-button" type="button" data-remove-custom-field>Remove</button></td>
    </tr>
</template>

<script>
document.addEventListener('click', function (event) {
    const addButton = event.target.closest('[data-add-custom-field]');
    if (addButton) {
        const section = addButton.closest('[data-custom-fields]');
        const tbody = section.querySelector('[data-custom-field-rows]');
        const template = document.getElementById('custom-field-row-template');
        const emptyRow = tbody.querySelector('[data-empty-custom-field-row]');
        if (emptyRow) emptyRow.remove();
        tbody.appendChild(template.content.cloneNode(true));
        return;
    }

    const removeButton = event.target.closest('[data-remove-custom-field]');
    if (removeButton) {
        const tbody = removeButton.closest('tbody');
        removeButton.closest('tr').remove();
        if (!tbody.querySelector('tr')) {
            const emptyRow = document.createElement('tr');
            emptyRow.className = 'empty-row';
            emptyRow.setAttribute('data-empty-custom-field-row', '');
            emptyRow.innerHTML = '<td colspan="5" class="muted">No custom fields configured for this content type yet.</td>';
            tbody.appendChild(emptyRow);
        }
    }
});
</script>
<?php page_footer(); ?>
