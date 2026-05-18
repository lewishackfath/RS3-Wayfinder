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
        $action = (string)($_POST['action'] ?? 'toggle_drop');
        $profileId = (int)($_POST['profile_id'] ?? 0);
        $bossId = (int)($_POST['boss_content_item_id'] ?? 0);

        if ($action === 'set_killcount') {
            $killCount = max(0, (int)($_POST['kill_count'] ?? 0));
            set_profile_boss_killcount($profileId, $userId, $bossId, $killCount);
            $success = 'Boss kill count updated.';
        } else {
            $dropId = (int)($_POST['drop_content_item_id'] ?? 0);
            $obtained = (string)($_POST['obtained'] ?? '0') === '1';
            set_profile_boss_drop_obtained($profileId, $userId, $bossId, $dropId, $obtained);
            $success = $obtained ? 'Drop marked as collected.' : 'Drop marked as missing.';
        }
        $profile = active_profile() ?: $profile;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'category' => trim((string)($_GET['category'] ?? '')),
    'completion' => trim((string)($_GET['completion'] ?? '')),
];
$bosses = boss_log_bosses_for_profile((int)$profile['id'], $filters);
$categories = boss_log_categories();
$totals = boss_log_totals_for_profile((int)$profile['id']);

page_header('Boss Drop Log');
?>
<div class="page-title-row">
    <div>
        <h1>Boss Drop Log</h1>
        <p class="muted">Track boss drops for <?= e($profile['rsn']) ?>. Items stay greyed out until you mark them as collected.</p>
    </div>
    <a class="button secondary" href="/account/index.php">Manage profiles</a>
</div>

<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><span>Bosses</span><strong><?= e((string)$totals['boss_count']) ?></strong></div>
    <div class="stat-card"><span>Drops collected</span><strong><?= e($totals['obtained_count'] . '/' . $totals['drop_count']) ?></strong></div>
    <div class="stat-card"><span>Completion</span><strong><?= e($totals['completion_pct'] . '%') ?></strong></div>
    <div class="stat-card"><span>Total KC</span><strong><?= e((string)($totals['total_kill_count'] ?? 0)) ?></strong></div>
</div>

<section class="card filter-card boss-log-filter-card">
    <form method="get" class="filters-form boss-log-filters">
        <label>Search
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Boss or item name">
        </label>
        <label>Category
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Completion
            <select name="completion">
                <option value="">All bosses</option>
                <option value="incomplete" <?= $filters['completion'] === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
                <option value="started" <?= $filters['completion'] === 'started' ? 'selected' : '' ?>>Started</option>
                <option value="complete" <?= $filters['completion'] === 'complete' ? 'selected' : '' ?>>Complete</option>
                <option value="no_drops" <?= $filters['completion'] === 'no_drops' ? 'selected' : '' ?>>No drops linked</option>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button" type="submit">Apply filters</button>
            <a class="button secondary" href="/boss-log/index.php">Reset</a>
        </div>
    </form>
</section>

<?php if (!$bosses): ?>
    <section class="empty-state">
        <h2>No bosses found</h2>
        <p>No active boss content matched your filters, or no boss content has been linked in the Content Library yet.</p>
    </section>
<?php else: ?>
    <div class="boss-log-ledger">
        <?php foreach ($bosses as $boss): ?>
            <?php
                $dropCount = (int)$boss['drop_count'];
                $obtainedCount = (int)$boss['obtained_count'];
                $pct = $dropCount > 0 ? round(($obtainedCount / $dropCount) * 100) : 0;
                $killCount = (int)($boss['kill_count'] ?? 0);
                $bossDomId = 'boss-log-' . (int)$boss['id'];
            ?>
            <details class="boss-log-entry" id="<?= e($bossDomId) ?>">
                <summary class="boss-log-summary">
                    <img class="boss-log-boss-image" src="<?= e(boss_log_icon_url($boss['icon_url'], $boss['name'])) ?>" alt="<?= e($boss['name']) ?>" loading="lazy" referrerpolicy="no-referrer">
                    <div class="boss-log-summary-main">
                        <div class="boss-log-summary-title-row">
                            <h2><?= e($boss['name']) ?> <span class="boss-log-kc">(<?= e((string)$killCount) ?> KC)</span></h2>
                            <?php if ($dropCount > 0 && $obtainedCount === $dropCount): ?><span class="badge success-badge">Complete</span><?php endif; ?>
                        </div>
                        <?php if ($boss['category'] !== ''): ?><p class="muted small boss-log-category"><?= e($boss['category']) ?></p><?php endif; ?>
                        <div class="boss-log-progress">
                            <div class="boss-log-progress-bar" aria-label="<?= e($obtainedCount . ' of ' . $dropCount . ' drops collected') ?>"><span style="width: <?= (int)$pct ?>%"></span></div>
                            <span class="boss-log-progress-label"><?= e($obtainedCount . '/' . $dropCount) ?> drops</span>
                        </div>
                    </div>
                    <span class="boss-log-expand-indicator" aria-hidden="true">Open</span>
                </summary>

                <div class="boss-log-entry-body">
                    <div class="boss-log-actions">
                        <form method="post" class="boss-kc-prompt-form" data-current-kc="<?= (int)$killCount ?>" data-boss-name="<?= e($boss['name']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_killcount">
                            <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                            <input type="hidden" name="boss_content_item_id" value="<?= (int)$boss['id'] ?>">
                            <input type="hidden" name="kill_count" value="<?= (int)$killCount ?>">
                            <button class="button secondary small-button set-kc-button" type="submit">Set kill count</button>
                        </form>
                    </div>

                    <?php if (!$boss['drops']): ?>
                        <p class="muted">No drops have been linked to this boss yet.</p>
                    <?php else: ?>
                        <div class="boss-drop-stamp-grid">
                            <?php foreach ($boss['drops'] as $drop): ?>
                                <form method="post" class="boss-drop-stamp <?= $drop['is_obtained'] ? 'is-obtained' : 'is-missing' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_drop">
                                    <input type="hidden" name="profile_id" value="<?= (int)$profile['id'] ?>">
                                    <input type="hidden" name="boss_content_item_id" value="<?= (int)$boss['id'] ?>">
                                    <input type="hidden" name="drop_content_item_id" value="<?= (int)$drop['id'] ?>">
                                    <input type="hidden" name="obtained" value="<?= $drop['is_obtained'] ? '0' : '1' ?>">
                                    <button type="submit" title="<?= $drop['is_obtained'] ? 'Mark as missing' : 'Mark as collected' ?>">
                                        <span class="stamp-icon-wrap"><img src="<?= e(boss_log_icon_url($drop['icon_url'], $drop['name'])) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"></span>
                                        <span class="boss-drop-name"><?= e($drop['name']) ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<script>
document.addEventListener('submit', function (event) {
    var form = event.target.closest('.boss-kc-prompt-form');
    if (!form) {
        return;
    }

    event.preventDefault();

    var current = form.getAttribute('data-current-kc') || '0';
    var bossName = form.getAttribute('data-boss-name') || 'this boss';
    var value = window.prompt('Set the kill count for ' + bossName + ':', current);

    if (value === null) {
        return;
    }

    value = value.trim();
    if (value === '' || !/^\d+$/.test(value)) {
        window.alert('Please enter a whole number of 0 or higher.');
        return;
    }

    form.querySelector('input[name="kill_count"]').value = value;
    form.submit();
});
</script>
<?php page_footer(); ?>
