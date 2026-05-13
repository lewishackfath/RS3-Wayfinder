<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.manage');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    try {
        update_content_type_config((string)($_POST['type_slug'] ?? ''), $_POST);
        redirect('/admin/content_types.php?saved=1');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$configs = content_type_configs();
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

<div class="grid two-col-grid admin-dashboard-grid">
<?php foreach (content_types() as $type => $fallbackLabel): ?>
    <?php
        $config = $configs[$type] ?? content_type_config($type);
        $fields = $config['custom_fields'] ?? [];
        $fieldText = implode("\n", array_map(static function (array $field): string {
            return ($field['key'] ?? '') . ' | ' . ($field['label'] ?? '') . ' | ' . ($field['type'] ?? 'text') . ' | ' . ($field['placeholder'] ?? '');
        }, $fields));
    ?>
    <form class="card enhanced-form" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="type_slug" value="<?= e($type) ?>">
        <h2><?= e($config['label'] ?? $fallbackLabel) ?></h2>
        <p class="muted small">Internal type: <code><?= e($type) ?></code></p>

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

        <div class="form-grid">
            <label class="toggle-row"><input type="checkbox" name="is_enabled" value="1" <?= !empty($config['is_enabled']) ? 'checked' : '' ?>><span><strong>Enabled</strong><small>Show this type in content forms.</small></span></label>
            <label class="toggle-row"><input type="checkbox" name="allow_skill_requirements" value="1" <?= !empty($config['allow_skill_requirements']) ? 'checked' : '' ?>><span><strong>Skill requirements</strong><small>Show skill requirement panel.</small></span></label>
            <label class="toggle-row"><input type="checkbox" name="allow_quest_requirements" value="1" <?= !empty($config['allow_quest_requirements']) ? 'checked' : '' ?>><span><strong>Quest requirements</strong><small>Show quest requirement panel.</small></span></label>
            <label class="toggle-row"><input type="checkbox" name="allow_achievement_requirements" value="1" <?= !empty($config['allow_achievement_requirements']) ? 'checked' : '' ?>><span><strong>Achievement requirements</strong><small>Show achievement requirement panel.</small></span></label>
            <label class="toggle-row"><input type="checkbox" name="allow_boss_drop_links" value="1" <?= !empty($config['allow_boss_drop_links']) ? 'checked' : '' ?>><span><strong>Boss drop links</strong><small>Show drop source management for bosses.</small></span></label>
        </div>

        <label>Custom fields
            <textarea name="custom_fields_text" rows="5" placeholder="key | Label | text | Placeholder"><?= e($fieldText) ?></textarea>
            <span class="field-help">One field per line. Format: <code>key | Label | type | Placeholder</code>. Types: text, textarea, url, number.</span>
        </label>

        <div class="form-actions">
            <button class="button secondary" type="submit">Save <?= e($config['label'] ?? $fallbackLabel) ?></button>
        </div>
    </form>
<?php endforeach; ?>
</div>
<?php page_footer(); ?>
