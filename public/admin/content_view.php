<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('content.view');

$id = (int)($_GET['id'] ?? 0);
$item = content_item_by_id($id);
if (!$item) abort_page(404, 'Content item not found.');

$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_permission('content.manage');
    require_csrf();

    try {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'add_skill_requirement') {
            add_content_skill_requirement($id, (string)($_POST['skill_name'] ?? ''), (int)($_POST['required_level'] ?? 0), (string)($_POST['notes'] ?? ''));
        } elseif ($action === 'delete_skill_requirement') {
            delete_content_skill_requirement((int)($_POST['requirement_id'] ?? 0));
        } elseif ($action === 'add_quest_requirement') {
            add_content_quest_requirement($id, (int)($_POST['required_content_item_id'] ?? 0), (string)($_POST['notes'] ?? ''));
        } elseif ($action === 'delete_quest_requirement') {
            delete_content_quest_requirement((int)($_POST['requirement_id'] ?? 0));
        } elseif ($action === 'add_boss_drop_source') {
            add_boss_drop_source($id, (int)($_POST['drop_content_item_id'] ?? 0), (string)($_POST['rarity'] ?? ''), (string)($_POST['quantity'] ?? ''), (string)($_POST['notes'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        } elseif ($action === 'add_drop_to_boss') {
            add_boss_drop_source((int)($_POST['boss_content_item_id'] ?? 0), $id, (string)($_POST['rarity'] ?? ''), (string)($_POST['quantity'] ?? ''), (string)($_POST['notes'] ?? ''), (int)($_POST['sort_order'] ?? 0));
        } elseif ($action === 'delete_boss_drop_source') {
            delete_boss_drop_source((int)($_POST['source_id'] ?? 0));
        }
        redirect('/admin/content_view.php?id=' . $id);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$metadata = content_metadata($item);
$skillRequirements = content_skill_requirements($id);
$questRequirements = content_quest_requirements($id);
$questOptions = content_items_for_select('quest');
$skills = runemetrics_skill_names();
$bossOptions = content_items_for_select('boss');
$dropOptions = array_merge(content_items_for_select('drop'), content_items_for_select('item'));
$bossDrops = $item['type'] === 'boss' ? boss_drop_sources_for_boss($id) : [];
$dropSources = in_array($item['type'], ['drop','item'], true) ? boss_sources_for_drop($id) : [];

page_header('Manage Content');
?>
<div class="page-title-row">
    <div>
        <h1><?= e($item['name']) ?></h1>
        <p class="muted"><?= e(content_types()[$item['type']] ?? $item['type']) ?><?= $item['category'] ? ' • ' . e($item['category']) : '' ?></p>
    </div>
    <div class="form-actions">
        <a class="button secondary" href="/admin/content.php">Content library</a>
        <?php if (current_user_can('content.manage')): ?><a class="button" href="/admin/content_edit.php?id=<?= (int)$item['id'] ?>">Edit</a><?php endif; ?>
    </div>
</div>

<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
    <h2>Overview</h2>
    <p><?= nl2br(e($item['description'] ?: 'No description yet.')) ?></p>
    <?php if (!empty($item['source_url'])): ?><p><a href="<?= e($item['source_url']) ?>" target="_blank" rel="noopener">Source / Wiki</a></p><?php endif; ?>
    <p class="muted small">Slug: <code><?= e($item['slug']) ?></code></p>

    <?php if ($item['type'] === 'quest'): ?>
        <div class="content-meta-grid">
            <div><span>Difficulty</span><strong><?= e($metadata['difficulty_label'] ?? 'Unknown') ?></strong></div>
            <div><span>Quest Points</span><strong><?= isset($metadata['quest_points']) ? e((string)$metadata['quest_points']) : '—' ?></strong></div>
            <div><span>Membership</span><strong><?= array_key_exists('members', $metadata) ? (!empty($metadata['members']) ? 'Members' : 'Free-to-play') : '—' ?></strong></div>
            <div><span>Timeline</span><strong><?= e($metadata['quest_timeline'] ?? '—') ?></strong></div>
            <div><span>Series</span><strong><?= e($metadata['quest_series'] ?? '—') ?></strong></div>
        </div>
    <?php endif; ?>
</div>

<div class="grid two-col-grid admin-dashboard-grid">
    <div class="card">
        <h2>Skill Requirements</h2>
        <?php if (!$skillRequirements): ?>
            <p class="muted">No skill requirements configured.</p>
        <?php else: ?>
            <table class="table compact-table">
                <thead><tr><th>Skill</th><th>Level</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($skillRequirements as $req): ?>
                    <tr>
                        <td><?= e($req['skill_name']) ?></td>
                        <td><?= (int)$req['required_level'] ?></td>
                        <td><?= e($req['notes'] ?: '—') ?></td>
                        <td>
                            <?php if (current_user_can('content.manage')): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_skill_requirement">
                                    <input type="hidden" name="requirement_id" value="<?= (int)$req['id'] ?>">
                                    <button class="button secondary" type="submit">Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (current_user_can('content.manage')): ?>
            <form method="post" class="mini-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_skill_requirement">
                <div class="form-grid">
                    <label>Skill
                        <select name="skill_name">
                            <?php foreach ($skills as $skillName): ?><option value="<?= e($skillName) ?>"><?= e($skillName) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Level
                        <input type="number" name="required_level" min="1" max="150" required>
                    </label>
                </div>
                <label>Notes
                    <input name="notes">
                </label>
                <button class="button secondary" type="submit">Add skill requirement</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Quest Requirements</h2>
        <?php if (!$questRequirements): ?>
            <p class="muted">No quest requirements configured.</p>
        <?php else: ?>
            <table class="table compact-table">
                <thead><tr><th>Quest</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($questRequirements as $req): ?>
                    <tr>
                        <td><?= e($req['required_name']) ?></td>
                        <td><?= e($req['notes'] ?: '—') ?></td>
                        <td>
                            <?php if (current_user_can('content.manage')): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_quest_requirement">
                                    <input type="hidden" name="requirement_id" value="<?= (int)$req['id'] ?>">
                                    <button class="button secondary" type="submit">Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (current_user_can('content.manage')): ?>
            <form method="post" class="mini-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_quest_requirement">
                <label>Required quest
                    <select name="required_content_item_id">
                        <?php foreach ($questOptions as $quest): if ((int)$quest['id'] === $id) continue; ?>
                            <option value="<?= (int)$quest['id'] ?>"><?= e($quest['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Notes
                    <input name="notes">
                </label>
                <button class="button secondary" type="submit">Add quest requirement</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($item['type'] === 'boss'): ?>
    <div class="card">
        <h2>Boss Drop Sources</h2>
        <p class="muted">Drops are reusable content items. The same drop can be linked to multiple bosses.</p>
        <?php if (!$bossDrops): ?>
            <p class="muted">No drops configured for this boss.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Drop</th><th>Rarity</th><th>Quantity</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($bossDrops as $source): ?>
                    <tr>
                        <td><?= e($source['drop_name']) ?></td>
                        <td><?= e($source['rarity'] ?: '—') ?></td>
                        <td><?= e($source['quantity'] ?: '—') ?></td>
                        <td><?= e($source['notes'] ?: '—') ?></td>
                        <td>
                            <?php if (current_user_can('content.manage')): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_boss_drop_source">
                                    <input type="hidden" name="source_id" value="<?= (int)$source['id'] ?>">
                                    <button class="button secondary" type="submit">Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (current_user_can('content.manage')): ?>
            <form method="post" class="mini-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_boss_drop_source">
                <label>Drop / Item
                    <select name="drop_content_item_id">
                        <?php foreach ($dropOptions as $drop): ?><option value="<?= (int)$drop['id'] ?>"><?= e($drop['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="form-grid">
                    <label>Rarity <input name="rarity" placeholder="Rare / 1/500"></label>
                    <label>Quantity <input name="quantity" placeholder="1"></label>
                    <label>Sort order <input type="number" name="sort_order" value="0"></label>
                </div>
                <label>Notes <input name="notes"></label>
                <button class="button secondary" type="submit">Add drop source</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (in_array($item['type'], ['drop','item'], true)): ?>
    <div class="card">
        <h2>Dropped By</h2>
        <?php if (!$dropSources): ?>
            <p class="muted">This item is not linked to any bosses yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Boss</th><th>Rarity</th><th>Quantity</th><th>Notes</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($dropSources as $source): ?>
                    <tr>
                        <td><?= e($source['boss_name']) ?></td>
                        <td><?= e($source['rarity'] ?: '—') ?></td>
                        <td><?= e($source['quantity'] ?: '—') ?></td>
                        <td><?= e($source['notes'] ?: '—') ?></td>
                        <td>
                            <?php if (current_user_can('content.manage')): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_boss_drop_source">
                                    <input type="hidden" name="source_id" value="<?= (int)$source['id'] ?>">
                                    <button class="button secondary" type="submit">Remove</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if (current_user_can('content.manage')): ?>
            <form method="post" class="mini-admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_drop_to_boss">
                <label>Boss
                    <select name="boss_content_item_id">
                        <?php foreach ($bossOptions as $boss): ?><option value="<?= (int)$boss['id'] ?>"><?= e($boss['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="form-grid">
                    <label>Rarity <input name="rarity" placeholder="Rare / 1/500"></label>
                    <label>Quantity <input name="quantity" placeholder="1"></label>
                    <label>Sort order <input type="number" name="sort_order" value="0"></label>
                </div>
                <label>Notes <input name="notes"></label>
                <button class="button secondary" type="submit">Add boss source</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php page_footer(); ?>
