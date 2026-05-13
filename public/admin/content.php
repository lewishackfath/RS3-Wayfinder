<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.view');

$type = (string)($_GET['type'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$filters = ['type' => $type, 'q' => $q, 'is_active' => ''];
$items = content_items($filters);
$counts = content_library_counts();

page_header('Content Library');
?>
<div class="page-title-row">
    <div>
        <h1>Content Library</h1>
        <p class="muted">Reusable RuneScape knowledge records for quests, achievements, bosses, unlocks and items.</p>
    </div>
    <?php if (current_user_can('content.manage')): ?>
        <a class="button secondary" href="/admin/content_import_quests.php">Import quests</a>
        <a class="button" href="/admin/content_edit.php">Add content</a>
    <?php endif; ?>
</div>

<div class="admin-summary-grid">
    <?php foreach (content_types() as $value => $label): ?>
        <a class="stat-card content-stat-card" href="/admin/content.php?type=<?= e($value) ?>">
            <span><?= e($label) ?></span>
            <strong><?= e($counts[$value] ?? 0) ?></strong>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <form class="filter-form" method="get">
        <label>Type
            <select name="type">
                <option value="">All types</option>
                <?php foreach (content_types() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Search
            <input name="q" value="<?= e($q) ?>" placeholder="Search by name, category or description">
        </label>
        <button class="button secondary" type="submit">Filter</button>
    </form>
</div>

<div class="card">
    <?php if (!$items): ?>
        <p class="muted">No content items found.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Name</th><th>Type</th><th>Category</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['name']) ?></strong>
                        <?php if (!empty($item['description'])): ?><br><span class="muted small"><?= e(mb_strimwidth((string)$item['description'], 0, 110, '…')) ?></span><?php endif; ?>
                    </td>
                    <td><span class="badge"><?= e(content_types()[$item['type']] ?? $item['type']) ?></span></td>
                    <td><?= e($item['category'] ?: '—') ?></td>
                    <td><?= ((int)$item['is_active'] === 1) ? '<span class="badge success">Active</span>' : '<span class="badge">Inactive</span>' ?></td>
                    <td class="actions">
                        <a class="button secondary" href="/admin/content_view.php?id=<?= (int)$item['id'] ?>">Manage / Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
