<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_permission('journeys.view');

$journeys = all_journeys(false);
page_header('Manage Journeys');
?>
<div class="page-title-row">
    <div>
        <h1>Journeys</h1>
        <p class="muted">Create progression paths made from chapters and steps.</p>
    </div>
    <?php if (current_user_can('journeys.manage')): ?>
        <a class="button" href="/admin/journey_edit.php">Create journey</a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (!$journeys): ?>
        <p class="muted">No journeys have been created yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Journey</th><th>Status</th><th>Sort</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($journeys as $journey): ?>
                <tr>
                    <td>
                        <strong><?= e($journey['icon'] ?: '🧭') ?> <?= e($journey['name']) ?></strong><br>
                        <span class="muted small"><?= e($journey['slug']) ?></span><br>
                        <?php foreach (journey_tags_for_journey((int)$journey['id']) as $tag): ?><span class="badge"><?= e($tag['name']) ?></span><?php endforeach; ?>
                    </td>
                    <td><?= ((int)$journey['is_published'] === 1) ? '<span class="badge success">Published</span>' : '<span class="badge">Draft</span>' ?></td>
                    <td><?= (int)$journey['sort_order'] ?></td>
                    <td class="actions">
                        <a class="button secondary" href="/admin/journey_view.php?id=<?= (int)$journey['id'] ?>">Manage</a>
                        <?php if (current_user_can('journeys.manage')): ?>
                            <a class="button secondary" href="/admin/journey_edit.php?id=<?= (int)$journey['id'] ?>">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
