<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/layout.php';
require_login();

$user = current_user();
$userId = (int)$user['id'];
$profile = active_profile();
if (!$profile) {
    redirect('/profiles/new.php');
}

$error = null;
$success = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $profileId = (int)($_POST['profile_id'] ?? 0);
        $achievementId = (int)($_POST['achievement_content_item_id'] ?? 0);
        $completed = (string)($_POST['completed'] ?? '0') === '1';
        set_profile_achievement_completed($profileId, $userId, $achievementId, $completed);
        $success = $completed ? 'Achievement marked as completed.' : 'Achievement marked as incomplete.';
        $profile = active_profile() ?: $profile;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
];
$achievements = achievements_for_profile((int)$profile['id'], $filters);
$categories = achievement_categories();
$totals = achievement_totals_for_profile((int)$profile['id']);

page_header('Achievements');
?>
<div class="page-title-row journal-title-row">
    <div>
        <h1>Achievement Journal</h1>
        <p class="muted">Track manual achievements for <?= e($profile['rsn']) ?> and filter by what your current levels, quests and achievements allow.</p>
    </div>
    <a class="button secondary" href="/account/index.php">Manage profile</a>
</div>

<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

<div class="stats-grid achievement-stats-grid">
    <div class="stat-card"><span>Total</span><strong><?= e((string)$totals['total']) ?></strong></div>
    <div class="stat-card"><span>Completed</span><strong><?= e((string)$totals['completed']) ?></strong></div>
    <div class="stat-card"><span>Available</span><strong><?= e((string)$totals['available']) ?></strong></div>
    <div class="stat-card"><span>Blocked</span><strong><?= e((string)$totals['blocked']) ?></strong></div>
</div>

<section class="card filter-card achievements-filter-card">
    <form method="get" class="filters-form achievements-filters">
        <label>Search
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Achievement name or description">
        </label>
        <label>Category
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status">
                <option value="">All achievements</option>
                <option value="available" <?= $filters['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="blocked" <?= $filters['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button" type="submit">Apply filters</button>
            <a class="button secondary" href="/achievements/index.php">Reset</a>
        </div>
    </form>
</section>

<?php if (!$achievements): ?>
    <section class="empty-state">
        <h2>No achievements found</h2>
        <p>No active achievement content matched your filters, or no achievements have been added to the Content Library yet.</p>
    </section>
<?php else: ?>
    <div class="achievement-journal-list">
        <?php foreach ($achievements as $achievement): ?>
            <?php
                $requirements = $achievement['requirements'];
                $metadata = $achievement['metadata'] ?? [];
                $status = (string)$achievement['availability_status'];
                $statusLabel = [
                    'completed' => 'Completed',
                    'available' => 'Available',
                    'blocked' => 'Blocked',
                ][$status] ?? ucfirst($status);
            ?>
            <article class="card achievement-entry achievement-status-<?= e($status) ?>">
                <div class="achievement-entry-main">
                    <img class="achievement-icon" src="<?= e(achievement_icon_url($achievement['icon_url'] ?? null)) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                    <div class="achievement-entry-copy">
                        <div class="card-row">
                            <h2><?= e($achievement['name']) ?></h2>
                            <span class="badge <?= $status === 'completed' ? 'success-badge' : ($status === 'blocked' ? 'warning-badge' : '') ?>"><?= e($statusLabel) ?></span>
                        </div>
                        <?php if (!empty($achievement['category'])): ?><p class="muted small"><?= e((string)$achievement['category']) ?></p><?php endif; ?>
                        <?php if (!empty($achievement['description'])): ?><p><?= nl2br(e((string)$achievement['description'])) ?></p><?php endif; ?>

                        <?php if (!empty($metadata['subcategory']) || !empty($metadata['subsubcategory'])): ?>
                            <div class="achievement-meta-row">
                                <?php if (!empty($metadata['subcategory'])): ?><span class="badge"><?= e((string)$metadata['subcategory']) ?></span><?php endif; ?>
                                <?php if (!empty($metadata['subsubcategory'])): ?><span class="badge"><?= e((string)$metadata['subsubcategory']) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="achievement-requirements">
                            <?php if ((int)$requirements['total_count'] === 0): ?>
                                <span class="muted small">No requirements configured.</span>
                            <?php else: ?>
                                <span class="muted small"><?= e($requirements['met_count'] . '/' . $requirements['total_count']) ?> requirements met</span>
                                <?php if (!$requirements['is_available']): ?>
                                    <ul>
                                        <?php foreach (array_slice($requirements['blocked_by'], 0, 6) as $blocked): ?>
                                            <li><?= e($blocked) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <form method="post" class="achievement-toggle-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                    <input type="hidden" name="achievement_content_item_id" value="<?= (int)$achievement['id'] ?>">
                    <input type="hidden" name="completed" value="<?= $achievement['is_completed'] ? '0' : '1' ?>">
                    <button class="button <?= $achievement['is_completed'] ? 'secondary' : '' ?>" type="submit">
                        <?= $achievement['is_completed'] ? 'Mark incomplete' : 'Mark complete' ?>
                    </button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
